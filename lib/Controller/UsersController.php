<?php
declare(strict_types=1);
namespace OCA\NGSign\Controller;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;
class UsersController extends Controller {
	public function __construct(string $appName, IRequest $request, private IUserManager $users) { parent::__construct($appName, $request); }
	/** @NoAdminRequired */
	public function search(string $search = ''): JSONResponse {
		$result = [];
		foreach ($this->users->search($search, 20, 0) as $user) $result[] = ['uid' => $user->getUID(), 'displayName' => $user->getDisplayName(), 'email' => $user->getEMailAddress()];
		return new JSONResponse(['users' => $result]);
	}
}
