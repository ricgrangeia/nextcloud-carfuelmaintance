<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Tests\Unit\Service;

use OCA\CarFuelMaintance\Db\Car;
use OCA\CarFuelMaintance\Db\FuelEntry;
use OCA\CarFuelMaintance\Db\MaintenanceEntry;
use OCA\CarFuelMaintance\Service\StatsService;
use PHPUnit\Framework\TestCase;

class StatsServiceTest extends TestCase {
	private StatsService $stats;

	protected function setUp(): void {
		parent::setUp();
		$this->stats = new StatsService();
	}

	private function car(float $initialOdometer = 0.0): Car {
		$car = new Car();
		$car->setInitialOdometer($initialOdometer);
		$car->setOdometerUnit('km');
		return $car;
	}

	private function fuel(float $odometer, float $quantity, bool $fullTank, ?float $totalCost = null, string $unit = 'L'): FuelEntry {
		$entry = new FuelEntry();
		$entry->setEntryDate(new \DateTimeImmutable('2026-01-01'));
		$entry->setOdometer($odometer);
		$entry->setQuantity($quantity);
		$entry->setUnit($unit);
		$entry->setFullTank($fullTank);
		$entry->setTotalCost($totalCost);
		return $entry;
	}

	public function testConsumptionBetweenTwoFullTanks(): void {
		// 500km covered on 40L -> 8 L/100km, 12.5 km/L.
		$entries = [
			$this->fuel(1000, 40.0, true),
			$this->fuel(1500, 40.0, true),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));

		self::assertSame(8.0, $result['avgConsumptionPer100']);
		self::assertSame(12.5, $result['avgDistancePerUnit']);
	}

	public function testPartialFillsAreAddedToTheClosingFullTank(): void {
		// full at 1000 (40L), partial +10L, full again at 1500 (30L) -> 40L used over 500km.
		$entries = [
			$this->fuel(1000, 40.0, true),
			$this->fuel(1250, 10.0, false),
			$this->fuel(1500, 30.0, true),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));

		self::assertSame(8.0, $result['avgConsumptionPer100']);
	}

	public function testNoConsumptionWithoutTwoFullTanks(): void {
		$entries = [$this->fuel(1000, 40.0, true)];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));

		self::assertNull($result['avgConsumptionPer100']);
		self::assertNull($result['avgDistancePerUnit']);
	}

	public function testTotalsAndCostPerDistance(): void {
		$entries = [
			$this->fuel(1000, 40.0, true, 60.0),
			$this->fuel(1500, 40.0, true, 60.0),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));

		self::assertSame(120.0, $result['totalFuelCost']);
		self::assertSame(500.0, $result['totalDistance']);
		self::assertEqualsWithDelta(0.24, $result['costPerDistance'], 0.001);
	}

	public function testTotalDistanceIgnoresUnsetInitialOdometer(): void {
		// A car left at the default initialOdometer=0 with high-mileage fuel
		// entries must report the distance covered *between fill-ups*, not
		// currentOdometer - 0 (which would wrongly equal currentOdometer).
		$entries = [
			$this->fuel(206311, 40.0, true),
			$this->fuel(206811, 40.0, true),
		];

		$result = $this->stats->computeCarStats($this->car(0), $entries, [], new \DateTimeImmutable('2026-01-15'));

		self::assertSame(206811.0, $result['currentOdometer']);
		self::assertSame(500.0, $result['totalDistance']);
	}

	public function testReminderStatusOverdueDueSoonAndUpcoming(): void {
		$today = new \DateTimeImmutable('2026-06-01');

		$overdue = new MaintenanceEntry();
		$overdue->setEntryDate($today);
		$overdue->setType('oil_change');
		$overdue->setNextDueDate(new \DateTimeImmutable('2026-05-01'));

		$dueSoon = new MaintenanceEntry();
		$dueSoon->setEntryDate($today);
		$dueSoon->setType('tires');
		$dueSoon->setNextDueDate(new \DateTimeImmutable('2026-06-10'));

		$upcoming = new MaintenanceEntry();
		$upcoming->setEntryDate($today);
		$upcoming->setType('inspection');
		$upcoming->setNextDueDate(new \DateTimeImmutable('2027-01-01'));

		$result = $this->stats->computeCarStats($this->car(), [], [$overdue, $dueSoon, $upcoming], $today);

		self::assertCount(3, $result['reminders']);
		self::assertSame('overdue', $result['reminders'][0]['status']);
		self::assertSame('due_soon', $result['reminders'][1]['status']);
		self::assertSame('upcoming', $result['reminders'][2]['status']);
	}

	public function testEntriesWithoutDueDateOrOdometerAreNotReminders(): void {
		$entry = new MaintenanceEntry();
		$entry->setEntryDate(new \DateTimeImmutable('2026-01-01'));
		$entry->setType('other');

		$result = $this->stats->computeCarStats($this->car(), [], [$entry], new \DateTimeImmutable('2026-01-15'));

		self::assertCount(0, $result['reminders']);
	}
}
