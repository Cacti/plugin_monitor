<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the device-list filter/action/removal hooks in
 * setup.php: monitor_device_filters(), monitor_device_sql_where(),
 * monitor_device_action_array(), monitor_device_remove(),
 * monitor_draw_navigation_text(), and monitor_get_default().
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']       = array();
	$GLOBALS['__test_request']        = array();
	$GLOBALS['__test_config_options'] = array();
});

it('adds a criticality drop-down filter without disturbing existing filters', function () {
	$filters = monitor_device_filters(array('other' => array('friendly_name' => 'Other')));

	expect($filters)->toHaveKey('other');
	expect($filters)->toHaveKey('criticality');
	expect($filters['criticality']['default'])->toBe('-1');
});

it('does not add a criticality clause when criticality is -1 (any)', function () {
	$GLOBALS['__test_request']['criticality'] = '-1';

	expect(monitor_device_sql_where(''))->toBe('');
});

it('adds a criticality clause when a specific criticality is selected', function () {
	$GLOBALS['__test_request']['criticality'] = '2';

	expect(monitor_device_sql_where(''))->toBe('WHERE  monitor_criticality = 2');
	expect(monitor_device_sql_where('WHERE host_status = 1'))->toBe('WHERE host_status = 1 AND  monitor_criticality = 2');
});

it('adds the monitoring actions to the device action dropdown', function () {
	$actions = monitor_device_action_array(array('delete' => 'Delete'));

	expect($actions)->toHaveKey('delete');
	expect($actions)->toHaveKey('monitor_settings');
	expect($actions)->toHaveKey('monitor_enable');
	expect($actions)->toHaveKey('monitor_disable');
});

it('removes every per-device record on device_remove', function () {
	$result = monitor_device_remove(array(3, 4));

	expect($GLOBALS['__test_db_calls'])->toHaveCount(3);
	expect($result)->toBe(array(3, 4));

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		expect($call['sql'])->toContain('IN(3,4)');
	}
});

it('adds the monitor breadcrumb entry without disturbing existing ones', function () {
	$nav = monitor_draw_navigation_text(array('other.php:' => array('title' => 'Other')));

	expect($nav)->toHaveKey('other.php:');
	expect($nav)->toHaveKey('monitor.php:');
});

it('reads the default monitor state only for a new (unsaved) host', function () {
	$GLOBALS['__test_config_options']['monitor_new_enabled'] = 'on';

	expect(monitor_get_default(0))->toBe('on');
	expect(monitor_get_default(-1))->toBe('on');
	expect(monitor_get_default(5))->toBe('');
});
