<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\Db\CarMapper;
use OCA\CarFuelMaintance\Db\MaintenanceEntry;
use OCA\CarFuelMaintance\Db\MaintenanceEntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class MaintenanceService {
	public function __construct(
		private MaintenanceEntryMapper $maintenanceEntryMapper,
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
	private function assertEntryOwned(int $id, string $userId): MaintenanceEntry {
		$entry = $this->maintenanceEntryMapper->find($id);
		$this->assertCarOwned($entry->getCarId(), $userId);
		return $entry;
	}

	/**
	 * @return MaintenanceEntry[]
	 * @throws DoesNotExistException|MultipleObjectsReturnedException
	 */
	public function findAll(int $carId, string $userId): array {
		$this->assertCarOwned($carId, $userId);
		return $this->maintenanceEntryMapper->findAllForCar($carId);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function create(
		int $carId,
		string $userId,
		\DateTimeImmutable $entryDate,
		string $type,
		?float $odometer = null,
		?string $description = null,
		?float $cost = null,
		?string $workshop = null,
		?\DateTimeImmutable $nextDueDate = null,
		?float $nextDueOdometer = null,
		?string $notes = null,
		int $sortOrder = 0,
	): MaintenanceEntry {
		$this->assertCarOwned($carId, $userId);

		$entry = new MaintenanceEntry();
		$entry->setCarId($carId);
		$entry->setEntryDate($entryDate);
		$entry->setType($type !== '' ? $type : 'other');
		$entry->setOdometer($odometer);
		$entry->setDescription($description);
		$entry->setCost($cost);
		$entry->setWorkshop($workshop);
		$entry->setNextDueDate($nextDueDate);
		$entry->setNextDueOdometer($nextDueOdometer);
		$entry->setNotes($notes);
		$entry->setSortOrder($sortOrder);
		return $this->maintenanceEntryMapper->insert($entry);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function update(
		int $id,
		string $userId,
		?\DateTimeImmutable $entryDate = null,
		?string $type = null,
		?float $odometer = null,
		bool $odometerProvided = false,
		?string $description = null,
		bool $descriptionProvided = false,
		?float $cost = null,
		bool $costProvided = false,
		?string $workshop = null,
		bool $workshopProvided = false,
		?\DateTimeImmutable $nextDueDate = null,
		bool $nextDueDateProvided = false,
		?float $nextDueOdometer = null,
		bool $nextDueOdometerProvided = false,
		?string $notes = null,
		bool $notesProvided = false,
		?int $sortOrder = null,
	): MaintenanceEntry {
		$entry = $this->assertEntryOwned($id, $userId);

		if ($entryDate !== null) {
			$entry->setEntryDate($entryDate);
		}
		if ($type !== null) {
			$entry->setType($type);
		}
		if ($odometerProvided) {
			$entry->setOdometer($odometer);
		}
		if ($descriptionProvided) {
			$entry->setDescription($description);
		}
		if ($costProvided) {
			$entry->setCost($cost);
		}
		if ($workshopProvided) {
			$entry->setWorkshop($workshop);
		}
		if ($nextDueDateProvided) {
			$entry->setNextDueDate($nextDueDate);
		}
		if ($nextDueOdometerProvided) {
			$entry->setNextDueOdometer($nextDueOdometer);
		}
		if ($notesProvided) {
			$entry->setNotes($notes);
		}
		if ($sortOrder !== null) {
			$entry->setSortOrder($sortOrder);
		}

		return $this->maintenanceEntryMapper->update($entry);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function delete(int $id, string $userId): void {
		$entry = $this->assertEntryOwned($id, $userId);
		$this->maintenanceEntryMapper->delete($entry);
	}
}
