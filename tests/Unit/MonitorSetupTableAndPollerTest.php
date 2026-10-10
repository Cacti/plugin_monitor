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
	require_once __DIR__ . '/../../includes/database.php';

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

it('does not keep plugin ownership of the user-owned dashboards table', function () {
	monitor_setup_table();

	$disowned = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute'
			&& stripos($call['sql'], 'DELETE FROM plugin_db_changes') !== false
			&& stripos($call['sql'], 'plugin_monitor_dashboards') !== false;
	});

	expect($disowned)->not->toBeEmpty();
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

it('parses a legacy dashboard url into the properties vars structure', function () {
	$props = monitor_url_to_properties('monitor.php?refresh=60&grouping=site&site=-1&rfilter=');

	expect($props)->toBe([
		'vars' => [
			'refresh'  => '60',
			'grouping' => 'site',
			'site'     => '-1',
			'rfilter'  => '',
		],
	]);
});

it('migrates legacy dashboard urls into properties and drops the url column', function () {
	monitor_test_reset_db_mocks();
	$GLOBALS['__test_db_calls'] = array();

	monitor_test_mock_db('db_table_exists', 'plugin_monitor_dashboards', true);
	monitor_test_mock_db('db_column_exists', fn ($key) => $key === 'plugin_monitor_dashboards.url', true);
	monitor_test_mock_db('db_fetch_assoc', 'plugin_monitor_dashboards', [
		['id' => 1, 'url' => 'monitor.php?grouping=site&view=tiles'],
	]);

	monitor_migrate_dashboard_properties();

	$updates = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));
	$removed = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_remove_column' && $c['column'] === 'url');

	expect($removed)->not->toBeEmpty();
	expect($updates)->not->toBeEmpty();
	expect($updates[0]['params'][0])->toContain('"grouping":"site"');
});

it('skips dashboard migration when the url column is already gone', function () {
	monitor_test_reset_db_mocks();
	$GLOBALS['__test_db_calls'] = array();

	monitor_test_mock_db('db_table_exists', 'plugin_monitor_dashboards', true);
	monitor_test_mock_db('db_column_exists', fn ($key) => $key === 'plugin_monitor_dashboards.properties', true);

	monitor_migrate_dashboard_properties();

	$removed = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_remove_column');

	expect($removed)->toBeEmpty();
});
