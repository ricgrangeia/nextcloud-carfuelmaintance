<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\Db\CarMapper;
use OCA\CarFuelMaintance\Db\FuelEntry;
use OCA\CarFuelMaintance\Db\FuelEntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class FuelService {
	public function __construct(
		private FuelEntryMapper $fuelEntryMapper,
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
	private function assertEntryOwned(int $id, string $userId): FuelEntry {
		$entry = $this->fuelEntryMapper->find($id);
		$this->assertCarOwned($entry->getCarId(), $userId);
		return $entry;
	}

	private function resolveCost(float $quantity, ?float $pricePerUnit, ?float $totalCost): array {
		if ($totalCost === null && $pricePerUnit !== null) {
			$totalCost = round($pricePerUnit * $quantity, 2);
		} elseif ($pricePerUnit === null && $totalCost !== null && $quantity > 0) {
			$pricePerUnit = round($totalCost / $quantity, 4);
		}
		return [$pricePerUnit, $totalCost];
	}

	/**
	 * @return FuelEntry[]
	 * @throws DoesNotExistException|MultipleObjectsReturnedException
	 */
	public function findAll(int $carId, string $userId): array {
		$this->assertCarOwned($carId, $userId);
		return $this->fuelEntryMapper->findAllForCar($carId);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function create(
		int $carId,
		string $userId,
		\DateTimeImmutable $entryDate,
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
	): FuelEntry {
		$this->assertCarOwned($carId, $userId);
		[$pricePerUnit, $totalCost] = $this->resolveCost($quantity, $pricePerUnit, $totalCost);

		$entry = new FuelEntry();
		$entry->setCarId($carId);
		$entry->setEntryDate($entryDate);
		$entry->setFuelType($fuelType !== '' ? $fuelType : 'gasoline');
		$entry->setOdometer($odometer);
		$entry->setQuantity($quantity);
		$entry->setUnit($unit !== '' ? $unit : 'L');
		$entry->setPricePerUnit($pricePerUnit);
		$entry->setTotalCost($totalCost);
		$entry->setFullTank($fullTank);
		$entry->setStation($station);
		$entry->setNotes($notes);
		$entry->setSortOrder($sortOrder);
		return $this->fuelEntryMapper->insert($entry);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function update(
		int $id,
		string $userId,
		?\DateTimeImmutable $entryDate = null,
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
	): FuelEntry {
		$entry = $this->assertEntryOwned($id, $userId);

		if ($entryDate !== null) {
			$entry->setEntryDate($entryDate);
		}
		if ($fuelType !== null) {
			$entry->setFuelType($fuelType);
		}
		if ($odometer !== null) {
			$entry->setOdometer($odometer);
		}
		if ($quantity !== null) {
			$entry->setQuantity($quantity);
		}
		if ($unit !== null) {
			$entry->setUnit($unit);
		}
		if ($pricePerUnitProvided) {
			$entry->setPricePerUnit($pricePerUnit);
		}
		if ($totalCostProvided) {
			$entry->setTotalCost($totalCost);
		}
		if (($pricePerUnitProvided || $totalCostProvided || $quantity !== null) && !($pricePerUnitProvided && $totalCostProvided)) {
			[$resolvedPrice, $resolvedTotal] = $this->resolveCost($entry->getQuantity(), $entry->getPricePerUnit(), $entry->getTotalCost());
			$entry->setPricePerUnit($resolvedPrice);
			$entry->setTotalCost($resolvedTotal);
		}
		if ($fullTank !== null) {
			$entry->setFullTank($fullTank);
		}
		if ($stationProvided) {
			$entry->setStation($station);
		}
		if ($notesProvided) {
			$entry->setNotes($notes);
		}
		if ($sortOrder !== null) {
			$entry->setSortOrder($sortOrder);
		}

		return $this->fuelEntryMapper->update($entry);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function delete(int $id, string $userId): void {
		$entry = $this->assertEntryOwned($id, $userId);
		$this->fuelEntryMapper->delete($entry);
	}
}
