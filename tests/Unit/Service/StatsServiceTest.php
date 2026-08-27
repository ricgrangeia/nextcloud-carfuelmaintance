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

	private function fuel(float $odometer, float $quantity, bool $fullTank, ?float $totalCost = null, string $unit = 'L', string $fuelType = 'gasoline', ?float $pricePerUnit = null): FuelEntry {
		$entry = new FuelEntry();
		$entry->setEntryDate(new \DateTimeImmutable('2026-01-01'));
		$entry->setOdometer($odometer);
		$entry->setQuantity($quantity);
		$entry->setUnit($unit);
		$entry->setFullTank($fullTank);
		$entry->setTotalCost($totalCost);
		$entry->setFuelType($fuelType);
		$entry->setPricePerUnit($pricePerUnit);
		return $entry;
	}

	private function fuelTypeStats(array $result, string $fuelType): ?array {
		foreach ($result['consumptionByFuelType'] as $group) {
			if ($group['fuelType'] === $fuelType) {
				return $group;
			}
		}
		return null;
	}

	public function testConsumptionBetweenTwoFullTanks(): void {
		// 500km covered on 40L -> 8 L/100km, 12.5 km/L.
		$entries = [
			$this->fuel(1000, 40.0, true),
			$this->fuel(1500, 40.0, true),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));
		$gasoline = $this->fuelTypeStats($result, 'gasoline');

		self::assertSame(8.0, $gasoline['avgConsumptionPer100']);
		self::assertSame(12.5, $gasoline['avgDistancePerUnit']);
	}

	public function testPartialFillsAreAddedToTheClosingFullTank(): void {
		// full at 1000 (40L), partial +10L, full again at 1500 (30L) -> 40L used over 500km.
		$entries = [
			$this->fuel(1000, 40.0, true),
			$this->fuel(1250, 10.0, false),
			$this->fuel(1500, 30.0, true),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));

		self::assertSame(8.0, $this->fuelTypeStats($result, 'gasoline')['avgConsumptionPer100']);
	}

	public function testConsumptionHistoryHasOneEntryPerFullTankInterval(): void {
		$entries = [
			$this->fuel(1000, 40.0, true),
			$this->fuel(1500, 40.0, true),
			$this->fuel(2100, 48.0, true),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));
		$history = $this->fuelTypeStats($result, 'gasoline')['history'];

		self::assertCount(2, $history);
		self::assertSame('2026-01-01', $history[0]['date']);
		self::assertSame(500.0, $history[0]['distance']);
		self::assertSame(8.0, $history[0]['consumptionPer100']);
		self::assertSame(600.0, $history[1]['distance']);
		self::assertSame(8.0, $history[1]['consumptionPer100']);
	}

	public function testNoConsumptionWithoutTwoFullTanks(): void {
		$entries = [$this->fuel(1000, 40.0, true)];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));
		$gasoline = $this->fuelTypeStats($result, 'gasoline');

		self::assertNull($gasoline['avgConsumptionPer100']);
		self::assertNull($gasoline['avgDistancePerUnit']);
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

	public function testCostAtLastPriceUsesMostRecentPriceNotHistoricalAverage(): void {
		// Prices rise over time: 1.50 -> 1.60 -> 1.80/L. "At last price" must
		// use 1.80 (the most recent), not an average of the three.
		$entries = [
			$this->fuel(1000, 40.0, true, null, 'L', 'gasoline', 1.50),
			$this->fuel(1500, 40.0, true, null, 'L', 'gasoline', 1.60),
			$this->fuel(2000, 40.0, true, null, 'L', 'gasoline', 1.80),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));
		$gasoline = $this->fuelTypeStats($result, 'gasoline');

		// 8 L/100km at 1.80 €/L -> 14.40 €/100km.
		self::assertSame(1.80, $gasoline['lastPricePerUnit']);
		self::assertSame(14.40, $gasoline['costPer100AtLastPrice']);
		// 12.5 km/L -> 1.80 / 12.5 = 0.144 €/km.
		self::assertEqualsWithDelta(0.144, $gasoline['costPerDistanceAtLastPrice'], 0.001);
	}

	public function testCostAtLastPriceSkipsEntriesWithoutAPrice(): void {
		$entries = [
			$this->fuel(1000, 40.0, true, null, 'L', 'gasoline', 1.50),
			$this->fuel(1500, 40.0, true, null, 'L', 'gasoline', null),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));
		$gasoline = $this->fuelTypeStats($result, 'gasoline');

		self::assertSame(1.50, $gasoline['lastPricePerUnit']);
	}

	public function testNoCostAtLastPriceWithoutAnyPrice(): void {
		$entries = [
			$this->fuel(1000, 40.0, true),
			$this->fuel(1500, 40.0, true),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));
		$gasoline = $this->fuelTypeStats($result, 'gasoline');

		self::assertNull($gasoline['lastPricePerUnit']);
		self::assertNull($gasoline['costPer100AtLastPrice']);
		self::assertNull($gasoline['costPerDistanceAtLastPrice']);
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

	public function testBifuelConsumptionIsComputedSeparatelyPerFuelType(): void {
		// A gasoline/LPG car: LPG (small tank) is always filled to full, so its
		// consumption is computable. Gasoline is topped up occasionally and
		// never filled fully, so it must report "not enough data" rather than
		// being mixed into the LPG numbers or silently ignored.
		$entries = [
			$this->fuel(1000, 35.0, true, null, 'L', 'lpg'),
			$this->fuel(1100, 15.0, false, null, 'L', 'gasoline'),
			$this->fuel(1300, 36.0, true, null, 'L', 'lpg'),
			$this->fuel(1450, 10.0, false, null, 'L', 'gasoline'),
			$this->fuel(1600, 34.0, true, null, 'L', 'lpg'),
		];

		$result = $this->stats->computeCarStats($this->car(1000), $entries, [], new \DateTimeImmutable('2026-01-15'));

		$lpg = $this->fuelTypeStats($result, 'lpg');
		$gasoline = $this->fuelTypeStats($result, 'gasoline');

		self::assertNotNull($lpg);
		self::assertNotNull($gasoline);

		// LPG: 300km/36L then 300km/34L.
		self::assertCount(2, $lpg['history']);
		self::assertEqualsWithDelta(12.0, $lpg['history'][0]['consumptionPer100'], 0.01);
		self::assertEqualsWithDelta(11.33, $lpg['history'][1]['consumptionPer100'], 0.01);

		// Gasoline never hit a full tank, so no interval could be closed.
		self::assertCount(0, $gasoline['history']);
		self::assertNull($gasoline['avgConsumptionPer100']);

		// entryCount orders the groups, most-logged fuel type first.
		self::assertSame('lpg', $result['consumptionByFuelType'][0]['fuelType']);
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
