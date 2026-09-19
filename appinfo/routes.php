<?php
declare(strict_types=1);

return [
	'routes' => [
		['name' => 'signature#launch', 'url' => '/signature/launch', 'verb' => 'POST'],
		['name' => 'settings#save', 'url' => '/settings', 'verb' => 'POST'],
		['name' => 'signature#landing', 'url' => '/landing', 'verb' => 'GET'],
		['name' => 'users#search', 'url' => '/users', 'verb' => 'GET'],
		['name' => 'transactions#index', 'url' => '/transactions', 'verb' => 'GET'],
		['name' => 'transactions#list', 'url' => '/api/transactions', 'verb' => 'GET'],
		['name' => 'transactions#download', 'url' => '/transactions/{transactionId}/download', 'verb' => 'GET'],
		['name' => 'transactions#check', 'url' => '/transactions/{transactionId}/check', 'verb' => 'POST'],
		['name' => 'transactions#cancel', 'url' => '/transactions/{transactionId}/cancel', 'verb' => 'POST'],
	],
];
