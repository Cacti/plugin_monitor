<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for monitor_config_settings() in setup.php. Text domain usage
 * is covered for the whole plugin by I18nTextDomainTest.php.
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
