<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for monitor_config_settings() in setup.php, including
 * verification that monitor_list labels register under the monitor domain.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_request']        = array();
	$GLOBALS['__test_config_options'] = array();
	$GLOBALS['__test_current_page']   = 'settings.php';
});

it('registers the monitor settings tab including monitor_list', function () {
	global $tabs, $formats, $settings, $criticalities, $page_refresh_interval, $settings_user, $tabs_graphs;

	$tabs                  = array();
	$formats               = array();
	$settings              = array();
	$criticalities         = array();
	$page_refresh_interval = array(60 => '1 Minute');
	$settings_user         = array();
	$tabs_graphs           = array();

	monitor_config_settings();

	expect($tabs)->toHaveKey('monitor');
	expect($settings['monitor'])->toHaveKey('monitor_list');
	expect($settings['monitor']['monitor_list']['friendly_name'])->toBe('Notification List');
	expect($settings['monitor']['monitor_list']['description'])->toContain('Notification List');
});

it('uses the monitor text domain for monitor_list labels in setup.php', function () {
	$source = plugin_test_read_source('setup.php');

	expect($source)->toContain("__('Notification List', 'monitor')");
	expect($source)->not->toContain("__('Notification List', 'thold')");
	expect($source)->not->toContain("'thold'");
});
