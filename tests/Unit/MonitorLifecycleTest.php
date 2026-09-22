<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the plugin lifecycle contract functions in setup.php:
 * plugin_monitor_uninstall(), plugin_monitor_check_config(),
 * plugin_monitor_upgrade(), and monitor_check_upgrade()'s page-guard and
 * version-drift branches.
 *
 * plugin_monitor_check_config() include_once()s Cacti core's
 * database.php via $config['library_path'], so that is pointed at a
 * throwaway empty stub file for the duration of these tests.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'monitor-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");
	file_put_contents($stubLibraryPath . '/poller.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	monitor_test_reset_db_mocks();
	$GLOBALS['__test_db_calls']       = array();
	$GLOBALS['__test_config_options'] = array();
	$_SERVER['PHP_SELF']              = '/monitor.php';
});

it('drops every table it owns on uninstall', function () {
	plugin_monitor_uninstall();

	$drops = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'DROP TABLE') !== false;
	});

	expect($drops)->toHaveCount(3);
});

it('normalizes an out-of-range monitor_refresh setting to 300', function () {
	$GLOBALS['__test_config_options']['monitor_refresh'] = '9999';

	expect(plugin_monitor_check_config())->toBeTrue();

	expect($GLOBALS['__test_config_options']['monitor_refresh'])->toBe('300');
});

it('leaves a valid monitor_refresh setting untouched', function () {
	$GLOBALS['__test_config_options']['monitor_refresh'] = '60';

	plugin_monitor_check_config();

	expect($GLOBALS['__test_config_options']['monitor_refresh'])->toBe('60');
});

it('reports that no upgrade is pending', function () {
	expect(plugin_monitor_upgrade())->toBeFalse();
});

it('skips the version check on pages that do not need it', function () {
	$_SERVER['PHP_SELF'] = '/graphs.php';

	monitor_check_upgrade();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});

it('updates the stored plugin_config version when it drifts', function () {
	monitor_test_mock_db('db_fetch_cell', 'plugin_config', '0.0.0');

	monitor_check_upgrade();

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	expect($sql)->toContain('ALTER TABLE host');
});
