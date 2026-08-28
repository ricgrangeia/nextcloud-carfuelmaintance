<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Controller;

use OCA\CarFuelMaintance\Service\TripService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class TripController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private TripService $tripService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	private function notFound(): DataResponse {
		return new DataResponse([], Http::STATUS_NOT_FOUND);
	}

	private function invalidDate(): DataResponse {
		return new DataResponse(['message' => 'Invalid date'], Http::STATUS_BAD_REQUEST);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/cars/{carId}/trips', requirements: ['carId' => '\d+'])]
	public function index(int $carId): DataResponse {
		try {
			return new DataResponse($this->tripService->findAll($carId, $this->getUserId()));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/cars/{carId}/trips', requirements: ['carId' => '\d+'])]
	public function create(
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
	#[FrontpageRoute(verb: 'PUT', url: '/api/trips/{id}', requirements: ['id' => '\d+'])]
	public function update(
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
	#[FrontpageRoute(verb: 'DELETE', url: '/api/trips/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->tripService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}
}
