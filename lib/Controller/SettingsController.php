<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Controller;

use OCA\CarFuelMaintance\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private SettingsService $settingsService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/settings')]
	public function index(): DataResponse {
		$userId = $this->getUserId();
		return new DataResponse([
			'reminderMonths' => $this->settingsService->getReminderMonths($userId),
			'currencySymbol' => $this->settingsService->getCurrencySymbol($userId),
			'consumptionFormat' => $this->settingsService->getConsumptionFormat($userId),
		]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/settings')]
	public function update(?int $reminderMonths = null, ?string $currencySymbol = null, ?string $consumptionFormat = null): DataResponse {
		$userId = $this->getUserId();
		if ($reminderMonths !== null) {
			$this->settingsService->setReminderMonths($userId, $reminderMonths);
		}
		if ($currencySymbol !== null) {
			$this->settingsService->setCurrencySymbol($userId, $currencySymbol);
		}
		if ($consumptionFormat !== null) {
			$this->settingsService->setConsumptionFormat($userId, $consumptionFormat);
		}
		return new DataResponse([
			'reminderMonths' => $this->settingsService->getReminderMonths($userId),
			'currencySymbol' => $this->settingsService->getCurrencySymbol($userId),
			'consumptionFormat' => $this->settingsService->getConsumptionFormat($userId),
		]);
	}
}
