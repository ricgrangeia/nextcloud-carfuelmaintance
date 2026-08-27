<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\Db\FuelEntryMapper;
use OCA\CarFuelMaintance\Db\MaintenanceEntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

/** Assembles a car plus its fuel/maintenance entries and computed stats. */
class CarDetailService {
	public function __construct(
		private CarService $carService,
		private FuelEntryMapper $fuelEntryMapper,
		private MaintenanceEntryMapper $maintenanceEntryMapper,
		private StatsService $statsService,
		private SettingsService $settingsService,
	) {
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function build(int $carId, string $userId): array {
		$car = $this->carService->find($carId, $userId);
		$fuelEntries = $this->fuelEntryMapper->findAllForCar($carId);
		$maintenanceEntries = $this->maintenanceEntryMapper->findAllForCar($carId);
		$reminderMonths = $this->settingsService->getReminderMonths($userId);
		$currencySymbol = $this->settingsService->getCurrencySymbol($userId);
		$consumptionFormat = $this->settingsService->getConsumptionFormat($userId);
		$stats = $this->statsService->computeCarStats($car, $fuelEntries, $maintenanceEntries, new \DateTimeImmutable('today'), $reminderMonths, $currencySymbol, $consumptionFormat);

		return [
			'car' => $car,
			'fuelEntries' => $fuelEntries,
			'maintenanceEntries' => $maintenanceEntries,
			'stats' => $stats,
		];
	}
}
