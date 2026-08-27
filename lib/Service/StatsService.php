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
	private const DUE_SOON_DISTANCE = 500.0;
	private const DAYS_PER_MONTH = 30;

	/**
	 * @param FuelEntry[] $fuelEntries sorted by entryDate ascending
	 * @param MaintenanceEntry[] $maintenanceEntries
	 * @param int $reminderMonths how many months ahead of a due date counts as "due soon"
	 * @param string $currencySymbol the user's configured currency symbol, echoed back for display
	 */
	public function computeCarStats(Car $car, array $fuelEntries, array $maintenanceEntries, \DateTimeImmutable $today, int $reminderMonths = 1, string $currencySymbol = '€'): array {
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
		// Distance actually covered by the fuel log: first to last fill-up
		// odometer reading, rather than relying on the car's starting odometer.
		$totalDistance = count($byOdometer) >= 2
			? max(0.0, end($byOdometer)->getOdometer() - $byOdometer[0]->getOdometer())
			: 0.0;

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
			// A bifuel car (e.g. gasoline/LPG) mixes two different substances in
			// the same odometer log, so consumption is computed and reported
			// separately per fuel type rather than as a single blended average.
			'consumptionByFuelType' => $this->consumptionByFuelType($byOdometer),
			'reminderMonths' => $reminderMonths,
			'currencySymbol' => $currencySymbol,
			'reminders' => $this->buildReminders($maintenanceEntries, $currentOdometer, $today, $reminderMonths),
		];
	}

	/**
	 * @param FuelEntry[] $sortedByOdometer all fuel entries, sorted by odometer ascending
	 * @return array<int, array{fuelType: string, unit: string, entryCount: int, avgConsumptionPer100: ?float, avgDistancePerUnit: ?float, history: array}>
	 */
	private function consumptionByFuelType(array $sortedByOdometer): array {
		$byType = [];
		foreach ($sortedByOdometer as $entry) {
			$byType[$entry->getFuelType()][] = $entry;
		}

		$result = [];
		foreach ($byType as $fuelType => $entries) {
			[$fuelUsed, $distance, $unit, $history] = $this->consumptionBetweenFullTanks($entries);

			$result[] = [
				'fuelType' => $fuelType,
				'unit' => $unit,
				'entryCount' => count($entries),
				'avgConsumptionPer100' => ($distance > 0 && $fuelUsed > 0) ? round($fuelUsed / $distance * 100, 2) : null,
				'avgDistancePerUnit' => $fuelUsed > 0 ? round($distance / $fuelUsed, 2) : null,
				'history' => $history,
			];
		}

		usort($result, static fn (array $a, array $b) => $b['entryCount'] <=> $a['entryCount']);

		return $result;
	}

	/**
	 * Standard fill-to-fill consumption: between two consecutive full-tank
	 * entries of the same fuel type (sorted by odometer), the fuel used to
	 * cover that distance is the sum of every fill-up quantity in the
	 * interval (partial fills included), ending with the closing full-tank
	 * fill-up. Also returns that same breakdown per interval, for charting
	 * consumption over time.
	 *
	 * @param FuelEntry[] $sortedByOdometer entries of a single fuel type, sorted by odometer ascending
	 * @return array{0: float, 1: float, 2: string, 3: array}
	 */
	private function consumptionBetweenFullTanks(array $sortedByOdometer): array {
		$totalFuel = 0.0;
		$totalDistance = 0.0;
		$unit = 'L';
		$history = [];

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
					$history[] = [
						'date' => $entry->getEntryDate()?->format('Y-m-d'),
						'distance' => round($distance, 1),
						'fuelUsed' => round($quantitySinceLastFull, 3),
						'unit' => $unit,
						'consumptionPer100' => round($quantitySinceLastFull / $distance * 100, 2),
						'distancePerUnit' => round($distance / $quantitySinceLastFull, 2),
					];
				}
			}

			$previousFullOdometer = $entry->getOdometer();
			$quantitySinceLastFull = 0.0;
		}

		return [$totalFuel, $totalDistance, $unit, $history];
	}

	/**
	 * @param MaintenanceEntry[] $maintenanceEntries
	 */
	private function buildReminders(array $maintenanceEntries, float $currentOdometer, \DateTimeImmutable $today, int $reminderMonths): array {
		$reminders = [];
		$dueSoonDays = $reminderMonths * self::DAYS_PER_MONTH;

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
				($daysRemaining !== null && $daysRemaining <= $dueSoonDays)
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
