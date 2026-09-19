<?php
declare(strict_types=1);

namespace OCA\NGSign\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\NGSign\Listener\LoadFilesScriptsListener;
use OCA\NGSign\BackgroundJob\SyncSignedTransactionsJob;
use OCA\NGSign\Notification\Notifier;
use OCP\BackgroundJob\IJobList;
use OCP\INavigationManager;

class Application extends App implements IBootstrap {
	public const APP_ID = 'ngsign';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadFilesScriptsListener::class);
		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
		$context->getServerContainer()->get(IJobList::class)->add(SyncSignedTransactionsJob::class);
		$context->getServerContainer()->get(INavigationManager::class)->add(function () use ($context): array {
			$urlGenerator = $context->getServerContainer()->get(\OCP\IURLGenerator::class);
			return ['id' => 'ngsign', 'order' => 90, 'href' => $urlGenerator->linkToRoute('ngsign.transactions.index'), 'icon' => $urlGenerator->imagePath('core', 'actions/edit.svg'), 'name' => 'NGSign'];
		});
	}
}
