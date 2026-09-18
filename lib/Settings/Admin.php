<?php
declare(strict_types=1);

namespace OCA\NGSign\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function __construct(private IConfig $config, private IURLGenerator $urlGenerator, private IL10N $l10n) {
	}

	public function getForm(): TemplateResponse {
		\OCP\Util::addScript('ngsign', 'ngsign-settings');
		return new TemplateResponse('ngsign', 'admin', [
			'baseUrl' => $this->config->getAppValue('ngsign', 'base_url', 'https://sandbox.ng-sign.com/server'),
			'configured' => $this->config->getAppValue('ngsign', 'api_token') !== '',
			'debug' => $this->config->getAppValue('ngsign', 'debug', 'no') === 'yes',
			'expirationDays' => $this->config->getAppValue('ngsign', 'expiration_days', '15'),
			'saveUrl' => $this->urlGenerator->linkToRoute('ngsign.settings.save'),
		]);
	}

	public function getSection(): string { return 'additional'; }
	public function getPriority(): int { return 50; }
}
