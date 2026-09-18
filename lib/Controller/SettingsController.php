<?php
declare(strict_types=1);

namespace OCA\NGSign\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class SettingsController extends Controller {
	public function __construct(string $appName, IRequest $request, private IConfig $config, private IUserSession $userSession, private IGroupManager $groupManager) {
		parent::__construct($appName, $request);
	}

	/** @AdminRequired */
	public function save(string $baseUrl, string $apiToken, ?string $debug = null, ?string $expirationDays = null): JSONResponse {
		$baseUrl = rtrim(trim($baseUrl), '/');
		if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !str_starts_with($baseUrl, 'https://')) {
			return new JSONResponse(['message' => 'The NGSign server URL must use HTTPS.'], 400);
		}
		$this->config->setAppValue('ngsign', 'base_url', $baseUrl);
		if (trim($apiToken) !== '') $this->config->setAppValue('ngsign', 'api_token', trim($apiToken));
		$days = (int)($expirationDays ?? 15);
		if ($days < 1 || $days > 365) return new JSONResponse(['message' => 'The expiration duration must be between 1 and 365 days.'], 400);
		$this->config->setAppValue('ngsign', 'expiration_days', (string)$days);
		$this->config->setAppValue('ngsign', 'debug', $debug === '1' ? 'yes' : 'no');
		return new JSONResponse(['status' => 'ok']);
	}
}
