<?php
declare(strict_types=1);
namespace OCA\NGSign\Service;
use OCP\IConfig;
class PendingTransactionStore {
	private const APP_ID = 'ngsign'; private const KEY = 'pending_transactions';
	public function __construct(private IConfig $config) {}
	/** @param array<string, mixed> $transaction */ public function add(array $transaction): void { $all = $this->all(); $all[$transaction['transactionId']] = $transaction; $this->save($all); }
	/** @return array<string, array<string, mixed>> */ public function all(): array { $data = json_decode($this->config->getAppValue(self::APP_ID, self::KEY, '{}'), true); return is_array($data) ? $data : []; }
	public function remove(string $id): void { $all = $this->all(); unset($all[$id]); $this->save($all); }
	public function markSigned(string $id, string $signedName): void { $all = $this->all(); if (isset($all[$id])) { $all[$id]['status'] = 'SIGNED'; $all[$id]['signedName'] = $signedName; $this->save($all); } }
	public function markNotified(string $id): void { $all = $this->all(); if (isset($all[$id])) { $all[$id]['notifiedAt'] = time(); $this->save($all); } }
	public function status(string $id, string $status): void { $all = $this->all(); if (isset($all[$id])) { $all[$id]['status'] = $status; $this->save($all); } }
	/** @param list<array{name: string, email: string, status: string}> $signers */
	public function updateSigners(string $id, array $signers, ?string $nextSigner): void { $all = $this->all(); if (isset($all[$id])) { $all[$id]['signerStatuses'] = $signers; $all[$id]['nextSigner'] = $nextSigner; $this->save($all); } }
	/** @param array<string, array<string, mixed>> $all */ private function save(array $all): void { $this->config->setAppValue(self::APP_ID, self::KEY, json_encode($all, JSON_THROW_ON_ERROR)); }
}
