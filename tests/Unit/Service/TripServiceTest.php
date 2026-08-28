<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Tests\Unit\Service;

use OCA\CarFuelMaintance\Db\Car;
use OCA\CarFuelMaintance\Db\CarMapper;
use OCA\CarFuelMaintance\Db\Trip;
use OCA\CarFuelMaintance\Db\TripMapper;
use OCA\CarFuelMaintance\Service\TripService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TripServiceTest extends TestCase {
	private TripMapper&MockObject $tripMapper;
	private CarMapper&MockObject $carMapper;
	private TripService $trips;

	protected function setUp(): void {
		parent::setUp();
		$this->tripMapper = $this->createMock(TripMapper::class);
		$this->carMapper = $this->createMock(CarMapper::class);
		$this->carMapper->method('find')->willReturn(new Car());
		$this->trips = new TripService($this->tripMapper, $this->carMapper);
	}

	public function testCreateDefaultsToBusinessPurpose(): void {
		$this->tripMapper->method('insert')->willReturnCallback(static fn (Trip $t) => $t);

		$trip = $this->trips->create(1, 'alice', new \DateTimeImmutable('2026-01-01'), 1000.0, 1100.0);

		self::assertSame('business', $trip->getPurpose());
	}

	public function testCreateNormalizesUnknownPurposeToOther(): void {
		$this->tripMapper->method('insert')->willReturnCallback(static fn (Trip $t) => $t);

		$trip = $this->trips->create(1, 'alice', new \DateTimeImmutable('2026-01-01'), 1000.0, 1100.0, 'commute');

		self::assertSame('other', $trip->getPurpose());
	}

	public function testCreateClampsEndOdometerToStart(): void {
		$this->tripMapper->method('insert')->willReturnCallback(static fn (Trip $t) => $t);

		$trip = $this->trips->create(1, 'alice', new \DateTimeImmutable('2026-01-01'), 1000.0, 900.0);

		self::assertSame(1000.0, $trip->getEndOdometer());
	}

	public function testDistanceIsComputedInJsonSerialize(): void {
		$trip = new Trip();
		$trip->setStartOdometer(1000.0);
		$trip->setEndOdometer(1120.5);

		self::assertSame(120.5, $trip->jsonSerialize()['distance']);
	}

	public function testUpdateClampsEndOdometerWhenLoweredBelowStart(): void {
		$existing = new Trip();
		$existing->setStartOdometer(1000.0);
		$existing->setEndOdometer(1100.0);
		$this->tripMapper->method('find')->willReturn($existing);
		$this->tripMapper->method('update')->willReturnCallback(static fn (Trip $t) => $t);

		$updated = $this->trips->update(1, 'alice', null, null, 900.0);

		self::assertSame(1000.0, $updated->getEndOdometer());
	}
}
