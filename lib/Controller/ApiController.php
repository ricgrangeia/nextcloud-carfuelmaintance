<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Controller;

use OCA\CarFuelMaintance\Service\CarDetailService;
use OCA\CarFuelMaintance\Service\CarService;
use OCA\CarFuelMaintance\Service\FuelService;
use OCA\CarFuelMaintance\Service\MaintenanceService;
use OCA\CarFuelMaintance\Service\PartService;
use OCA\CarFuelMaintance\Service\SettingsService;
use OCA\CarFuelMaintance\Service\TripService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Token-friendly OCS API, mirroring the SPA's endpoints one-for-one.
 *
 * Meant for external/automated clients (e.g. an AI assistant pairing with the
 * user) authenticated via a Nextcloud "app password" (Basic Auth), not a
 * browser session — see Settings > Security > "Create new app password".
 * Every request must carry the `OCS-APIRequest: true` header, per the
 * standard OCS API convention.
 */
class ApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CarService $carService,
		private CarDetailService $carDetailService,
		private FuelService $fuelService,
		private MaintenanceService $maintenanceService,
		private PartService $partService,
		private TripService $tripService,
		private SettingsService $settingsService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	private function notFound(): DataResponse {
		return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
	}

	private function invalidDate(): DataResponse {
		return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
	}

	// --- Discovery ------------------------------------------------------

	#[PublicPage]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/help')]
	public function help(): DataResponse {
		$host = $this->request->getServerProtocol() . '://' . $this->request->getServerHost();

		return new DataResponse([
			'description' => 'Car Fuel & Maintenance tracks fuel fill-ups and maintenance work per car. Fuel entries compute consumption (fill-to-fill) and cost automatically; maintenance entries support due-date/due-mileage reminders. This API mirrors the web UI one-for-one and is meant for external/automated clients, e.g. an AI assistant pairing with the user.',
			'authentication' => [
				'method' => 'HTTP Basic Auth using a Nextcloud "app password" (NOT the account password)',
				'howToGet' => 'In Nextcloud: Settings > Security > Devices & sessions > "Create new app password". The value is shown only once.',
				'requiredHeader' => 'OCS-APIRequest: true (on every request, standard Nextcloud OCS API requirement)',
			],
			'fullSpec' => $host . '/custom_apps/carfuelmaintance/openapi.json',
			'fullSpecNote' => 'OpenAPI 3.0 document with every endpoint, parameter and response shape. Read this before guessing endpoint names.',
			'quickReference' => [
				['method' => 'GET', 'path' => '/api/v1/cars', 'summary' => 'List your cars (archived ones excluded unless ?includeArchived=true)'],
				['method' => 'POST', 'path' => '/api/v1/cars', 'summary' => 'Create a car (name, brand, model, plate, year, fuelType, initialOdometer, odometerUnit, notes)'],
				['method' => 'GET', 'path' => '/api/v1/cars/{id}', 'summary' => 'Get a car plus its fuel/maintenance entries and computed stats (consumption, spend, reminders)'],
				['method' => 'PUT', 'path' => '/api/v1/cars/{id}', 'summary' => 'Update a car (any field, archived=true to archive / false to restore)'],
				['method' => 'DELETE', 'path' => '/api/v1/cars/{id}', 'summary' => 'Delete a car and all its entries'],
				['method' => 'GET', 'path' => '/api/v1/cars/{carId}/fuel', 'summary' => 'List fuel entries for a car'],
				['method' => 'POST', 'path' => '/api/v1/cars/{carId}/fuel', 'summary' => 'Log a fuel fill-up (entryDate, odometer, quantity, unit, pricePerUnit or totalCost, fullTank, station)'],
				['method' => 'PUT', 'path' => '/api/v1/fuel/{id}', 'summary' => 'Update a fuel entry'],
				['method' => 'DELETE', 'path' => '/api/v1/fuel/{id}', 'summary' => 'Delete a fuel entry'],
				['method' => 'GET', 'path' => '/api/v1/cars/{carId}/maintenance', 'summary' => 'List maintenance entries for a car'],
				['method' => 'POST', 'path' => '/api/v1/cars/{carId}/maintenance', 'summary' => 'Log maintenance work (entryDate, type, odometer, description, cost, workshop, nextDueDate, nextDueOdometer)'],
				['method' => 'PUT', 'path' => '/api/v1/maintenance/{id}', 'summary' => 'Update a maintenance entry'],
				['method' => 'DELETE', 'path' => '/api/v1/maintenance/{id}', 'summary' => 'Delete a maintenance entry'],
				['method' => 'GET', 'path' => '/api/v1/parts', 'summary' => 'List your parts/equipment inventory (optionally ?carId= to filter to one car; parts with no car are general stock)'],
				['method' => 'POST', 'path' => '/api/v1/parts', 'summary' => 'Add a part (name, carId, reference, condition "new"|"used", category, location, quantity, cost, notes)'],
				['method' => 'PUT', 'path' => '/api/v1/parts/{id}', 'summary' => 'Update a part'],
				['method' => 'DELETE', 'path' => '/api/v1/parts/{id}', 'summary' => 'Delete a part (and its photo, if any)'],
				['method' => 'POST', 'path' => '/api/v1/parts/{id}/image', 'summary' => 'Upload/replace a part\'s photo (multipart/form-data, field name "image"; jpeg/png/webp/gif, max 10MB)'],
				['method' => 'GET', 'path' => '/api/v1/parts/{id}/image', 'summary' => 'Download a part\'s photo'],
				['method' => 'GET', 'path' => '/api/v1/cars/{carId}/trips', 'summary' => 'List trip log entries for a car'],
				['method' => 'POST', 'path' => '/api/v1/cars/{carId}/trips', 'summary' => 'Log a trip (tripDate, startOdometer, endOdometer, purpose "business"|"personal"|"other", origin, destination, tolls, otherCosts)'],
				['method' => 'PUT', 'path' => '/api/v1/trips/{id}', 'summary' => 'Update a trip'],
				['method' => 'DELETE', 'path' => '/api/v1/trips/{id}', 'summary' => 'Delete a trip'],
				['method' => 'GET', 'path' => '/api/v1/settings', 'summary' => 'Get user preferences (reminderMonths: how many months ahead of a due date/mileage counts as "due soon"; currencySymbol: shown after every money value app-wide; consumptionFormat: "per100" e.g. L/100km or "perUnit" e.g. km/L, MPG; notificationsEnabled: whether the background job sends a Nextcloud notification when a reminder becomes due soon/overdue)'],
				['method' => 'PUT', 'path' => '/api/v1/settings', 'summary' => 'Update user preferences (reminderMonths, currencySymbol, consumptionFormat, notificationsEnabled) — only fields present in the request are changed'],
			],
		]);
	}

	// --- Cars -------------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/cars')]
	public function listCars(bool $includeArchived = false): DataResponse {
		return new DataResponse($this->carService->findAll($this->getUserId(), $includeArchived));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/cars')]
	public function createCar(
		string $name,
		?string $brand = null,
		?string $model = null,
		?string $plate = null,
		?int $year = null,
		string $fuelType = 'gasoline',
		?string $secondaryFuelType = null,
		float $initialOdometer = 0.0,
		string $odometerUnit = 'km',
		?string $notes = null,
		?float $purchasePrice = null,
		?string $purchaseDate = null,
	): DataResponse {
		try {
			$purchaseDateValue = $purchaseDate !== null ? new \DateTimeImmutable($purchaseDate) : null;
		} catch (\Exception) {
			return $this->invalidDate();
		}
		return new DataResponse(
			$this->carService->create($this->getUserId(), $name, $brand, $model, $plate, $year, $fuelType, $secondaryFuelType, $initialOdometer, $odometerUnit, $notes, $purchasePrice, $purchaseDateValue),
			Http::STATUS_CREATED,
		);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/cars/{id}', requirements: ['id' => '\d+'])]
	public function getCar(int $id): DataResponse {
		try {
			return new DataResponse($this->carDetailService->build($id, $this->getUserId()));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/cars/{id}', requirements: ['id' => '\d+'])]
	public function updateCar(
		int $id,
		?string $name = null,
		?string $brand = null,
		bool $brandProvided = false,
		?string $model = null,
		bool $modelProvided = false,
		?string $plate = null,
		bool $plateProvided = false,
		?int $year = null,
		bool $yearProvided = false,
		?string $fuelType = null,
		?string $secondaryFuelType = null,
		bool $secondaryFuelTypeProvided = false,
		?float $initialOdometer = null,
		?string $odometerUnit = null,
		?string $notes = null,
		bool $notesProvided = false,
		?bool $archived = null,
		?float $purchasePrice = null,
		bool $purchasePriceProvided = false,
		?string $purchaseDate = null,
		bool $purchaseDateProvided = false,
	): DataResponse {
		try {
			$purchaseDateValue = $purchaseDateProvided && $purchaseDate !== null ? new \DateTimeImmutable($purchaseDate) : null;
			return new DataResponse($this->carService->update(
				$id,
				$this->getUserId(),
				$name,
				$brand,
				$brandProvided,
				$model,
				$modelProvided,
				$plate,
				$plateProvided,
				$year,
				$yearProvided,
				$fuelType,
				$secondaryFuelType,
				$secondaryFuelTypeProvided,
				$initialOdometer,
				$odometerUnit,
				$notes,
				$notesProvided,
				$archived,
				$purchasePrice,
				$purchasePriceProvided,
				$purchaseDateValue,
				$purchaseDateProvided,
			));
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return $this->invalidDate();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/cars/{id}', requirements: ['id' => '\d+'])]
	public function deleteCar(int $id): DataResponse {
		try {
			$this->carService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Fuel entries -------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/cars/{carId}/fuel', requirements: ['carId' => '\d+'])]
	public function listFuel(int $carId): DataResponse {
		try {
			return new DataResponse($this->fuelService->findAll($carId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/cars/{carId}/fuel', requirements: ['carId' => '\d+'])]
	public function createFuel(
		int $carId,
		string $entryDate,
		float $odometer,
		float $quantity,
		string $fuelType = 'gasoline',
		string $unit = 'L',
		?float $pricePerUnit = null,
		?float $totalCost = null,
		bool $fullTank = true,
		?string $station = null,
		?string $notes = null,
		int $sortOrder = 0,
	): DataResponse {
		try {
			return new DataResponse(
				$this->fuelService->create($carId, $this->getUserId(), new \DateTimeImmutable($entryDate), $odometer, $quantity, $fuelType, $unit, $pricePerUnit, $totalCost, $fullTank, $station, $notes, $sortOrder),
				Http::STATUS_CREATED,
			);
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return $this->invalidDate();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/fuel/{id}', requirements: ['id' => '\d+'])]
	public function updateFuel(
		int $id,
		?string $entryDate = null,
		?string $fuelType = null,
		?float $odometer = null,
		?float $quantity = null,
		?string $unit = null,
		?float $pricePerUnit = null,
		bool $pricePerUnitProvided = false,
		?float $totalCost = null,
		bool $totalCostProvided = false,
		?bool $fullTank = null,
		?string $station = null,
		bool $stationProvided = false,
		?string $notes = null,
		bool $notesProvided = false,
		?int $sortOrder = null,
	): DataResponse {
		try {
			$date = $entryDate !== null ? new \DateTimeImmutable($entryDate) : null;
			return new DataResponse($this->fuelService->update(
				$id,
				$this->getUserId(),
				$date,
				$fuelType,
				$odometer,
				$quantity,
				$unit,
				$pricePerUnit,
				$pricePerUnitProvided,
				$totalCost,
				$totalCostProvided,
				$fullTank,
				$station,
				$stationProvided,
				$notes,
				$notesProvided,
				$sortOrder,
			));
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return $this->invalidDate();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/fuel/{id}', requirements: ['id' => '\d+'])]
	public function deleteFuel(int $id): DataResponse {
		try {
			$this->fuelService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Maintenance entries ----------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/cars/{carId}/maintenance', requirements: ['carId' => '\d+'])]
	public function listMaintenance(int $carId): DataResponse {
		try {
			return new DataResponse($this->maintenanceService->findAll($carId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/cars/{carId}/maintenance', requirements: ['carId' => '\d+'])]
	public function createMaintenance(
		int $carId,
		string $entryDate,
		string $type,
		?float $odometer = null,
		?string $description = null,
		?float $cost = null,
		?string $workshop = null,
		?string $nextDueDate = null,
		?float $nextDueOdometer = null,
		?string $notes = null,
		int $sortOrder = 0,
	): DataResponse {
		try {
			$dueDate = $nextDueDate !== null ? new \DateTimeImmutable($nextDueDate) : null;
			return new DataResponse(
				$this->maintenanceService->create($carId, $this->getUserId(), new \DateTimeImmutable($entryDate), $type, $odometer, $description, $cost, $workshop, $dueDate, $nextDueOdometer, $notes, $sortOrder),
				Http::STATUS_CREATED,
			);
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return $this->invalidDate();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/maintenance/{id}', requirements: ['id' => '\d+'])]
	public function updateMaintenance(
		int $id,
		?string $entryDate = null,
		?string $type = null,
		?float $odometer = null,
		bool $odometerProvided = false,
		?string $description = null,
		bool $descriptionProvided = false,
		?float $cost = null,
		bool $costProvided = false,
		?string $workshop = null,
		bool $workshopProvided = false,
		?string $nextDueDate = null,
		bool $nextDueDateProvided = false,
		?float $nextDueOdometer = null,
		bool $nextDueOdometerProvided = false,
		?string $notes = null,
		bool $notesProvided = false,
		?int $sortOrder = null,
	): DataResponse {
		try {
			$date = $entryDate !== null ? new \DateTimeImmutable($entryDate) : null;
			$dueDate = $nextDueDateProvided && $nextDueDate !== null ? new \DateTimeImmutable($nextDueDate) : null;
			return new DataResponse($this->maintenanceService->update(
				$id,
				$this->getUserId(),
				$date,
				$type,
				$odometer,
				$odometerProvided,
				$description,
				$descriptionProvided,
				$cost,
				$costProvided,
				$workshop,
				$workshopProvided,
				$dueDate,
				$nextDueDateProvided,
				$nextDueOdometer,
				$nextDueOdometerProvided,
				$notes,
				$notesProvided,
				$sortOrder,
			));
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return $this->invalidDate();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/maintenance/{id}', requirements: ['id' => '\d+'])]
	public function deleteMaintenance(int $id): DataResponse {
		try {
			$this->maintenanceService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Parts / equipment inventory ---------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/parts')]
	public function listParts(?int $carId = null): DataResponse {
		return new DataResponse($this->partService->findAll($this->getUserId(), $carId));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/parts')]
	public function createPart(
		string $name,
		?int $carId = null,
		?string $reference = null,
		string $condition = 'new',
		?string $category = null,
		?string $location = null,
		int $quantity = 1,
		?float $cost = null,
		?string $notes = null,
	): DataResponse {
		try {
			return new DataResponse(
				$this->partService->create($this->getUserId(), $name, $carId, $reference, $condition, $category, $location, $quantity, $cost, $notes),
				Http::STATUS_CREATED,
			);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/parts/{id}', requirements: ['id' => '\d+'])]
	public function updatePart(
		int $id,
		?string $name = null,
		?int $carId = null,
		bool $carIdProvided = false,
		?string $reference = null,
		bool $referenceProvided = false,
		?string $condition = null,
		?string $category = null,
		bool $categoryProvided = false,
		?string $location = null,
		bool $locationProvided = false,
		?int $quantity = null,
		?float $cost = null,
		bool $costProvided = false,
		?string $notes = null,
		bool $notesProvided = false,
	): DataResponse {
		try {
			return new DataResponse($this->partService->update(
				$id,
				$this->getUserId(),
				$name,
				$carId,
				$carIdProvided,
				$reference,
				$referenceProvided,
				$condition,
				$category,
				$categoryProvided,
				$location,
				$locationProvided,
				$quantity,
				$cost,
				$costProvided,
				$notes,
				$notesProvided,
			));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/parts/{id}', requirements: ['id' => '\d+'])]
	public function deletePart(int $id): DataResponse {
		try {
			$this->partService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/parts/{id}/image', requirements: ['id' => '\d+'])]
	public function uploadPartImage(int $id): DataResponse {
		$uploaded = $this->request->getUploadedFile('image');
		if ($uploaded === null || !is_string($uploaded['tmp_name'] ?? null) || !is_uploaded_file($uploaded['tmp_name'])) {
			return new DataResponse(['message' => 'No image uploaded'], Http::STATUS_BAD_REQUEST);
		}

		try {
			return new DataResponse($this->partService->setImage($id, $this->getUserId(), $uploaded['tmp_name'], (string) ($uploaded['type'] ?? '')));
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/parts/{id}/image', requirements: ['id' => '\d+'])]
	public function partImage(int $id): Http\Response {
		try {
			$file = $this->partService->getImage($id, $this->getUserId());
			return new DataDisplayResponse($file->getContent(), Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
		} catch (DoesNotExistException) {
			return new Http\Response(Http::STATUS_NOT_FOUND);
		}
	}

	// --- Trips ----------------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/cars/{carId}/trips', requirements: ['carId' => '\d+'])]
	public function listTrips(int $carId): DataResponse {
		try {
			return new DataResponse($this->tripService->findAll($carId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/cars/{carId}/trips', requirements: ['carId' => '\d+'])]
	public function createTrip(
		int $carId,
		string $tripDate,
		float $startOdometer,
		float $endOdometer,
		string $purpose = 'business',
		?string $origin = null,
		?string $destination = null,
		?float $tolls = null,
		?float $otherCosts = null,
		?string $notes = null,
		int $sortOrder = 0,
	): DataResponse {
		try {
			return new DataResponse(
				$this->tripService->create($carId, $this->getUserId(), new \DateTimeImmutable($tripDate), $startOdometer, $endOdometer, $purpose, $origin, $destination, $tolls, $otherCosts, $notes, $sortOrder),
				Http::STATUS_CREATED,
			);
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return $this->invalidDate();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/trips/{id}', requirements: ['id' => '\d+'])]
	public function updateTrip(
		int $id,
		?string $tripDate = null,
		?float $startOdometer = null,
		?float $endOdometer = null,
		?string $purpose = null,
		?string $origin = null,
		bool $originProvided = false,
		?string $destination = null,
		bool $destinationProvided = false,
		?float $tolls = null,
		bool $tollsProvided = false,
		?float $otherCosts = null,
		bool $otherCostsProvided = false,
		?string $notes = null,
		bool $notesProvided = false,
		?int $sortOrder = null,
	): DataResponse {
		try {
			$date = $tripDate !== null ? new \DateTimeImmutable($tripDate) : null;
			return new DataResponse($this->tripService->update(
				$id,
				$this->getUserId(),
				$date,
				$startOdometer,
				$endOdometer,
				$purpose,
				$origin,
				$originProvided,
				$destination,
				$destinationProvided,
				$tolls,
				$tollsProvided,
				$otherCosts,
				$otherCostsProvided,
				$notes,
				$notesProvided,
				$sortOrder,
			));
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\Exception) {
			return $this->invalidDate();
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/trips/{id}', requirements: ['id' => '\d+'])]
	public function deleteTrip(int $id): DataResponse {
		try {
			$this->tripService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	// --- Settings -----------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/settings')]
	public function getSettings(): DataResponse {
		$userId = $this->getUserId();
		return new DataResponse([
			'reminderMonths' => $this->settingsService->getReminderMonths($userId),
			'currencySymbol' => $this->settingsService->getCurrencySymbol($userId),
			'consumptionFormat' => $this->settingsService->getConsumptionFormat($userId),
			'notificationsEnabled' => $this->settingsService->getNotificationsEnabled($userId),
		]);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/settings')]
	public function updateSettings(?int $reminderMonths = null, ?string $currencySymbol = null, ?string $consumptionFormat = null, ?bool $notificationsEnabled = null): DataResponse {
		$userId = $this->getUserId();
		if ($reminderMonths !== null) {
			$this->settingsService->setReminderMonths($userId, $reminderMonths);
		}
		if ($currencySymbol !== null) {
			$this->settingsService->setCurrencySymbol($userId, $currencySymbol);
		}
		if ($consumptionFormat !== null) {
			$this->settingsService->setConsumptionFormat($userId, $consumptionFormat);
		}
		if ($notificationsEnabled !== null) {
			$this->settingsService->setNotificationsEnabled($userId, $notificationsEnabled);
		}
		return new DataResponse([
			'reminderMonths' => $this->settingsService->getReminderMonths($userId),
			'currencySymbol' => $this->settingsService->getCurrencySymbol($userId),
			'consumptionFormat' => $this->settingsService->getConsumptionFormat($userId),
			'notificationsEnabled' => $this->settingsService->getNotificationsEnabled($userId),
		]);
	}
}
