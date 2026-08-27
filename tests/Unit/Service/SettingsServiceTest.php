<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Tests\Unit\Service;

use OCA\CarFuelMaintance\AppInfo\Application;
use OCA\CarFuelMaintance\Service\SettingsService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {
	private IConfig&MockObject $config;
	private SettingsService $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->settings = new SettingsService($this->config);
	}

	public function testDefaultReminderMonthsIsOne(): void {
		$this->config->method('getUserValue')
			->with('alice', Application::APP_ID, 'reminderMonths', '1')
			->willReturn('1');

		self::assertSame(1, $this->settings->getReminderMonths('alice'));
	}

	public function testReturnsStoredReminderMonths(): void {
		$this->config->method('getUserValue')->willReturn('3');

		self::assertSame(3, $this->settings->getReminderMonths('alice'));
	}

	public function testSetReminderMonthsClampsToValidRange(): void {
		$this->config->expects(self::once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'reminderMonths', '24');

		self::assertSame(24, $this->settings->setReminderMonths('alice', 100));
	}

	public function testSetReminderMonthsRejectsZeroOrNegative(): void {
		$this->config->expects(self::once())
			->method('setUserValue')
			->with('alice', Application::APP_ID, 'reminderMonths', '1');

		self::assertSame(1, $this->settings->setReminderMonths('alice', 0));
	}
}
