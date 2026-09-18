<?php
declare(strict_types=1);

namespace OCA\NGSign\Service;

use OCP\Http\Client\IClientService;
use OCP\IConfig;

class NGSignClient {
	private const APP_ID = 'ngsign';
	/** @var list<array<string, mixed>> */
	private array $debugTrace = [];
	private bool $debug = false;

	public function __construct(
		private IClientService $clientService,
		private IConfig $config,
	) {
	}

	/** @return array<string, mixed> */
	public function launch(string $fileName, string $content, array $signers): array {
		$this->debug = $this->debugEnabled();
		$this->debugTrace = [];
		$baseUrl = rtrim($this->config->getAppValue(self::APP_ID, 'base_url', 'https://sandbox.ng-sign.com/server'), '/');
		$token = trim($this->config->getAppValue(self::APP_ID, 'api_token'));
		$expirationDays = max(1, min(365, (int)$this->config->getAppValue(self::APP_ID, 'expiration_days', '15')));
		if ($token === '') {
			throw new \RuntimeException('NGSign is not configured. Ask an administrator to add the API token.');
		}

		$upload = $this->request('POST', $baseUrl . '/protected/transaction/pdfs', [[
			'fileName' => pathinfo($fileName, PATHINFO_FILENAME),
			'fileExtension' => 'pdf',
			'fileBase64' => base64_encode($content),
		]], $token);
		// NGSign returns the transaction as object.uuid (the Postman collection
		// documents older variants returning transactionId or id).
		$transactionId = $this->findId($upload, ['transactionId', 'id', 'uuid']);
		$documentId = $this->findId($upload, ['identifier', 'documentId']);
		if ($transactionId === null || $documentId === null) {
			throw new \RuntimeException('NGSign returned an unexpected upload response.');
		}

		$document = [
			'page' => 1,
			'xAxis' => 81,
			'yAxis' => 44.28125,
			'documentName' => pathinfo($fileName, PATHINFO_FILENAME),
			'documentExtension' => 'pdf',
			'identifier' => $documentId,
		];
		$sigConf = array_map(static fn (array $signer): array => [
			'signer' => [
				'firstName' => $signer['firstName'],
				'lastName' => $signer['lastName'],
				'email' => $signer['email'],
				'phoneNumber' => $signer['phoneNumber'] ?? '',
			],
			'sigType' => 'CERTIFIED_TIMESTAMP',
			'docsConfigs' => [$document],
			'mode' => 'BY_MAIL',
			'otp' => 'NONE',
			'choosePosition' => true,
		], $signers);

		$expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+' . $expirationDays . ' days');
		$result = $this->request('POST', $baseUrl . '/protected/transaction/' . rawurlencode($transactionId) . '/launch', ['sigConf' => $sigConf, 'expirationDate' => $expiresAt->format(DATE_ATOM)], $token);
		return ['transactionId' => $transactionId, 'documentId' => $documentId, 'nextSigner' => $this->findId($result, ['nextSigner']), 'expiresAt' => $expiresAt->getTimestamp(), 'result' => $result];
	}

	public function debugEnabled(): bool {
		return $this->config->getAppValue(self::APP_ID, 'debug', 'no') === 'yes';
	}

	/** @return list<array<string, mixed>> */
	public function getDebugTrace(): array {
		return $this->debugTrace;
	}

	/** @return array<string, mixed> */
	public function getTransaction(string $transactionId): array {
		$baseUrl = rtrim($this->config->getAppValue(self::APP_ID, 'base_url'), '/');
		return $this->request('GET', $baseUrl . '/any/' . rawurlencode($transactionId), [], '');
	}

	public function downloadSignedDocument(string $transactionId, string $documentId): string {
		$baseUrl = rtrim($this->config->getAppValue(self::APP_ID, 'base_url'), '/');
		$response = $this->clientService->newClient()->get($baseUrl . '/any/' . rawurlencode($transactionId) . '/pdfs/' . rawurlencode($documentId), ['timeout' => 60, 'http_errors' => false]);
		if ($response->getStatusCode() >= 400) throw new \RuntimeException('NGSign returned HTTP ' . $response->getStatusCode() . ' while downloading the signed PDF.');
		return (string)$response->getBody();
	}

	public function signingUrl(string $nextSigner, string $transactionId, string $landingUrl): string {
		$serverUrl = preg_replace('#/server$#', '', rtrim($this->config->getAppValue(self::APP_ID, 'base_url'), '/'));
		return $serverUrl . '/pds/#/transaction/sign/' . rawurlencode($nextSigner) . '?uuid=' . rawurlencode($transactionId) . '&url=' . rawurlencode($landingUrl . '?transaction=' . rawurlencode($transactionId));
	}

	/** @return array<string, mixed> */
	private function request(string $method, string $url, array $body, string $token): array {
		$jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
		$this->trace([
			'type' => 'request',
			'method' => $method,
			'url' => $url,
			'headers' => ['Authorization' => 'Bearer [REDACTED]', 'Content-Type' => 'application/json'],
			'body' => $this->sanitize($body),
		]);
		try {
			$response = $this->clientService->newClient()->request($method, $url, [
				'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
				'body' => $jsonBody,
				'timeout' => 60,
				'http_errors' => false,
			]);
			$rawBody = (string)$response->getBody();
			$decoded = json_decode($rawBody, true);
			$this->trace([
				'type' => 'response',
				'status' => $response->getStatusCode(),
				'body' => json_last_error() === JSON_ERROR_NONE ? $decoded : $this->truncate($rawBody),
			]);
			if ($response->getStatusCode() >= 400) {
				throw new \RuntimeException('NGSign responded with HTTP ' . $response->getStatusCode() . '.');
			}
			return is_array($decoded) ? $decoded : ['value' => $decoded];
		} catch (\Throwable $exception) {
			$this->trace(['type' => 'exception', 'message' => $exception->getMessage()]);
			throw new \RuntimeException('NGSign request failed: ' . $exception->getMessage(), 0, $exception);
		}
	}

	/** @param array<string, mixed> $entry */
	private function trace(array $entry): void {
		if ($this->debug) $this->debugTrace[] = $entry;
	}

	private function sanitize(mixed $value): mixed {
		if (!is_array($value)) return $value;
		$result = [];
		foreach ($value as $key => $item) {
			$result[$key] = $key === 'fileBase64'
				? ['omitted' => true, 'base64Length' => is_string($item) ? strlen($item) : 0]
				: $this->sanitize($item);
		}
		return $result;
	}

	private function truncate(string $value): string {
		return strlen($value) > 20000 ? substr($value, 0, 20000) . '… [truncated]' : $value;
	}

	private function findId(array $data, array $keys): ?string {
		foreach ($keys as $key) {
			if (isset($data[$key]) && is_scalar($data[$key])) {
				return (string)$data[$key];
			}
		}
		foreach ($data as $value) {
			if (is_array($value)) {
				$id = $this->findId($value, $keys);
				if ($id !== null) return $id;
			}
		}
		return null;
	}
}
