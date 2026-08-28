<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\Db\CarMapper;
use OCA\CarFuelMaintance\Db\Trip;
use OCA\CarFuelMaintance\Db\TripMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class TripService {
	private const VALID_PURPOSES = ['business', 'personal', 'other'];

	public function __construct(
		private TripMapper $tripMapper,
		private CarMapper $carMapper,
	) {
	}

	/**
	 * @throws DoesNotExistException if the car does not exist or is not owned by $userId
	 * @throws MultipleObjectsReturnedException
	 */
	private function assertCarOwned(int $carId, string $userId): void {
		$this->carMapper->find($carId, $userId);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	private function assertTripOwned(int $id, string $userId): Trip {
		$trip = $this->tripMapper->find($id);
		$this->assertCarOwned($trip->getCarId(), $userId);
		return $trip;
	}

	private function normalizePurpose(string $purpose): string {
		return in_array($purpose, self::VALID_PURPOSES, true) ? $purpose : 'other';
	}

	/**
	 * @return Trip[]
	 * @throws DoesNotExistException|MultipleObjectsReturnedException
	 */
	public function findAll(int $carId, string $userId): array {
		$this->assertCarOwned($carId, $userId);
		return $this->tripMapper->findAllForCar($carId);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function create(
		int $carId,
		string $userId,
		\DateTimeImmutable $tripDate,
		float $startOdometer,
		float $endOdometer,
		string $purpose = 'business',
		?string $origin = null,
		?string $destination = null,
		?float $tolls = null,
		?float $otherCosts = null,
		?string $notes = null,
		int $sortOrder = 0,
	): Trip {
		$this->assertCarOwned($carId, $userId);

		$trip = new Trip();
		$trip->setCarId($carId);
		$trip->setTripDate($tripDate);
		$trip->setPurpose($this->normalizePurpose($purpose));
		$trip->setOrigin($origin);
		$trip->setDestination($destination);
		$trip->setStartOdometer($startOdometer);
		$trip->setEndOdometer(max($endOdometer, $startOdometer));
		$trip->setTolls($tolls);
		$trip->setOtherCosts($otherCosts);
		$trip->setNotes($notes);
		$trip->setSortOrder($sortOrder);
		return $this->tripMapper->insert($trip);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function update(
		int $id,
		string $userId,
		?\DateTimeImmutable $tripDate = null,
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
	): Trip {
		$trip = $this->assertTripOwned($id, $userId);

		if ($tripDate !== null) {
			$trip->setTripDate($tripDate);
		}
		if ($purpose !== null) {
			$trip->setPurpose($this->normalizePurpose($purpose));
		}
		if ($originProvided) {
			$trip->setOrigin($origin);
		}
		if ($destinationProvided) {
			$trip->setDestination($destination);
		}
		if ($startOdometer !== null) {
			$trip->setStartOdometer($startOdometer);
		}
		if ($endOdometer !== null) {
			$trip->setEndOdometer($endOdometer);
		}
		if ($trip->getEndOdometer() < $trip->getStartOdometer()) {
			$trip->setEndOdometer($trip->getStartOdometer());
		}
		if ($tollsProvided) {
			$trip->setTolls($tolls);
		}
		if ($otherCostsProvided) {
			$trip->setOtherCosts($otherCosts);
		}
		if ($notesProvided) {
			$trip->setNotes($notes);
		}
		if ($sortOrder !== null) {
			$trip->setSortOrder($sortOrder);
		}

		return $this->tripMapper->update($trip);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function delete(int $id, string $userId): void {
		$trip = $this->assertTripOwned($id, $userId);
		$this->tripMapper->delete($trip);
	}
}
