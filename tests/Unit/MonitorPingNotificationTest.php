<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

beforeAll(function () {
	if (!defined('MONITOR_PING_NOTIFICATION_SUBJECT')) {
		define('MONITOR_PING_NOTIFICATION_SUBJECT', 'Cacti Monitor Plugin Ping Threshold Notification');
	}

	require_once __DIR__ . '/../../poller_functions.php';
});

it('builds HTML and plain-text ping threshold notification intros', function () {
	[$html, $text] = buildPingNotificationIntro(0);

	expect($html)->toContain('ping latency thresholds')
		->and($text)->toContain('ping latency thresholds')
		->and($html)->toContain('every time the Device is above its threshold');

	[$htmlFreq, $textFreq] = buildPingNotificationIntro(15);

	expect($htmlFreq)->toContain('every %d minutes')
		->and($textFreq)->toContain('every %d minutes');
});
