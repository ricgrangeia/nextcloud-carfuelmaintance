<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds bifuel support: a car can declare a secondary fuel type (e.g. a
 * gasoline/LPG car), and each fuel entry records which of the two it filled,
 * so consumption is computed separately per fuel type instead of mixing them.
 */
class Version1000Date20260827130000 extends SimpleMigrationStep {
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$carsTable = $schema->getTable('cfm_cars');
		if (!$carsTable->hasColumn('secondary_fuel_type')) {
			$carsTable->addColumn('secondary_fuel_type', Types::STRING, ['notnull' => false, 'length' => 32]);
		}

		$fuelTable = $schema->getTable('cfm_fuel_entries');
		if (!$fuelTable->hasColumn('fuel_type')) {
			$fuelTable->addColumn('fuel_type', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'gasoline']);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
