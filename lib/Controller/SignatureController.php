<?php
declare(strict_types=1);

namespace OCA\NGSign\Controller;

use OCA\NGSign\Service\NGSignClient;
use OCA\NGSign\Service\PendingTransactionStore;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;

class SignatureController extends Controller {
	public function __construct(string $appName, IRequest $request, private IRootFolder $rootFolder, private IUserSession $userSession, private NGSignClient $ngsign, private PendingTransactionStore $pendingTransactions, private IURLGenerator $urlGenerator) {
		parent::__construct($appName, $request);
	}

	/** @NoAdminRequired */
	public function launch(string $path, array $signers): JSONResponse {
		try {
			$user = $this->userSession->getUser();
			if ($user === null || !str_ends_with(strtolower($path), '.pdf')) throw new \InvalidArgumentException('Select a PDF file.');
			$node = $this->rootFolder->getUserFolder($user->getUID())->get(ltrim($path, '/'));
			if (!$node instanceof \OCP\Files\File) throw new \RuntimeException('The selected file could not be read.');
			$this->validateSigners($signers);
			$result = $this->ngsign->launch($node->getName(), $node->getContent(), $signers);
			$this->pendingTransactions->add(['transactionId' => $result['transactionId'], 'documentId' => $result['documentId'], 'ownerUid' => $user->getUID(), 'path' => ltrim($path, '/'), 'name' => $node->getName(), 'status' => 'PENDING', 'createdAt' => time(), 'expiresAt' => $result['expiresAt']]);
			$response = ['status' => 'ok'] + $result;
			$currentEmail = strtolower((string)$user->getEMailAddress());
			if ($currentEmail !== '' && strtolower((string)($signers[0]['email'] ?? '')) === $currentEmail && is_string($result['nextSigner']) && $result['nextSigner'] !== '') {
				$response['signingUrl'] = $this->ngsign->signingUrl($result['nextSigner'], $result['transactionId'], $this->urlGenerator->linkToRouteAbsolute('ngsign.signature.landing'));
			}
			if ($this->ngsign->debugEnabled()) $response['debug'] = $this->ngsign->getDebugTrace();
			return new JSONResponse($response);
		} catch (\Throwable $exception) {
			$response = ['status' => 'error', 'message' => $exception->getMessage()];
			if ($this->ngsign->debugEnabled()) $response['debug'] = $this->ngsign->getDebugTrace();
			return new JSONResponse($response, 400);
		}
	}

	/** @NoAdminRequired */
	public function landing(): TemplateResponse {
		return new TemplateResponse('ngsign', 'landing');
	}

	private function validateSigners(array $signers): void {
		if ($signers === []) throw new \InvalidArgumentException('Add at least one signer.');
		foreach ($signers as $signer) {
			if (!is_array($signer) || !filter_var($signer['email'] ?? '', FILTER_VALIDATE_EMAIL) || trim($signer['firstName'] ?? '') === '' || trim($signer['lastName'] ?? '') === '') {
				throw new \InvalidArgumentException('Each signer needs a first name, last name and valid email address.');
			}
		}
	}
}
