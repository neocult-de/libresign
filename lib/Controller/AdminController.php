<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Service\ConfigureCheckService;
use OCA\Libresign\Service\InstallService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\IEventSource;
use OCP\IEventSourceFactory;
use OCP\IRequest;

class AdminController extends Controller {
	private IEventSource $eventSource;
	public function __construct(
		IRequest $request,
		private ConfigureCheckService $configureCheckService,
		private InstallService $installService,
		private CertificateEngineHandler $certificateEngineHandler,
		private IEventSourceFactory $eventSourceFactory,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->eventSource = $this->eventSourceFactory->create();
	}

	#[NoCSRFRequired]
	public function generateCertificateCfssl(
		array $rootCert,
		string $cfsslUri = '',
		string $configPath = ''
	): DataResponse {
		return $this->generateCertificate($rootCert, [
			'engine' => 'cfssl',
			'configPath' => trim($configPath),
			'cfsslUri' => trim($cfsslUri),
		]);
	}

	#[NoCSRFRequired]
	public function generateCertificateOpenSsl(
		array $rootCert,
		string $configPath = ''
	): DataResponse {
		return $this->generateCertificate($rootCert, [
			'engine' => 'openssl',
			'configPath' => trim($configPath),
		]);
	}

	private function generateCertificate(
		array $rootCert,
		array $properties = [],
	): DataResponse {
		try {
			$names = [];
			foreach ($rootCert['names'] as $item) {
				$names[$item['id']]['value'] = $this->trimAndThrowIfEmpty($item['id'], $item['value']);
			}
			$this->installService->generate(
				$this->trimAndThrowIfEmpty('commonName', $rootCert['commonName']),
				$names ?? [],
				$properties,
			);

			return new DataResponse([
				'data' => $this->certificateEngineHandler->getEngine()->toArray(),
			]);
		} catch (\Exception $exception) {
			return new DataResponse(
				[
					'message' => $exception->getMessage()
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	#[NoCSRFRequired]
	public function loadCertificate(): DataResponse {
		$engine = $this->certificateEngineHandler->getEngine();
		$certificate = $engine->toArray();
		$configureResult = $engine->configureCheck();
		$success = array_filter(
			$configureResult,
			function (ConfigureCheckHelper $config) {
				return $config->getStatus() === 'success';
			}
		);
		$certificate['generated'] = count($success) === count($configureResult);

		return new DataResponse($certificate);
	}

	private function trimAndThrowIfEmpty(string $key, $value): string {
		if (empty($value)) {
			throw new LibresignException("parameter '{$key}' is required!", 400);
		}
		return trim($value);
	}

	#[NoCSRFRequired]
	public function downloadBinaries(): Response {
		try {
			$async = \function_exists('proc_open');
			$this->installService->installJava($async);
			$this->installService->installJSignPdf($async);
			$this->installService->installPdftk($async);
			$this->installService->installCfssl($async);
			return new DataResponse([]);
		} catch (\Exception $exception) {
			return new DataResponse(
				[
					'message' => $exception->getMessage()
				],
				Http::STATUS_UNAUTHORIZED
			);
		}
	}

	public function downloadStatus(): DataResponse {
		$return = $this->installService->getTotalSize();
		return new DataResponse($return);
	}

	public function configureCheck(): DataResponse {
		return new DataResponse(
			$this->configureCheckService->checkAll()
		);
	}

	public function downloadStatusSse(): void {
		while ($this->installService->isDownloadWip()) {
			$totalSize = $this->installService->getTotalSize();
			$this->eventSource->send('total_size', json_encode($totalSize));
			if ($errors = $this->installService->getErrorMessages()) {
				$this->eventSource->send('errors', json_encode($errors));
			}
			usleep(200000); // 0.2 seconds
		}
		$this->eventSource->send('done', '');
		$this->eventSource->close();
	}
}
