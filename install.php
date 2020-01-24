<?php

require_once __DIR__ . '/app/crest.php'; // use class CRest from Bitrix24 SDK

$result = CRest::installApp();
$eventBind = CRest::callBatch([
	'set_event_onCrmCompanyAdd' => [
		'method' => 'event.bind',
		'params' => [
			'event' => 'onCrmCompanyAdd',
			'handler' => 'https://your.domain/eventHandler.php' // Event handler URL
		]
	],
	'set_event_onCrmCompanyUpdate' => [
		'method' => 'event.bind',
		'params' => [
			'event' => 'onCrmCompanyUpdate',
			'handler' => 'https://your.domain/eventHandler.php' // Event handler URL
		]
	]
]);

