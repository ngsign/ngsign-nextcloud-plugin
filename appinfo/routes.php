<?php
declare(strict_types=1);

return [
	'routes' => [
		['name' => 'signature#launch', 'url' => '/signature/launch', 'verb' => 'POST'],
		['name' => 'settings#save', 'url' => '/settings', 'verb' => 'POST'],
		['name' => 'signature#landing', 'url' => '/landing', 'verb' => 'GET'],
		['name' => 'users#search', 'url' => '/users', 'verb' => 'GET'],
	],
];
