<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method ?int getCarId()
 * @method void setCarId(?int $carId)
 * @method string getName()
 * @method void setName(string $name)
 * @method ?string getReference()
 * @method void setReference(?string $reference)
 * @method string getCondition()
 * @method void setCondition(string $condition)
 * @method ?string getCategory()
 * @method void setCategory(?string $category)
 * @method ?string getLocation()
 * @method void setLocation(?string $location)
 * @method int getQuantity()
 * @method void setQuantity(int $quantity)
 * @method ?float getCost()
 * @method void setCost(?float $cost)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 * @method ?string getImagePath()
 * @method void setImagePath(?string $imagePath)
 * @method ?\DateTimeImmutable getCreatedAt()
 * @method void setCreatedAt(?\DateTimeImmutable $createdAt)
 */
class PartItem extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected ?int $carId = null;
	protected string $name = '';
	protected ?string $reference = null;
	protected string $condition = 'new';
	protected ?string $category = null;
	protected ?string $location = null;
	protected int $quantity = 1;
	protected ?float $cost = null;
	protected ?string $notes = null;
	protected ?string $imagePath = null;
	protected ?\DateTimeImmutable $createdAt = null;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('carId', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('reference', Types::STRING);
		$this->addType('condition', Types::STRING);
		$this->addType('category', Types::STRING);
		$this->addType('location', Types::STRING);
		$this->addType('quantity', Types::INTEGER);
		$this->addType('cost', Types::FLOAT);
		$this->addType('notes', Types::TEXT);
		$this->addType('imagePath', Types::STRING);
		$this->addType('createdAt', Types::DATETIME_IMMUTABLE);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'carId' => $this->carId,
			'name' => $this->name,
			'reference' => $this->reference,
			'condition' => $this->condition,
			'category' => $this->category,
			'location' => $this->location,
			'quantity' => $this->quantity,
			'cost' => $this->cost,
			'notes' => $this->notes,
			'hasImage' => $this->imagePath !== null,
			'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
		];
	}
}
