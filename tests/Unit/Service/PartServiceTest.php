<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Tests\Unit\Service;

use OCA\CarFuelMaintance\Db\Car;
use OCA\CarFuelMaintance\Db\CarMapper;
use OCA\CarFuelMaintance\Db\PartItem;
use OCA\CarFuelMaintance\Db\PartItemMapper;
use OCA\CarFuelMaintance\Service\PartService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PartServiceTest extends TestCase {
	private PartItemMapper&MockObject $partItemMapper;
	private CarMapper&MockObject $carMapper;
	private PartService $parts;

	protected function setUp(): void {
		parent::setUp();
		$this->partItemMapper = $this->createMock(PartItemMapper::class);
		$this->carMapper = $this->createMock(CarMapper::class);
		$rootFolder = $this->createMock(IRootFolder::class);
		$this->parts = new PartService($this->partItemMapper, $this->carMapper, $rootFolder);
	}

	public function testCreateDefaultsToNewConditionAndQuantityOne(): void {
		$this->partItemMapper->method('insert')->willReturnCallback(static fn (PartItem $p) => $p);

		$part = $this->parts->create('alice', 'Brake pads');

		self::assertSame('new', $part->getCondition());
		self::assertSame(1, $part->getQuantity());
		self::assertNull($part->getCarId());
	}

	public function testCreateNormalizesUnknownConditionToNew(): void {
		$this->partItemMapper->method('insert')->willReturnCallback(static fn (PartItem $p) => $p);

		$part = $this->parts->create('alice', 'Alternator', null, null, 'refurbished');

		self::assertSame('new', $part->getCondition());
	}

	public function testCreateAcceptsUsedCondition(): void {
		$this->partItemMapper->method('insert')->willReturnCallback(static fn (PartItem $p) => $p);

		$part = $this->parts->create('alice', 'Alternator', null, null, 'used');

		self::assertSame('used', $part->getCondition());
	}

	public function testCreateClampsQuantityToAtLeastOne(): void {
		$this->partItemMapper->method('insert')->willReturnCallback(static fn (PartItem $p) => $p);

		$part = $this->parts->create('alice', 'Bolts', null, null, 'new', null, null, 0);

		self::assertSame(1, $part->getQuantity());
	}

	public function testCreateRejectsCarNotOwnedByUser(): void {
		$this->carMapper->method('find')->willThrowException(new DoesNotExistException('not found'));

		$this->expectException(DoesNotExistException::class);
		$this->parts->create('alice', 'Brake pads', 42);
	}

	public function testCreateAcceptsCarOwnedByUser(): void {
		$this->carMapper->method('find')->willReturn(new Car());
		$this->partItemMapper->method('insert')->willReturnCallback(static fn (PartItem $p) => $p);

		$part = $this->parts->create('alice', 'Brake pads', 42);

		self::assertSame(42, $part->getCarId());
	}

	public function testUpdateOnlyChangesProvidedFields(): void {
		$existing = new PartItem();
		$existing->setName('Old name');
		$existing->setLocation('Shelf 1');
		$this->partItemMapper->method('find')->willReturn($existing);
		$this->partItemMapper->method('update')->willReturnCallback(static fn (PartItem $p) => $p);

		$updated = $this->parts->update(1, 'alice', 'New name');

		self::assertSame('New name', $updated->getName());
		self::assertSame('Shelf 1', $updated->getLocation());
	}

	public function testUpdateClearsLocationWhenProvidedAsNull(): void {
		$existing = new PartItem();
		$existing->setLocation('Shelf 1');
		$this->partItemMapper->method('find')->willReturn($existing);
		$this->partItemMapper->method('update')->willReturnCallback(static fn (PartItem $p) => $p);

		$updated = $this->parts->update(1, 'alice', null, null, false, null, false, null, null, false, null, true);

		self::assertNull($updated->getLocation());
	}
}
