<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Adds purchase price/date to cars, for total-cost-of-ownership stats. */
class Version1000Date20260828130000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('cfm_cars');
		if (!$table->hasColumn('purchase_price')) {
			$table->addColumn('purchase_price', Types::FLOAT, ['notnull' => false]);
		}
		if (!$table->hasColumn('purchase_date')) {
			$table->addColumn('purchase_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
