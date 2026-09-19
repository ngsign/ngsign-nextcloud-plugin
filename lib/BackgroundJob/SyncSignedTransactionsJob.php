<?php
declare(strict_types=1);
namespace OCA\NGSign\BackgroundJob;
use OCA\NGSign\Service\PendingTransactionStore;
use OCA\NGSign\Service\TransactionSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
class SyncSignedTransactionsJob extends TimedJob {
	public function __construct(ITimeFactory $time, private PendingTransactionStore $store, private TransactionSyncService $sync) { parent::__construct($time); $this->setInterval(300); }
	protected function run($argument): void {
		foreach ($this->store->all() as $id => $transaction) try {
			if ((int)($transaction['expiresAt'] ?? 0) > 0 && (int)$transaction['expiresAt'] < time()) { $this->store->remove($id); continue; }
			$this->sync->sync($id, $transaction);
		} catch (\Throwable) { /* The next scheduled run retries transient failures. */ }
	}
}
