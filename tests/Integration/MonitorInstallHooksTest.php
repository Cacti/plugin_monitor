<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for plugin_monitor_install(): verifies every hook
 * and the realm the plugin depends on at runtime are actually registered,
 * together with the tables and defaults it needs, in a single end-to-end
 * pass.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_registered_hooks']  = array();
	$GLOBALS['__test_registered_realms'] = array();
	$GLOBALS['__test_db_calls']          = array();
	$GLOBALS['__test_config_options']    = array();
});

it('registers every hook monitor depends on, its realm, and provisions its tables and defaults', function () {
	plugin_monitor_install();

	$hooks = array();
	foreach ($GLOBALS['__test_registered_hooks'] as $registered) {
		$hooks[$registered['hook']] = $registered;
	}

	foreach (array('top_header_tabs', 'top_graph_header_tabs', 'top_graph_refresh', 'draw_navigation_text', 'config_form', 'config_settings', 'config_arrays', 'poller_bottom', 'page_head', 'api_device_save', 'device_action_array', 'device_action_execute', 'device_action_prepare', 'device_remove', 'device_filters', 'device_sql_where', 'device_table_bottom') as $expected) {
		expect($hooks)->toHaveKey($expected);
		expect($hooks[$expected]['name'])->toBe('monitor');
	}

	expect($GLOBALS['__test_registered_realms'])->toHaveCount(1);
	expect($GLOBALS['__test_registered_realms'][0]['file'])->toBe('monitor.php');

	expect($GLOBALS['__test_config_options']['monitor_view'])->toBe('default');
	expect($GLOBALS['__test_config_options']['monitor_trim'])->toBe('4000');

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	expect($sql)->toContain('plugin_monitor_notify_history');
});
