<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Controller;

use OCA\CarFuelMaintance\Service\CarDetailService;
use OCA\CarFuelMaintance\Service\CarService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class CarController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private CarService $carService,
		private CarDetailService $carDetailService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/cars')]
	public function index(bool $includeArchived = false): DataResponse {
		return new DataResponse($this->carService->findAll($this->getUserId(), $includeArchived));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/cars')]
	public function create(
		string $name,
		?string $brand = null,
		?string $model = null,
		?string $plate = null,
		?int $year = null,
		string $fuelType = 'gasoline',
		float $initialOdometer = 0.0,
		string $odometerUnit = 'km',
		?string $notes = null,
	): DataResponse {
		return new DataResponse(
			$this->carService->create($this->getUserId(), $name, $brand, $model, $plate, $year, $fuelType, $initialOdometer, $odometerUnit, $notes),
			Http::STATUS_CREATED,
		);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/cars/{id}', requirements: ['id' => '\d+'])]
	public function show(int $id): DataResponse {
		try {
			return new DataResponse($this->carDetailService->build($id, $this->getUserId()));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/cars/{id}', requirements: ['id' => '\d+'])]
	public function update(
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
				$initialOdometer,
				$odometerUnit,
				$notes,
				$notesProvided,
				$archived,
			));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/cars/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->carService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
