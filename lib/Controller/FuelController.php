<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Controller;

use OCA\CarFuelMaintance\Service\FuelService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class FuelController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private FuelService $fuelService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/cars/{carId}/fuel', requirements: ['carId' => '\d+'])]
	public function index(int $carId): DataResponse {
		try {
			return new DataResponse($this->fuelService->findAll($carId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/cars/{carId}/fuel', requirements: ['carId' => '\d+'])]
	public function create(
		int $carId,
		string $entryDate,
		float $odometer,
		float $quantity,
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
				$this->fuelService->create($carId, $this->getUserId(), new \DateTimeImmutable($entryDate), $odometer, $quantity, $unit, $pricePerUnit, $totalCost, $fullTank, $station, $notes, $sortOrder),
				Http::STATUS_CREATED,
			);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/fuel/{id}', requirements: ['id' => '\d+'])]
	public function update(
		int $id,
		?string $entryDate = null,
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
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/fuel/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->fuelService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
