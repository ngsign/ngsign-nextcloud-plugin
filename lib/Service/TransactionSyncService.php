<?php
declare(strict_types=1);
namespace OCA\NGSign\Service;
use OCP\Files\IRootFolder;
use OCP\Notification\IManager;

class TransactionSyncService {
	public function __construct(private NGSignClient $ngsign, private PendingTransactionStore $store, private IRootFolder $rootFolder, private IManager $notificationManager) {}

	/**
	 * Fetches the live status from NGSign, refreshes the local signer/status snapshot,
	 * and downloads the signed PDF the moment the transaction turns SIGNED (whether
	 * detected from a manual check or the periodic cron sync).
	 * @param array<string, mixed> $transaction the locally stored transaction record
	 * @return array{status: string, signers: list<array{name: string, email: string, status: string}>, nextSigner: ?string}
	 */
	public function sync(string $id, array $transaction): array {
		$data = $this->ngsign->getTransaction($id);
		$status = (string)($data['object']['status'] ?? 'PENDING');
		$parsed = $this->ngsign->extractSigners($data);
		$this->store->updateSigners($id, $parsed['signers'], $parsed['nextSigner']);
		$this->store->status($id, $status);
		if ($status === 'SIGNED') {
			if (empty($transaction['signedName'])) $this->downloadSignedDocument($id, $transaction);
			if (empty($transaction['notifiedAt'])) $this->notifyOwner($id, $transaction);
		}
		return ['status' => $status] + $parsed;
	}

	/** @param array<string, mixed> $transaction */
	private function downloadSignedDocument(string $id, array $transaction): void {
		$folderPath = dirname((string)$transaction['path']);
		$folder = $this->rootFolder->getUserFolder((string)$transaction['ownerUid'])->get($folderPath === '.' ? '' : $folderPath);
		$name = str_starts_with((string)$transaction['name'], 'signed_') ? (string)$transaction['name'] : 'signed_' . $transaction['name'];
		if (!$folder->nodeExists($name)) $folder->newFile($name, $this->ngsign->downloadSignedDocument($id, (string)$transaction['documentId']));
		$this->store->markSigned($id, $name);
	}

	/** @param array<string, mixed> $transaction */
	private function notifyOwner(string $id, array $transaction): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('ngsign')
			->setUser((string)$transaction['ownerUid'])
			->setDateTime(new \DateTime())
			->setObject('transaction', $id)
			->setSubject('signed', ['name' => (string)$transaction['name']]);
		$this->notificationManager->notify($notification);
		$this->store->markNotified($id);
	}
}
