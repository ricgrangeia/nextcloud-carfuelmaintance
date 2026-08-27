<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Controller;

use OCA\CarFuelMaintance\Service\CarDetailService;
use OCA\CarFuelMaintance\Service\CarService;
use OCA\CarFuelMaintance\Service\FuelService;
use OCA\CarFuelMaintance\Service\MaintenanceService;
use OCA\CarFuelMaintance\Service\SettingsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
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
				['method' => 'GET', 'path' => '/api/v1/settings', 'summary' => 'Get user preferences (reminderMonths: how many months ahead of a due date/mileage counts as "due soon"; currencySymbol: shown after every money value app-wide)'],
				['method' => 'PUT', 'path' => '/api/v1/settings', 'summary' => 'Update user preferences (reminderMonths, currencySymbol) — only fields present in the request are changed'],
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
	): DataResponse {
		return new DataResponse(
			$this->carService->create($this->getUserId(), $name, $brand, $model, $plate, $year, $fuelType, $secondaryFuelType, $initialOdometer, $odometerUnit, $notes),
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
	): DataResponse {
		try {
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
			));
		} catch (DoesNotExistException) {
			return $this->notFound();
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

	// --- Settings -----------------------------------------------------------

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/settings')]
	public function getSettings(): DataResponse {
		$userId = $this->getUserId();
		return new DataResponse([
			'reminderMonths' => $this->settingsService->getReminderMonths($userId),
			'currencySymbol' => $this->settingsService->getCurrencySymbol($userId),
		]);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/settings')]
	public function updateSettings(?int $reminderMonths = null, ?string $currencySymbol = null): DataResponse {
		$userId = $this->getUserId();
		if ($reminderMonths !== null) {
			$this->settingsService->setReminderMonths($userId, $reminderMonths);
		}
		if ($currencySymbol !== null) {
			$this->settingsService->setCurrencySymbol($userId, $currencySymbol);
		}
		return new DataResponse([
			'reminderMonths' => $this->settingsService->getReminderMonths($userId),
			'currencySymbol' => $this->settingsService->getCurrencySymbol($userId),
		]);
	}
}
