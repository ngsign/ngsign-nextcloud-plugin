<?php
declare(strict_types=1);
namespace OCA\NGSign\BackgroundJob;
use OCA\NGSign\Service\NGSignClient;
use OCA\NGSign\Service\PendingTransactionStore;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\IRootFolder;
class SyncSignedTransactionsJob extends TimedJob {
	public function __construct(private PendingTransactionStore $store, private NGSignClient $ngsign, private IRootFolder $rootFolder) { $this->setInterval(300); }
	protected function run($argument): void {
		foreach ($this->store->all() as $id => $transaction) try {
			if ((int)($transaction['expiresAt'] ?? 0) > 0 && (int)$transaction['expiresAt'] < time()) { $this->store->remove($id); continue; }
			$status = $this->ngsign->getTransaction($id);
			if (($status['object']['status'] ?? null) !== 'SIGNED') continue;
			$folderPath = dirname((string)$transaction['path']);
			$folder = $this->rootFolder->getUserFolder((string)$transaction['ownerUid'])->get($folderPath === '.' ? '' : $folderPath);
			$name = str_starts_with((string)$transaction['name'], 'signed_') ? (string)$transaction['name'] : 'signed_' . $transaction['name'];
			if (!$folder->nodeExists($name)) $folder->newFile($name, $this->ngsign->downloadSignedDocument($id, (string)$transaction['documentId']));
			$this->store->remove($id);
		} catch (\Throwable) { /* The next scheduled run retries transient failures. */ }
	}
}
