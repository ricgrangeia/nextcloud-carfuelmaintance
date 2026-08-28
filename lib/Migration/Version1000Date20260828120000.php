<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[CreateTable(table: 'cfm_parts', description: 'Spare parts/equipment inventory, optionally linked to a car')]
class Version1000Date20260828120000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('cfm_parts')) {
			$table = $schema->createTable('cfm_parts');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('car_id', Types::BIGINT, ['notnull' => false, 'length' => 20, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('reference', Types::STRING, ['notnull' => false, 'length' => 128]);
			$table->addColumn('condition', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'new']);
			$table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('location', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('quantity', Types::INTEGER, ['notnull' => true, 'default' => 1]);
			$table->addColumn('cost', Types::FLOAT, ['notnull' => false]);
			$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
			$table->addColumn('image_path', Types::STRING, ['notnull' => false, 'length' => 500]);
			$table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'cfm_parts_uid_idx');
			$table->addIndex(['car_id'], 'cfm_parts_car_idx');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
