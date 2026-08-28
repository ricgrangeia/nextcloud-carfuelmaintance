<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\BackgroundJob;

use OCA\CarFuelMaintance\AppInfo\Application;
use OCA\CarFuelMaintance\Db\Car;
use OCA\CarFuelMaintance\Db\CarMapper;
use OCA\CarFuelMaintance\Db\MaintenanceEntry;
use OCA\CarFuelMaintance\Db\MaintenanceEntryMapper;
use OCA\CarFuelMaintance\Service\MaintenanceService;
use OCA\CarFuelMaintance\Service\SettingsService;
use OCA\CarFuelMaintance\Service\StatsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

/**
 * Periodically checks every user's maintenance reminders and sends a
 * Nextcloud notification the first time one becomes "due soon" or
 * "overdue" — tracked via MaintenanceEntry::notifiedStatus so the same
 * reminder isn't re-sent on every run, only on first entry into a status
 * or when it escalates from due_soon to overdue.
 */
class CheckRemindersJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private IUserManager $userManager,
		private CarMapper $carMapper,
		private MaintenanceEntryMapper $maintenanceEntryMapper,
		private MaintenanceService $maintenanceService,
		private SettingsService $settingsService,
		private StatsService $statsService,
		private IManager $notificationManager,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(6 * 60 * 60);
	}

	protected function run($argument): void {
		$this->userManager->callForSeenUsers(function (IUser $user): void {
			try {
				$this->checkUser($user->getUID());
			} catch (\Throwable $e) {
				$this->logger->error('carfuelmaintance: failed checking reminders for a user', ['exception' => $e]);
			}
		});
	}

	private function checkUser(string $userId): void {
		if (!$this->settingsService->getNotificationsEnabled($userId)) {
			return;
		}

		$cars = $this->carMapper->findAllForUser($userId, false);
		if ($cars === []) {
			return;
		}

		$reminderMonths = $this->settingsService->getReminderMonths($userId);
		$today = new \DateTimeImmutable('today');

		foreach ($cars as $car) {
			$this->checkCar($car, $userId, $reminderMonths, $today);
		}
	}

	private function checkCar(Car $car, string $userId, int $reminderMonths, \DateTimeImmutable $today): void {
		$entries = $this->maintenanceEntryMapper->findAllForCar($car->getId());
		if ($entries === []) {
			return;
		}

		$currentOdometer = $car->getInitialOdometer();
		foreach ($entries as $entry) {
			$currentOdometer = max($currentOdometer, $entry->getOdometer() ?? 0.0);
		}

		foreach ($entries as $entry) {
			$this->checkEntry($car, $userId, $entry, $currentOdometer, $today, $reminderMonths);
		}
	}

	private function checkEntry(Car $car, string $userId, MaintenanceEntry $entry, float $currentOdometer, \DateTimeImmutable $today, int $reminderMonths): void {
		$reminder = $this->statsService->computeReminder($entry, $currentOdometer, $today, $reminderMonths);
		if ($reminder === null || !in_array($reminder['status'], ['due_soon', 'overdue'], true)) {
			return;
		}
		if ($entry->getNotifiedStatus() === $reminder['status']) {
			return;
		}

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setDateTime(new \DateTime())
			->setObject('maintenance', (string) $entry->getId())
			->setSubject('maintenance_due', [
				'carId' => $car->getId(),
				'carName' => $car->getName(),
				'type' => $reminder['type'],
				'status' => $reminder['status'],
				'nextDueDate' => $reminder['nextDueDate'],
				'nextDueOdometer' => $reminder['nextDueOdometer'],
				'odometerUnit' => $car->getOdometerUnit(),
			]);

		$this->notificationManager->notify($notification);
		$this->maintenanceService->markNotified($entry, $reminder['status']);
	}
}
