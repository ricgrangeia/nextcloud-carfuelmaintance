<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Controller;

use OCA\CarFuelMaintance\Service\MaintenanceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class MaintenanceController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private MaintenanceService $maintenanceService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/cars/{carId}/maintenance', requirements: ['carId' => '\d+'])]
	public function index(int $carId): DataResponse {
		try {
			return new DataResponse($this->maintenanceService->findAll($carId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/cars/{carId}/maintenance', requirements: ['carId' => '\d+'])]
	public function create(
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
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/maintenance/{id}', requirements: ['id' => '\d+'])]
	public function update(
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
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/maintenance/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->maintenanceService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
