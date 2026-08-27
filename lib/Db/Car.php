<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method ?string getBrand()
 * @method void setBrand(?string $brand)
 * @method ?string getModel()
 * @method void setModel(?string $model)
 * @method ?string getPlate()
 * @method void setPlate(?string $plate)
 * @method ?int getYear()
 * @method void setYear(?int $year)
 * @method string getFuelType()
 * @method void setFuelType(string $fuelType)
 * @method ?string getSecondaryFuelType()
 * @method void setSecondaryFuelType(?string $secondaryFuelType)
 * @method float getInitialOdometer()
 * @method void setInitialOdometer(float $initialOdometer)
 * @method string getOdometerUnit()
 * @method void setOdometerUnit(string $odometerUnit)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 * @method ?\DateTimeImmutable getCreatedAt()
 * @method void setCreatedAt(?\DateTimeImmutable $createdAt)
 * @method bool getArchived()
 * @method void setArchived(bool $archived)
 */
class Car extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected string $name = '';
	protected ?string $brand = null;
	protected ?string $model = null;
	protected ?string $plate = null;
	protected ?int $year = null;
	protected string $fuelType = 'gasoline';
	protected ?string $secondaryFuelType = null;
	protected float $initialOdometer = 0.0;
	protected string $odometerUnit = 'km';
	protected ?string $notes = null;
	protected ?\DateTimeImmutable $createdAt = null;
	protected bool $archived = false;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('brand', Types::STRING);
		$this->addType('model', Types::STRING);
		$this->addType('plate', Types::STRING);
		$this->addType('year', Types::INTEGER);
		$this->addType('fuelType', Types::STRING);
		$this->addType('secondaryFuelType', Types::STRING);
		$this->addType('initialOdometer', Types::FLOAT);
		$this->addType('odometerUnit', Types::STRING);
		$this->addType('notes', Types::TEXT);
		$this->addType('createdAt', Types::DATETIME_IMMUTABLE);
		$this->addType('archived', Types::BOOLEAN);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'name' => $this->name,
			'brand' => $this->brand,
			'model' => $this->model,
			'plate' => $this->plate,
			'year' => $this->year,
			'fuelType' => $this->fuelType,
			'secondaryFuelType' => $this->secondaryFuelType,
			'initialOdometer' => $this->initialOdometer,
			'odometerUnit' => $this->odometerUnit,
			'notes' => $this->notes,
			'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
			'archived' => $this->archived,
		];
	}
}
