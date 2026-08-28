<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Migration;

use Closure;
use OCA\CarFuelMaintance\BackgroundJob\CheckRemindersJob;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds reminder-notification tracking to maintenance entries, and registers
 * the background job that checks for due reminders. Registration happens
 * here (a one-time step) rather than in Application::register() because
 * IJobList::add() resets the job's last-run time on every call, which would
 * make it fire far too often if called on every request.
 */
class Version1000Date20260828140000 extends SimpleMigrationStep {
	public function __construct(
		private IJobList $jobList,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('cfm_maintenance_entries');
		if (!$table->hasColumn('notified_status')) {
			$table->addColumn('notified_status', Types::STRING, ['notnull' => false, 'length' => 16]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->jobList->add(CheckRemindersJob::class);
	}
}
