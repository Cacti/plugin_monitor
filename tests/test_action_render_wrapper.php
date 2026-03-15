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
 */

require_once __DIR__ . '/../monitor_helpers.php';

$events = [];

function drawPage(): void {
	global $events;
	$events[] = 'render';
}

function monitor_test_action(): void {
	global $events;
	$events[] = 'action';
}

function assert_same($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message . PHP_EOL);
		fwrite(STDERR, 'Expected: ' . json_encode($expected) . PHP_EOL);
		fwrite(STDERR, 'Actual:   ' . json_encode($actual) . PHP_EOL);
		exit(1);
	}
}

function assert_regex(string $pattern, string $subject, string $message): void {
	if (!preg_match($pattern, $subject)) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

monitorRunActionAndRender('monitor_test_action');
assert_same(['action', 'render'], $events, 'Wrapper should run action before render.');

$source = file_get_contents(__DIR__ . '/../monitor.php');
if ($source === false) {
	fwrite(STDERR, "Unable to read monitor.php\n");
	exit(1);
}

$expected_patterns = [
	"/include_once\\s+__DIR__\\s*\\.\\s*'\\/monitor_helpers\\.php'\\s*;/",
	"/monitorRunActionAndRender\\(\\s*'muteAllHosts'\\s*\\)\\s*;/",
	"/monitorRunActionAndRender\\(\\s*'unmuteAllHosts'\\s*\\)\\s*;/",
	"/monitorRunActionAndRender\\(\\s*'loadDashboardSettings'\\s*\\)\\s*;/",
	"/monitorRunActionAndRender\\(\\s*'removeDashboard'\\s*\\)\\s*;/",
	"/monitorRunActionAndRender\\(\\s*'saveSettings'\\s*\\)\\s*;/"
];

foreach ($expected_patterns as $expected_pattern) {
	assert_regex($expected_pattern, $source, "Expected monitor.php to match pattern: $expected_pattern");
}

echo "OK\n";
