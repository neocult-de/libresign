<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\FileElementService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FileElementController extends AEnvironmentAwareController {
	public function __construct(
		IRequest $request,
		private FileElementService $fileElementService,
		private IUserSession $userSession,
		private ValidateHelper $validateHelper,
		private LoggerInterface $logger
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Create visible element
	 *
	 * Create visible element of a specific file
	 *
	 * @param string $uuid UUID of sign request. The signer UUID is what the person receives via email when asked to sign. This is not the file UUID.
	 * @param integer $signRequestId Id of sign request
	 * @param integer|null $elementId ID of visible element. Each element has an ID that is returned on validation endpoints.
	 * @param string $type The type of element to create, sginature, sinitial, date, datetime, text
	 * @param array<string, mixed> $metadata Metadata of visible elements to associate with the document
	 * @param array<string, mixed> $coordinates Coortinates of a visible element on PDF
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{errors: array{}}, array{}>
	 *
	 * 200: OK
	 * 404: Failure when create visible element
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function post(string $uuid, int $signRequestId, int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []): DataResponse {
		$visibleElement = [
			'elementId' => $elementId,
			'type' => $type,
			'signRequestId' => $signRequestId,
			'coordinates' => $coordinates,
			'metadata' => $metadata,
			'fileUuid' => $uuid,
		];
		try {
			$this->validateHelper->validateVisibleElement($visibleElement, ValidateHelper::TYPE_VISIBLE_ELEMENT_PDF);
			$this->validateHelper->validateExistingFile([
				'uuid' => $uuid,
				'userManager' => $this->userSession->getUser()
			]);
			$this->validateHelper->signerCanHaveVisibleElement($signRequestId);
			$fileElement = $this->fileElementService->saveVisibleElement($visibleElement, $uuid);
			$return = [
				'fileElementId' => $fileElement->getId(),
			];
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			$return = [
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
		}
		return new DataResponse($return, $statusCode);
	}

	/**
	 * Update visible element
	 *
	 * Update visible element of a specific file
	 *
	 * @param string $uuid UUID of sign request. The signer UUID is what the person receives via email when asked to sign. This is not the file UUID.
	 * @param integer $signRequestId Id of sign request
	 * @param integer|null $elementId ID of visible element. Each element has an ID that is returned on validation endpoints.
	 * @param string $type The type of element to create, sginature, sinitial, date, datetime, text
	 * @param array<string, mixed> $metadata Metadata of visible elements to associate with the document
	 * @param array<string, mixed> $coordinates Coortinates of a visible element on PDF
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{errors: array{}}, array{}>
	 *
	 * 200: OK
	 * 404: Failure when patch visible element
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function patch(string $uuid, int $signRequestId, int $elementId = null, string $type = '', array $metadata = [], array $coordinates = []): DataResponse {
		return $this->post($uuid, $signRequestId, $elementId, $type, $metadata, $coordinates);
	}

	/**
	 * Delete visible element
	 *
	 * Delete visible element of a specific file
	 *
	 * @param string $uuid UUID of sign request. The signer UUID is what the person receives via email when asked to sign. This is not the file UUID.
	 * @param integer|null $elementId ID of visible element. Each element has an ID that is returned on validation endpoints.
	 * @param integer $signRequestId Id of sign request
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{errors: array{}}, array{}>
	 *
	 * 200: OK
	 * 404: Failure when delete visible element or file not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function delete(string $uuid, int $elementId): DataResponse {
		try {
			$this->validateHelper->validateExistingFile([
				'uuid' => $uuid,
				'userManager' => $this->userSession->getUser()
			]);
			$this->validateHelper->validateAuthenticatedUserIsOwnerOfPdfVisibleElement($elementId, $this->userSession->getUser()->getUID());
			$this->fileElementService->deleteVisibleElement($elementId);
			$return = [];
			$statusCode = Http::STATUS_OK;
		} catch (\Throwable $th) {
			$this->logger->error($th->getMessage());
			$return = [
				'errors' => [$th->getMessage()]
			];
			$statusCode = $th->getCode() > 0 ? $th->getCode() : Http::STATUS_NOT_FOUND;
		}
		return new DataResponse($return, $statusCode);
	}
}
