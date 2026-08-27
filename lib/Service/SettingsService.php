<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Service;

use OCA\CarFuelMaintance\AppInfo\Application;
use OCP\IConfig;

/** Per-user app preferences: reminder threshold, display currency symbol and consumption format. */
class SettingsService {
	private const REMINDER_MONTHS_KEY = 'reminderMonths';
	private const DEFAULT_REMINDER_MONTHS = 1;
	private const CURRENCY_SYMBOL_KEY = 'currencySymbol';
	private const DEFAULT_CURRENCY_SYMBOL = '€';
	private const CONSUMPTION_FORMAT_KEY = 'consumptionFormat';
	private const DEFAULT_CONSUMPTION_FORMAT = 'per100';
	private const VALID_CONSUMPTION_FORMATS = ['per100', 'perUnit'];

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

	/**
	 * @return 'per100'|'perUnit' 'per100' e.g. L/100km, gal/100mi. 'perUnit' e.g. km/L, mi/gal (MPG).
	 */
	public function getConsumptionFormat(string $userId): string {
		$value = $this->config->getUserValue($userId, Application::APP_ID, self::CONSUMPTION_FORMAT_KEY, self::DEFAULT_CONSUMPTION_FORMAT);
		return in_array($value, self::VALID_CONSUMPTION_FORMATS, true) ? $value : self::DEFAULT_CONSUMPTION_FORMAT;
	}

	public function setConsumptionFormat(string $userId, string $format): string {
		if (!in_array($format, self::VALID_CONSUMPTION_FORMATS, true)) {
			$format = self::DEFAULT_CONSUMPTION_FORMAT;
		}
		$this->config->setUserValue($userId, Application::APP_ID, self::CONSUMPTION_FORMAT_KEY, $format);
		return $format;
	}
}
