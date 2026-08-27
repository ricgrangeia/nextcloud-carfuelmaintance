<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\AppInfo\Application;
use OCP\IConfig;

/** Per-user app preferences: reminder threshold and display currency symbol. */
class SettingsService {
	private const REMINDER_MONTHS_KEY = 'reminderMonths';
	private const DEFAULT_REMINDER_MONTHS = 1;
	private const CURRENCY_SYMBOL_KEY = 'currencySymbol';
	private const DEFAULT_CURRENCY_SYMBOL = '€';

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

	public function getCurrencySymbol(string $userId): string {
		$value = $this->config->getUserValue($userId, Application::APP_ID, self::CURRENCY_SYMBOL_KEY, self::DEFAULT_CURRENCY_SYMBOL);
		return $value !== '' ? $value : self::DEFAULT_CURRENCY_SYMBOL;
	}

	public function setCurrencySymbol(string $userId, string $symbol): string {
		$symbol = trim($symbol);
		if ($symbol === '') {
			$symbol = self::DEFAULT_CURRENCY_SYMBOL;
		}
		$symbol = mb_substr($symbol, 0, 8);
		$this->config->setUserValue($userId, Application::APP_ID, self::CURRENCY_SYMBOL_KEY, $symbol);
		return $symbol;
	}
}
