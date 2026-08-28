<?php

declare(strict_types=1);

namespace OCA\CarFuelMaintance\Controller;

use OCA\CarFuelMaintance\Service\PartService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class PartController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private PartService $partService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	private function notFound(): DataResponse {
		return new DataResponse([], Http::STATUS_NOT_FOUND);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/parts')]
	public function index(?int $carId = null): DataResponse {
		return new DataResponse($this->partService->findAll($this->getUserId(), $carId));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/parts')]
	public function create(
		string $name,
		?int $carId = null,
		?string $reference = null,
		string $condition = 'new',
		?string $category = null,
		?string $location = null,
		int $quantity = 1,
		?float $cost = null,
		?string $notes = null,
	): DataResponse {
		try {
			return new DataResponse(
				$this->partService->create($this->getUserId(), $name, $carId, $reference, $condition, $category, $location, $quantity, $cost, $notes),
				Http::STATUS_CREATED,
			);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/parts/{id}', requirements: ['id' => '\d+'])]
	public function update(
		int $id,
		?string $name = null,
		?int $carId = null,
		bool $carIdProvided = false,
		?string $reference = null,
		bool $referenceProvided = false,
		?string $condition = null,
		?string $category = null,
		bool $categoryProvided = false,
		?string $location = null,
		bool $locationProvided = false,
		?int $quantity = null,
		?float $cost = null,
		bool $costProvided = false,
		?string $notes = null,
		bool $notesProvided = false,
	): DataResponse {
		try {
			return new DataResponse($this->partService->update(
				$id,
				$this->getUserId(),
				$name,
				$carId,
				$carIdProvided,
				$reference,
				$referenceProvided,
				$condition,
				$category,
				$categoryProvided,
				$location,
				$locationProvided,
				$quantity,
				$cost,
				$costProvided,
				$notes,
				$notesProvided,
			));
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/parts/{id}', requirements: ['id' => '\d+'])]
	public function destroy(int $id): DataResponse {
		try {
			$this->partService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return $this->notFound();
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/parts/{id}/image', requirements: ['id' => '\d+'])]
	public function uploadImage(int $id): DataResponse {
		$uploaded = $this->request->getUploadedFile('image');
		if ($uploaded === null || !is_string($uploaded['tmp_name'] ?? null) || !is_uploaded_file($uploaded['tmp_name'])) {
			return new DataResponse(['message' => 'No image uploaded'], Http::STATUS_BAD_REQUEST);
		}

		try {
			return new DataResponse($this->partService->setImage($id, $this->getUserId(), $uploaded['tmp_name'], (string) ($uploaded['type'] ?? '')));
		} catch (DoesNotExistException) {
			return $this->notFound();
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/parts/{id}/image', requirements: ['id' => '\d+'])]
	public function image(int $id): Http\Response {
		try {
			$file = $this->partService->getImage($id, $this->getUserId());
			return new DataDisplayResponse($file->getContent(), Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
		} catch (DoesNotExistException) {
			return new Http\Response(Http::STATUS_NOT_FOUND);
		}
	}
}
