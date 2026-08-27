<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\AppInfo\Application;
use OCP\IConfig;

/** Per-user app preferences (currently: how far ahead to flag due reminders). */
class SettingsService {
	private const REMINDER_MONTHS_KEY = 'reminderMonths';
	private const DEFAULT_REMINDER_MONTHS = 1;

	public function __construct(
		private IConfig $config,
	) {
	}

	public function getReminderMonths(string $userId): int {
		$value = $this->config->getUserValue($userId, Application::APP_ID, self::REMINDER_MONTHS_KEY, (string) self::DEFAULT_REMINDER_MONTHS);
		$months = (int) $value;
		return $months > 0 ? $months : self::DEFAULT_REMINDER_MONTHS;
	}

	public function setReminderMonths(string $userId, int $months): int {
		$months = max(1, min(24, $months));
		$this->config->setUserValue($userId, Application::APP_ID, self::REMINDER_MONTHS_KEY, (string) $months);
		return $months;
	}
}
