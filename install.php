<?php

require_once __DIR__ . '/app/crest.php'; // use class CRest from Bitrix24 SDK

$result = CRest::installApp();

$events = [];
$eventList = CRest::call('event.get')['result'];

foreach($eventList as $event) {
	$events[] = $event['event'];
}

if(!in_array('ONCRMCOPMANYADD', $events)) {
	CRest::call('event.bind', [
		'event' => 'onCrmCompanyAdd'
		'handler' => 'https://bx.kraska54.ru/events.php'
	]);
}

if(!in_array('ONCRMCOMPANYUPDATE', $events)) {
	CRest::call('event.bind', [
		'event' => 'onCrmCompanyUpdate'
		'handler' => 'https://bx.kraska54.ru/events.php'
	]);
}

