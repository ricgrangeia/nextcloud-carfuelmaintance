<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\Db\Car;
use OCA\CarFuelMaintance\Db\CarMapper;
use OCA\CarFuelMaintance\Db\FuelEntryMapper;
use OCA\CarFuelMaintance\Db\MaintenanceEntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class CarService {
	public function __construct(
		private CarMapper $carMapper,
		private FuelEntryMapper $fuelEntryMapper,
		private MaintenanceEntryMapper $maintenanceEntryMapper,
		private PartService $partService,
	) {
	}

	/** @return Car[] */
	public function findAll(string $userId, bool $includeArchived = false): array {
		return $this->carMapper->findAllForUser($userId, $includeArchived);
	}

	/**
	 * @throws DoesNotExistException if the car does not exist or is not owned by $userId
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id, string $userId): Car {
		return $this->carMapper->find($id, $userId);
	}

	public function create(
		string $userId,
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
	): Car {
		$car = new Car();
		$car->setUserId($userId);
		$car->setName($name);
		$car->setBrand($brand);
		$car->setModel($model);
		$car->setPlate($plate);
		$car->setYear($year);
		$car->setFuelType($fuelType !== '' ? $fuelType : 'gasoline');
		$car->setSecondaryFuelType($secondaryFuelType !== '' ? $secondaryFuelType : null);
		$car->setInitialOdometer($initialOdometer);
		$car->setOdometerUnit($odometerUnit !== '' ? $odometerUnit : 'km');
		$car->setNotes($notes);
		$car->setCreatedAt(new \DateTimeImmutable());
		return $this->carMapper->insert($car);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function update(
		int $id,
		string $userId,
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
	): Car {
		$car = $this->find($id, $userId);
		if ($name !== null) {
			$car->setName($name);
		}
		if ($brandProvided) {
			$car->setBrand($brand);
		}
		if ($modelProvided) {
			$car->setModel($model);
		}
		if ($plateProvided) {
			$car->setPlate($plate);
		}
		if ($yearProvided) {
			$car->setYear($year);
		}
		if ($fuelType !== null) {
			$car->setFuelType($fuelType);
		}
		if ($secondaryFuelTypeProvided) {
			$car->setSecondaryFuelType($secondaryFuelType !== '' ? $secondaryFuelType : null);
		}
		if ($initialOdometer !== null) {
			$car->setInitialOdometer($initialOdometer);
		}
		if ($odometerUnit !== null) {
			$car->setOdometerUnit($odometerUnit);
		}
		if ($notesProvided) {
			$car->setNotes($notes);
		}
		if ($archived !== null) {
			$car->setArchived($archived);
		}
		return $this->carMapper->update($car);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function delete(int $id, string $userId): void {
		$car = $this->find($id, $userId);
		$this->fuelEntryMapper->deleteAllForCar($id);
		$this->maintenanceEntryMapper->deleteAllForCar($id);
		$this->partService->deleteAllForCar($id, $userId);
		$this->carMapper->delete($car);
	}
}
