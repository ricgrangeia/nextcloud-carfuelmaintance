<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<PartItem> */
class PartItemMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'cfm_parts', PartItem::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function find(int $id, string $userId): PartItem {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $this->findEntity($qb);
	}

	/** @return PartItem[] */
	public function findAllForUser(string $userId, ?int $carId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		if ($carId !== null) {
			$qb->andWhere($qb->expr()->eq('car_id', $qb->createNamedParameter($carId, IQueryBuilder::PARAM_INT)));
		}

		$qb->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	public function deleteAllForCar(int $carId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('car_id', $qb->createNamedParameter($carId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
