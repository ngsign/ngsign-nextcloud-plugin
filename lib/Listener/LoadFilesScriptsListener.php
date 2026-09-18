<?php
declare(strict_types=1);

namespace OCA\NGSign\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\NGSign\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @template-implements IEventListener<Event|LoadAdditionalScriptsEvent> */
class LoadFilesScriptsListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		// This event is emitted by the Files app after its core dependencies are
		// registered but before its Vue interface initializes.
		Util::addInitScript(Application::APP_ID, 'ngsign-files');
		Util::addStyle(Application::APP_ID, 'ngsign');
	}
}
