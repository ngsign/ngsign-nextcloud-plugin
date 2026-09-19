<?php
declare(strict_types=1);
namespace OCA\NGSign\Controller;
use OCA\NGSign\Service\PendingTransactionStore;
use OCA\NGSign\Service\NGSignClient;
use OCA\NGSign\Service\TransactionSyncService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;
class TransactionsController extends Controller {
	public function __construct(string $appName, IRequest $request, private PendingTransactionStore $store, private IUserSession $session, private IRootFolder $root, private NGSignClient $ngsign, private TransactionSyncService $sync) { parent::__construct($appName, $request); }
	/** @NoAdminRequired
	 * @NoCSRFRequired
	 */ public function index(): TemplateResponse { \OCP\Util::addScript('ngsign', 'ngsign-transactions'); \OCP\Util::addStyle('ngsign', 'ngsign'); return new TemplateResponse('ngsign', 'transactions'); }
	/** @NoAdminRequired
	 * @NoCSRFRequired
	 */ public function list(): JSONResponse {
		$uid = $this->session->getUser()?->getUID();
		$items = array_values(array_filter($this->store->all(), fn ($item) => ($item['ownerUid'] ?? '') === $uid));
		usort($items, static fn (array $a, array $b): int => ($b['createdAt'] ?? 0) <=> ($a['createdAt'] ?? 0));
		$items = array_map(static fn (array $item): array => [
			'transactionId' => $item['transactionId'],
			'name' => $item['name'],
			'status' => $item['status'],
			'createdAt' => $item['createdAt'] ?? null,
			'expiresAt' => $item['expiresAt'],
			'signedName' => $item['signedName'] ?? null,
			'signers' => $item['signerStatuses'] ?? [],
			'nextSigner' => $item['nextSigner'] ?? null,
		], $items);
		return new JSONResponse(['transactions' => $items]);
	}
	/** @NoAdminRequired
	 * @NoCSRFRequired
	 */ public function download(string $transactionId): DataDownloadResponse {
		$item = $this->store->all()[$transactionId] ?? null; $user = $this->session->getUser();
		if (!$item || !$user || $item['ownerUid'] !== $user->getUID() || empty($item['signedName'])) throw new \RuntimeException('Signed document is not available.');
		$folder = $this->root->getUserFolder($user->getUID()); $file = $folder->get(dirname($item['path']) . '/' . $item['signedName']);
		return new DataDownloadResponse($file->getContent(), $item['signedName'], 'application/pdf');
	}
	/** @NoAdminRequired */ public function check(string $transactionId): JSONResponse {
		$item = $this->store->all()[$transactionId] ?? null; $user = $this->session->getUser();
		if (!$item || !$user || $item['ownerUid'] !== $user->getUID()) return new JSONResponse(['message' => 'Transaction not found.'], 404);
		try { return new JSONResponse($this->sync->sync($transactionId, $item)); }
		catch (\Throwable $exception) { return new JSONResponse(['message' => $exception->getMessage()], 400); }
	}
	/** @NoAdminRequired */ public function cancel(string $transactionId): JSONResponse {
		$item = $this->store->all()[$transactionId] ?? null; $user = $this->session->getUser();
		if (!$item || !$user || $item['ownerUid'] !== $user->getUID()) return new JSONResponse(['message' => 'Transaction not found.'], 404);
		if (($item['status'] ?? null) === 'SIGNED') return new JSONResponse(['message' => 'A signed transaction cannot be cancelled.'], 400);
		try {
			$this->ngsign->cancelTransaction($transactionId);
			$this->store->status($transactionId, 'CANCELLED');
			return new JSONResponse(['status' => 'CANCELLED']);
		} catch (\Throwable $exception) { return new JSONResponse(['message' => $exception->getMessage()], 400); }
	}
}
