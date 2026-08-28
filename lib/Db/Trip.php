<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getCarId()
 * @method void setCarId(int $carId)
 * @method ?\DateTimeImmutable getTripDate()
 * @method void setTripDate(?\DateTimeImmutable $tripDate)
 * @method string getPurpose()
 * @method void setPurpose(string $purpose)
 * @method ?string getOrigin()
 * @method void setOrigin(?string $origin)
 * @method ?string getDestination()
 * @method void setDestination(?string $destination)
 * @method float getStartOdometer()
 * @method void setStartOdometer(float $startOdometer)
 * @method float getEndOdometer()
 * @method void setEndOdometer(float $endOdometer)
 * @method ?float getTolls()
 * @method void setTolls(?float $tolls)
 * @method ?float getOtherCosts()
 * @method void setOtherCosts(?float $otherCosts)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Trip extends Entity implements \JsonSerializable {
	protected int $carId = 0;
	protected ?\DateTimeImmutable $tripDate = null;
	protected string $purpose = 'business';
	protected ?string $origin = null;
	protected ?string $destination = null;
	protected float $startOdometer = 0.0;
	protected float $endOdometer = 0.0;
	protected ?float $tolls = null;
	protected ?float $otherCosts = null;
	protected ?string $notes = null;
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('carId', Types::INTEGER);
		$this->addType('tripDate', Types::DATE_IMMUTABLE);
		$this->addType('purpose', Types::STRING);
		$this->addType('origin', Types::STRING);
		$this->addType('destination', Types::STRING);
		$this->addType('startOdometer', Types::FLOAT);
		$this->addType('endOdometer', Types::FLOAT);
		$this->addType('tolls', Types::FLOAT);
		$this->addType('otherCosts', Types::FLOAT);
		$this->addType('notes', Types::TEXT);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		$distance = max(0.0, $this->endOdometer - $this->startOdometer);
		return [
			'id' => $this->id,
			'carId' => $this->carId,
			'tripDate' => $this->tripDate?->format('Y-m-d'),
			'purpose' => $this->purpose,
			'origin' => $this->origin,
			'destination' => $this->destination,
			'startOdometer' => $this->startOdometer,
			'endOdometer' => $this->endOdometer,
			'distance' => round($distance, 1),
			'tolls' => $this->tolls,
			'otherCosts' => $this->otherCosts,
			'notes' => $this->notes,
			'sortOrder' => $this->sortOrder,
		];
	}
}
