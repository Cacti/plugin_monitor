<?php

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

monitorRunActionAndRender('monitor_test_action');
assert_same(['action', 'render'], $events, 'Wrapper should run action before render.');

$source = file_get_contents(__DIR__ . '/../monitor.php');
if ($source === false) {
	fwrite(STDERR, "Unable to read monitor.php\n");
	exit(1);
}

$expected_calls = [
	"monitorRunActionAndRender('muteAllHosts');",
	"monitorRunActionAndRender('unmuteAllHosts');",
	"monitorRunActionAndRender('loadDashboardSettings');",
	"monitorRunActionAndRender('removeDashboard');",
	"monitorRunActionAndRender('saveSettings');"
];

foreach ($expected_calls as $expected_call) {
	if (strpos($source, $expected_call) === false) {
		fwrite(STDERR, "Expected monitor.php to call wrapper: $expected_call\n");
		exit(1);
	}
}

echo "OK\n";
