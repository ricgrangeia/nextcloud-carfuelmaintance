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
 * @method ?float getOdometer()
 * @method void setOdometer(?float $odometer)
 * @method string getType()
 * @method void setType(string $type)
 * @method ?string getDescription()
 * @method void setDescription(?string $description)
 * @method ?float getCost()
 * @method void setCost(?float $cost)
 * @method ?string getWorkshop()
 * @method void setWorkshop(?string $workshop)
 * @method ?\DateTimeImmutable getNextDueDate()
 * @method void setNextDueDate(?\DateTimeImmutable $nextDueDate)
 * @method ?float getNextDueOdometer()
 * @method void setNextDueOdometer(?float $nextDueOdometer)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method ?string getNotifiedStatus()
 * @method void setNotifiedStatus(?string $notifiedStatus)
 */
class MaintenanceEntry extends Entity implements \JsonSerializable {
	protected int $carId = 0;
	protected ?\DateTimeImmutable $entryDate = null;
	protected ?float $odometer = null;
	protected string $type = 'other';
	protected ?string $description = null;
	protected ?float $cost = null;
	protected ?string $workshop = null;
	protected ?\DateTimeImmutable $nextDueDate = null;
	protected ?float $nextDueOdometer = null;
	protected ?string $notes = null;
	protected int $sortOrder = 0;
	/** Last reminder status ('due_soon'|'overdue') a notification was already sent for; reset to null whenever the due date/odometer changes. */
	protected ?string $notifiedStatus = null;

	public function __construct() {
		$this->addType('carId', Types::INTEGER);
		$this->addType('entryDate', Types::DATE_IMMUTABLE);
		$this->addType('odometer', Types::FLOAT);
		$this->addType('type', Types::STRING);
		$this->addType('description', Types::TEXT);
		$this->addType('cost', Types::FLOAT);
		$this->addType('workshop', Types::STRING);
		$this->addType('nextDueDate', Types::DATE_IMMUTABLE);
		$this->addType('nextDueOdometer', Types::FLOAT);
		$this->addType('notes', Types::TEXT);
		$this->addType('sortOrder', Types::INTEGER);
		$this->addType('notifiedStatus', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'carId' => $this->carId,
			'entryDate' => $this->entryDate?->format('Y-m-d'),
			'odometer' => $this->odometer,
			'type' => $this->type,
			'description' => $this->description,
			'cost' => $this->cost,
			'workshop' => $this->workshop,
			'nextDueDate' => $this->nextDueDate?->format('Y-m-d'),
			'nextDueOdometer' => $this->nextDueOdometer,
			'notes' => $this->notes,
			'sortOrder' => $this->sortOrder,
		];
	}
}
