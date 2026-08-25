<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\Db\Car;
use OCA\CarFuelMaintance\Db\FuelEntry;
use OCA\CarFuelMaintance\Db\MaintenanceEntry;

/**
 * Computes fuel consumption, spending and maintenance-reminder stats for a
 * car. Nothing here is persisted — it's derived at request time from the
 * car's fuel and maintenance entries.
 */
class StatsService {
	private const DUE_SOON_DAYS = 30;
	private const DUE_SOON_DISTANCE = 500.0;

	/**
	 * @param FuelEntry[] $fuelEntries sorted by entryDate ascending
	 * @param MaintenanceEntry[] $maintenanceEntries
	 */
	public function computeCarStats(Car $car, array $fuelEntries, array $maintenanceEntries, \DateTimeImmutable $today): array {
		$byOdometer = $fuelEntries;
		usort($byOdometer, static fn (FuelEntry $a, FuelEntry $b) => $a->getOdometer() <=> $b->getOdometer());

		$totalFuelCost = 0.0;
		foreach ($fuelEntries as $entry) {
			$totalFuelCost += $entry->getTotalCost() ?? 0.0;
		}

		$totalMaintenanceCost = 0.0;
		foreach ($maintenanceEntries as $entry) {
			$totalMaintenanceCost += $entry->getCost() ?? 0.0;
		}

		$maxFuelOdometer = 0.0;
		foreach ($byOdometer as $entry) {
			$maxFuelOdometer = max($maxFuelOdometer, $entry->getOdometer());
		}
		$maxMaintenanceOdometer = 0.0;
		foreach ($maintenanceEntries as $entry) {
			$maxMaintenanceOdometer = max($maxMaintenanceOdometer, $entry->getOdometer() ?? 0.0);
		}

		$currentOdometer = max($car->getInitialOdometer(), $maxFuelOdometer, $maxMaintenanceOdometer);
		$totalDistance = max(0.0, $currentOdometer - $car->getInitialOdometer());

		[$fuelUsedBetweenFulls, $distanceBetweenFulls, $fuelUnit] = $this->consumptionBetweenFullTanks($byOdometer);

		$avgConsumptionPer100 = ($distanceBetweenFulls > 0 && $fuelUsedBetweenFulls > 0)
			? round($fuelUsedBetweenFulls / $distanceBetweenFulls * 100, 2)
			: null;
		$avgDistancePerUnit = ($fuelUsedBetweenFulls > 0)
			? round($distanceBetweenFulls / $fuelUsedBetweenFulls, 2)
			: null;

		return [
			'currentOdometer' => $currentOdometer,
			'odometerUnit' => $car->getOdometerUnit(),
			'totalDistance' => round($totalDistance, 1),
			'fuelEntriesCount' => count($fuelEntries),
			'maintenanceEntriesCount' => count($maintenanceEntries),
			'totalFuelCost' => round($totalFuelCost, 2),
			'totalMaintenanceCost' => round($totalMaintenanceCost, 2),
			'totalCost' => round($totalFuelCost + $totalMaintenanceCost, 2),
			'costPerDistance' => $totalDistance > 0 ? round(($totalFuelCost + $totalMaintenanceCost) / $totalDistance, 3) : null,
			'fuelUnit' => $fuelUnit,
			'avgConsumptionPer100' => $avgConsumptionPer100,
			'avgDistancePerUnit' => $avgDistancePerUnit,
			'reminders' => $this->buildReminders($maintenanceEntries, $currentOdometer, $today),
		];
	}

	/**
	 * Standard fill-to-fill consumption: between two consecutive full-tank
	 * entries (sorted by odometer), the fuel used to cover that distance is
	 * the sum of every fill-up quantity in the interval (partial fills
	 * included), ending with the closing full-tank fill-up.
	 *
	 * @param FuelEntry[] $sortedByOdometer
	 * @return array{0: float, 1: float, 2: string}
	 */
	private function consumptionBetweenFullTanks(array $sortedByOdometer): array {
		$totalFuel = 0.0;
		$totalDistance = 0.0;
		$unit = 'L';

		$previousFullOdometer = null;
		$quantitySinceLastFull = 0.0;

		foreach ($sortedByOdometer as $entry) {
			$unit = $entry->getUnit();
			$quantitySinceLastFull += $entry->getQuantity();

			if (!$entry->getFullTank()) {
				continue;
			}

			if ($previousFullOdometer !== null) {
				$distance = $entry->getOdometer() - $previousFullOdometer;
				if ($distance > 0) {
					$totalDistance += $distance;
					$totalFuel += $quantitySinceLastFull;
				}
			}

			$previousFullOdometer = $entry->getOdometer();
			$quantitySinceLastFull = 0.0;
		}

		return [$totalFuel, $totalDistance, $unit];
	}

	/**
	 * @param MaintenanceEntry[] $maintenanceEntries
	 */
	private function buildReminders(array $maintenanceEntries, float $currentOdometer, \DateTimeImmutable $today): array {
		$reminders = [];

		foreach ($maintenanceEntries as $entry) {
			$dueDate = $entry->getNextDueDate();
			$dueOdometer = $entry->getNextDueOdometer();
			if ($dueDate === null && $dueOdometer === null) {
				continue;
			}

			$daysRemaining = $dueDate !== null ? (int) $today->diff($dueDate)->format('%r%a') : null;
			$distanceRemaining = $dueOdometer !== null ? round($dueOdometer - $currentOdometer, 1) : null;

			$overdue = ($daysRemaining !== null && $daysRemaining < 0) || ($distanceRemaining !== null && $distanceRemaining < 0);
			$dueSoon = !$overdue && (
				($daysRemaining !== null && $daysRemaining <= self::DUE_SOON_DAYS)
				|| ($distanceRemaining !== null && $distanceRemaining <= self::DUE_SOON_DISTANCE)
			);
			$status = $overdue ? 'overdue' : ($dueSoon ? 'due_soon' : 'upcoming');

			$reminders[] = [
				'id' => $entry->getId(),
				'type' => $entry->getType(),
				'description' => $entry->getDescription(),
				'nextDueDate' => $dueDate?->format('Y-m-d'),
				'nextDueOdometer' => $dueOdometer,
				'daysRemaining' => $daysRemaining,
				'distanceRemaining' => $distanceRemaining,
				'status' => $status,
			];
		}

		usort($reminders, static function (array $a, array $b) {
			$rank = ['overdue' => 0, 'due_soon' => 1, 'upcoming' => 2];
			return $rank[$a['status']] <=> $rank[$b['status']];
		});

		return $reminders;
	}
}
