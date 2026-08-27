<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getCarId()
 * @method void setCarId(int $carId)
 * @method ?\DateTimeImmutable getEntryDate()
 * @method void setEntryDate(?\DateTimeImmutable $entryDate)
 * @method string getFuelType()
 * @method void setFuelType(string $fuelType)
 * @method float getOdometer()
 * @method void setOdometer(float $odometer)
 * @method float getQuantity()
 * @method void setQuantity(float $quantity)
 * @method string getUnit()
 * @method void setUnit(string $unit)
 * @method ?float getPricePerUnit()
 * @method void setPricePerUnit(?float $pricePerUnit)
 * @method ?float getTotalCost()
 * @method void setTotalCost(?float $totalCost)
 * @method bool getFullTank()
 * @method void setFullTank(bool $fullTank)
 * @method ?string getStation()
 * @method void setStation(?string $station)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class FuelEntry extends Entity implements \JsonSerializable {
	protected int $carId = 0;
	protected ?\DateTimeImmutable $entryDate = null;
	protected string $fuelType = 'gasoline';
	protected float $odometer = 0.0;
	protected float $quantity = 0.0;
	protected string $unit = 'L';
	protected ?float $pricePerUnit = null;
	protected ?float $totalCost = null;
	protected bool $fullTank = true;
	protected ?string $station = null;
	protected ?string $notes = null;
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('carId', Types::INTEGER);
		$this->addType('entryDate', Types::DATE_IMMUTABLE);
		$this->addType('fuelType', Types::STRING);
		$this->addType('odometer', Types::FLOAT);
		$this->addType('quantity', Types::FLOAT);
		$this->addType('unit', Types::STRING);
		$this->addType('pricePerUnit', Types::FLOAT);
		$this->addType('totalCost', Types::FLOAT);
		$this->addType('fullTank', Types::BOOLEAN);
		$this->addType('station', Types::STRING);
		$this->addType('notes', Types::TEXT);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'carId' => $this->carId,
			'entryDate' => $this->entryDate?->format('Y-m-d'),
			'fuelType' => $this->fuelType,
			'odometer' => $this->odometer,
			'quantity' => $this->quantity,
			'unit' => $this->unit,
			'pricePerUnit' => $this->pricePerUnit,
			'totalCost' => $this->totalCost,
			'fullTank' => $this->fullTank,
			'station' => $this->station,
			'notes' => $this->notes,
			'sortOrder' => $this->sortOrder,
		];
	}
}
