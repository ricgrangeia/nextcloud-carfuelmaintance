<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Trip> */
class TripMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'cfm_trips', Trip::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function find(int $id): Trip {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/** @return Trip[] */
	public function findAllForCar(int $carId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('car_id', $qb->createNamedParameter($carId, IQueryBuilder::PARAM_INT)))
			->orderBy('trip_date', 'ASC')
			->addOrderBy('sort_order', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	public function deleteAllForCar(int $carId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('car_id', $qb->createNamedParameter($carId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
