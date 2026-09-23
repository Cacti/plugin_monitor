<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for monitor_setup_table() and monitor_poller_bottom() in
 * setup.php.
 *
 * monitor_poller_bottom() include_once()s a poller.php via
 * $config['library_path'], so that is pointed at a throwaway empty stub
 * file for the duration of these tests (a real Cacti library_path would
 * instead point at lib/, which this plugin never touches directly).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'monitor-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/poller.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	monitor_test_reset_db_mocks();
	$GLOBALS['__test_db_calls']       = array();
	$GLOBALS['__test_exec_calls']     = array();
	$GLOBALS['__test_config_options'] = array();
	$GLOBALS['config']['poller_id']   = 1;
});

it('creates every table the plugin owns', function () {
	monitor_setup_table();

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	foreach (array('plugin_monitor_notify_history', 'plugin_monitor_reboot_history', 'plugin_monitor_uptime') as $table) {
		expect($sql)->toContain($table);
	}
});

it('launches poller_monitor.php in the background on the primary poller', function () {
	$GLOBALS['__test_config_options']['path_php_binary'] = '/usr/bin/php';

	monitor_poller_bottom();

	expect($GLOBALS['__test_exec_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_exec_calls'][0]['command'])->toBe('/usr/bin/php');
	expect($GLOBALS['__test_exec_calls'][0]['args'])->toContain('poller_monitor.php');
});

it('does nothing on a non-primary poller', function () {
	$GLOBALS['config']['poller_id'] = 2;

	monitor_poller_bottom();

	expect($GLOBALS['__test_exec_calls'])->toBeEmpty();
});
