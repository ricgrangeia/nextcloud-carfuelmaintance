<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[CreateTable(table: 'cfm_trips', description: 'Trip log entries (distance, purpose, tolls) for a car')]
class Version1000Date20260828150000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('cfm_trips')) {
			$table = $schema->createTable('cfm_trips');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('car_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('trip_date', Types::DATE_IMMUTABLE, ['notnull' => true]);
			$table->addColumn('purpose', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'business']);
			$table->addColumn('origin', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('destination', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('start_odometer', Types::FLOAT, ['notnull' => true, 'default' => 0.0]);
			$table->addColumn('end_odometer', Types::FLOAT, ['notnull' => true, 'default' => 0.0]);
			$table->addColumn('tolls', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('other_costs', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['car_id'], 'cfm_trips_car_idx');
			$table->addIndex(['trip_date'], 'cfm_trips_date_idx');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
