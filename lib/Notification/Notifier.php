<?php
declare(strict_types=1);
namespace OCA\NGSign\Notification;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(private IFactory $l10nFactory, private IURLGenerator $urlGenerator) {}

	public function getID(): string { return 'ngsign'; }

	public function getName(): string { return 'NGSign'; }

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'ngsign') throw new UnknownNotificationException();
		if ($notification->getSubject() !== 'signed') throw new UnknownNotificationException();
		$l = $this->l10nFactory->get('ngsign', $languageCode);
		$name = (string)($notification->getSubjectParameters()['name'] ?? '');
		$notification->setParsedSubject($l->t('Your document "%s" has been signed by all signers.', [$name]))
			->setLink($this->urlGenerator->linkToRouteAbsolute('ngsign.transactions.index'))
			->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/checkmark.svg')));
		return $notification;
	}
}
