<?php

function assert_contains(string $haystack, string $needle, string $message): void {
	if (strpos($haystack, $needle) === false) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

function assert_regex(string $pattern, string $subject, string $message): void {
	if (!preg_match($pattern, $subject)) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

function assert_not_contains(string $haystack, string $needle, string $message): void {
	if (strpos($haystack, $needle) !== false) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

$setup = file_get_contents(__DIR__ . '/../setup.php');
if ($setup === false) {
	fwrite(STDERR, "Unable to read setup.php\n");
	exit(1);
}

assert_contains(
	$setup,
	'db_fetch_cell_prepared(',
	'Expected plugin version lookup to use db_fetch_cell_prepared().'
);

assert_regex(
	"/SELECT\\s+version\\s+FROM\\s+plugin_config\\s+WHERE\\s+directory\\s*=\\s*\\?/s",
	$setup,
	'Expected version lookup SQL to query plugin_config with directory placeholder.'
);

assert_regex(
	"/db_fetch_cell_prepared\\s*\\(.*\\[\\s*'monitor'\\s*\\]/s",
	$setup,
	'Expected monitor directory value to be bound as a prepared parameter.'
);

assert_contains(
	$setup,
	"db_execute_prepared('DROP TABLE IF EXISTS plugin_monitor_notify_history', []);",
	'Expected uninstall notify-history drop to use db_execute_prepared() with empty bindings.'
);

assert_contains(
	$setup,
	"db_execute_prepared('DROP TABLE IF EXISTS plugin_monitor_reboot_history', []);",
	'Expected uninstall reboot-history drop to use db_execute_prepared().'
);

assert_contains(
	$setup,
	"db_execute_prepared('DROP TABLE IF EXISTS plugin_monitor_uptime', []);",
	'Expected uninstall uptime drop to use db_execute_prepared().'
);

assert_contains(
	$setup,
	'$placeholders = implode(\',\', array_fill(0, cacti_sizeof($devices), \'?\'));',
	'Expected monitor_device_remove() to build placeholder list for IN clause.'
);

assert_contains(
	$setup,
	'db_execute_prepared("DELETE FROM plugin_monitor_notify_history WHERE host_id IN($placeholders)", $devices);',
	'Expected notify history delete to use db_execute_prepared() with placeholders.'
);

assert_contains(
	$setup,
	'db_execute_prepared("DELETE FROM plugin_monitor_reboot_history WHERE host_id IN($placeholders)", $devices);',
	'Expected reboot history delete to use db_execute_prepared() with placeholders.'
);

assert_contains(
	$setup,
	'db_execute_prepared("DELETE FROM plugin_monitor_uptime WHERE host_id IN($placeholders)", $devices);',
	'Expected uptime delete to use db_execute_prepared() with placeholders.'
);

assert_not_contains(
	$setup,
	'db_execute(\'DELETE FROM plugin_monitor_notify_history WHERE host_id IN(\' . implode(\',\', $devices) . \')\');',
	'Raw notify-history delete should not remain.'
);

assert_not_contains(
	$setup,
	'db_execute(\'DELETE FROM plugin_monitor_reboot_history WHERE host_id IN(\' . implode(\',\', $devices) . \')\');',
	'Raw reboot-history delete should not remain.'
);

assert_not_contains(
	$setup,
	'db_execute(\'DELETE FROM plugin_monitor_uptime WHERE host_id IN(\' . implode(\',\', $devices) . \')\');',
	'Raw uptime delete should not remain.'
);

echo "OK\n";
