<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\Db\CarMapper;
use OCA\CarFuelMaintance\Db\PartItem;
use OCA\CarFuelMaintance\Db\PartItemMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

/**
 * CRUD for the parts/equipment inventory. Photos are stored as regular files
 * under "CarFuelMaintance/parts" in the owning user's own Nextcloud Files, so
 * they're backed up and browsable like any other file — not blobs in the DB.
 */
class PartService {
	private const STORAGE_FOLDER = 'CarFuelMaintance/parts';
	private const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
	private const MAX_IMAGE_SIZE = 10 * 1024 * 1024;

	public function __construct(
		private PartItemMapper $partItemMapper,
		private CarMapper $carMapper,
		private IRootFolder $rootFolder,
	) {
	}

	/**
	 * @throws DoesNotExistException if the part does not exist or is not owned by $userId
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id, string $userId): PartItem {
		return $this->partItemMapper->find($id, $userId);
	}

	/** @return PartItem[] */
	public function findAll(string $userId, ?int $carId = null): array {
		return $this->partItemMapper->findAllForUser($userId, $carId);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException if $carId is set but not owned by $userId */
	private function assertCarOwnedIfSet(?int $carId, string $userId): void {
		if ($carId !== null) {
			$this->carMapper->find($carId, $userId);
		}
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function create(
		string $userId,
		string $name,
		?int $carId = null,
		?string $reference = null,
		string $condition = 'new',
		?string $category = null,
		?string $location = null,
		int $quantity = 1,
		?float $cost = null,
		?string $notes = null,
	): PartItem {
		$this->assertCarOwnedIfSet($carId, $userId);

		$part = new PartItem();
		$part->setUserId($userId);
		$part->setCarId($carId);
		$part->setName($name);
		$part->setReference($reference);
		$part->setCondition($condition === 'used' ? 'used' : 'new');
		$part->setCategory($category);
		$part->setLocation($location);
		$part->setQuantity(max(1, $quantity));
		$part->setCost($cost);
		$part->setNotes($notes);
		$part->setCreatedAt(new \DateTimeImmutable());
		return $this->partItemMapper->insert($part);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function update(
		int $id,
		string $userId,
		?string $name = null,
		?int $carId = null,
		bool $carIdProvided = false,
		?string $reference = null,
		bool $referenceProvided = false,
		?string $condition = null,
		?string $category = null,
		bool $categoryProvided = false,
		?string $location = null,
		bool $locationProvided = false,
		?int $quantity = null,
		?float $cost = null,
		bool $costProvided = false,
		?string $notes = null,
		bool $notesProvided = false,
	): PartItem {
		$part = $this->find($id, $userId);

		if ($name !== null) {
			$part->setName($name);
		}
		if ($carIdProvided) {
			$this->assertCarOwnedIfSet($carId, $userId);
			$part->setCarId($carId);
		}
		if ($referenceProvided) {
			$part->setReference($reference);
		}
		if ($condition !== null) {
			$part->setCondition($condition === 'used' ? 'used' : 'new');
		}
		if ($categoryProvided) {
			$part->setCategory($category);
		}
		if ($locationProvided) {
			$part->setLocation($location);
		}
		if ($quantity !== null) {
			$part->setQuantity(max(1, $quantity));
		}
		if ($costProvided) {
			$part->setCost($cost);
		}
		if ($notesProvided) {
			$part->setNotes($notes);
		}

		return $this->partItemMapper->update($part);
	}

	/** @throws DoesNotExistException|MultipleObjectsReturnedException */
	public function delete(int $id, string $userId): void {
		$part = $this->find($id, $userId);
		$this->deleteImage($part);
		$this->partItemMapper->delete($part);
	}

	public function deleteAllForCar(int $carId, string $userId): void {
		foreach ($this->partItemMapper->findAllForUser($userId, $carId) as $part) {
			$this->deleteImage($part);
		}
		$this->partItemMapper->deleteAllForCar($carId);
	}

	private function storageFolder(string $userId): \OCP\Files\Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		if ($userFolder->nodeExists(self::STORAGE_FOLDER)) {
			return $userFolder->get(self::STORAGE_FOLDER);
		}
		return $userFolder->newFolder(self::STORAGE_FOLDER);
	}

	/**
	 * @throws DoesNotExistException|MultipleObjectsReturnedException
	 * @throws \InvalidArgumentException if the file is not a supported image or is too large
	 */
	public function setImage(int $id, string $userId, string $tmpPath, string $mimeType): PartItem {
		$part = $this->find($id, $userId);

		if (!in_array($mimeType, self::ALLOWED_IMAGE_MIME_TYPES, true)) {
			throw new \InvalidArgumentException('Unsupported image type');
		}
		$size = filesize($tmpPath);
		if ($size === false || $size > self::MAX_IMAGE_SIZE) {
			throw new \InvalidArgumentException('Image too large');
		}
		$content = file_get_contents($tmpPath);
		if ($content === false) {
			throw new \InvalidArgumentException('Could not read uploaded image');
		}

		$this->deleteImage($part);

		$extension = match ($mimeType) {
			'image/png' => 'png',
			'image/webp' => 'webp',
			'image/gif' => 'gif',
			default => 'jpg',
		};
		$fileName = $id . '-' . bin2hex(random_bytes(4)) . '.' . $extension;

		$folder = $this->storageFolder($userId);
		$file = $folder->newFile($fileName);
		$file->putContent($content);

		$part->setImagePath($fileName);
		return $this->partItemMapper->update($part);
	}

	/**
	 * @throws DoesNotExistException|MultipleObjectsReturnedException if the part has no image
	 */
	public function getImage(int $id, string $userId): File {
		$part = $this->find($id, $userId);
		if ($part->getImagePath() === null) {
			throw new DoesNotExistException('No image set for this part');
		}
		try {
			$node = $this->storageFolder($userId)->get($part->getImagePath());
		} catch (NotFoundException) {
			throw new DoesNotExistException('Image file is missing');
		}
		if (!$node instanceof File) {
			throw new DoesNotExistException('Image file is missing');
		}
		return $node;
	}

	private function deleteImage(PartItem $part): void {
		if ($part->getImagePath() === null) {
			return;
		}
		try {
			$folder = $this->storageFolder($part->getUserId());
			if ($folder->nodeExists($part->getImagePath())) {
				$folder->get($part->getImagePath())->delete();
			}
		} catch (NotFoundException|NotPermittedException) {
			// nothing to clean up
		}
	}
}
