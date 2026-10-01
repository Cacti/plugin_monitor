<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_monitor_page_head()'s stylesheet selection in
 * setup.php, including the per-theme override loaded from css/<theme>.css.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

afterEach(function () {
	unset($GLOBALS['__test_selected_theme']);
});

it('links the per-theme stylesheet from css/ when it exists', function () {
	$restore = $GLOBALS['config']['base_path'];
	$base    = sys_get_temp_dir() . '/monitor-ph-' . uniqid();
	mkdir($base . '/plugins/monitor/css', 0777, true);
	file_put_contents($base . '/plugins/monitor/css/modern.css', '');
	$GLOBALS['__test_selected_theme'] = 'modern';
	$GLOBALS['config']['base_path']   = $base;

	ob_start();

	try {
		plugin_monitor_page_head();
	} finally {
		$output = ob_get_clean();
		$GLOBALS['config']['base_path'] = $restore;
	}

	expect($output)->toContain('plugins/monitor/css/modern.css');
});

it('emits only the base stylesheet when no per-theme override exists', function () {
	$restore = $GLOBALS['config']['base_path'];
	$base    = sys_get_temp_dir() . '/monitor-ph-' . uniqid();
	mkdir($base . '/plugins/monitor/css', 0777, true);
	$GLOBALS['__test_selected_theme'] = 'no-such-theme';
	$GLOBALS['config']['base_path']   = $base;

	ob_start();

	try {
		plugin_monitor_page_head();
	} finally {
		$output = ob_get_clean();
		$GLOBALS['config']['base_path'] = $restore;
	}

	expect($output)->toContain('plugins/monitor/css/monitor.css');
});
