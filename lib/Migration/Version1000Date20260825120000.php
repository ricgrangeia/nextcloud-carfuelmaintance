<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[CreateTable(table: 'cfm_cars', description: 'Cars owned by a user')]
#[CreateTable(table: 'cfm_fuel_entries', description: 'Fuel fill-up log entries for a car')]
#[CreateTable(table: 'cfm_maintenance_entries', description: 'Maintenance log entries for a car')]
class Version1000Date20260825120000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('cfm_cars')) {
			$table = $schema->createTable('cfm_cars');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('brand', Types::STRING, ['notnull' => false, 'length' => 128]);
			$table->addColumn('model', Types::STRING, ['notnull' => false, 'length' => 128]);
			$table->addColumn('plate', Types::STRING, ['notnull' => false, 'length' => 32]);
			$table->addColumn('year', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('fuel_type', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'gasoline']);
			$table->addColumn('initial_odometer', Types::FLOAT, ['notnull' => true, 'default' => 0.0]);
			$table->addColumn('odometer_unit', Types::STRING, ['notnull' => true, 'length' => 8, 'default' => 'km']);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
			$table->addColumn('archived', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'cfm_cars_uid_idx');
		}

		if (!$schema->hasTable('cfm_fuel_entries')) {
			$table = $schema->createTable('cfm_fuel_entries');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('car_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('entry_date', Types::DATE_IMMUTABLE, ['notnull' => true]);
			$table->addColumn('odometer', Types::FLOAT, ['notnull' => true, 'default' => 0.0]);
			$table->addColumn('quantity', Types::FLOAT, ['notnull' => true, 'default' => 0.0]);
			$table->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'L']);
			$table->addColumn('price_per_unit', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('total_cost', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('full_tank', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->addColumn('station', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['car_id'], 'cfm_fuel_car_idx');
			$table->addIndex(['entry_date'], 'cfm_fuel_date_idx');
		}

		if (!$schema->hasTable('cfm_maintenance_entries')) {
			$table = $schema->createTable('cfm_maintenance_entries');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('car_id', Types::BIGINT, ['notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('entry_date', Types::DATE_IMMUTABLE, ['notnull' => true]);
			$table->addColumn('odometer', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'other']);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('cost', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('workshop', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('next_due_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
			$table->addColumn('next_due_odometer', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['car_id'], 'cfm_maint_car_idx');
			$table->addIndex(['next_due_date'], 'cfm_maint_due_date_idx');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
