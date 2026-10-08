<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression coverage for monitor_device_table_bottom(), the device-list
 * 'device_table_bottom' hook that injects the Criticality filter on legacy
 * Cacti releases. These lock in the select2 change-recursion fix: a bail
 * guard when core already rendered the field, a single namespaced change
 * handler, and a single widget engine (never both at once).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	// The function gates on CACTI_VERSION; the unit bootstrap does not load
	// Cacti's globals, so pin a legacy value that takes the injection path
	// where the fix lives.
	if (!defined('CACTI_VERSION')) {
		define('CACTI_VERSION', '1.2.29');
	}
});

function monitor_render_device_table_bottom(): string {
	ob_start();
	monitor_device_table_bottom();

	return (string) ob_get_clean();
}

it('bails without re-injecting when core already rendered the criticality field', function () {
	$GLOBALS['__test_selected_theme'] = 'modern';

	$html = monitor_render_device_table_bottom();

	expect($html)->toContain("if ($('#criticality').length)");
	expect($html)->toContain('return;');
});

it('injects the criticality select with the requested value marked selected', function () {
	$GLOBALS['__test_selected_theme']         = 'modern';
	$GLOBALS['__test_request']['criticality'] = '2';

	$html = monitor_render_device_table_bottom();

	expect($html)->toContain('<select id="criticality">');
	expect($html)->toContain('<option selected value="2">');
	expect($html)->toContain('<option value="1">');
});

it('wires exactly one namespaced change handler and defines applyFilter', function () {
	$GLOBALS['__test_selected_theme'] = 'modern';

	$html = monitor_render_device_table_bottom();

	expect(substr_count($html, "on('change.monitor'"))->toBe(1);
	expect($html)->toContain("off('change.monitor')");
	expect($html)->toContain('window.applyFilter = function()');
});

it('widgetizes with a single engine, never both, on non-classic themes', function () {
	$GLOBALS['__test_selected_theme'] = 'modern';

	$html = monitor_render_device_table_bottom();

	expect($html)->toContain('if ($.fn.select2 != null)');
	expect($html)->toContain('} else if ($.fn.selectmenu != null)');
});

it('omits the widget block on the classic theme', function () {
	$GLOBALS['__test_selected_theme'] = 'classic';

	$html = monitor_render_device_table_bottom();

	expect($html)->not->toContain('.select2()');
	expect($html)->not->toContain('.selectmenu()');
	expect($html)->toContain('<select id="criticality">');

	unset($GLOBALS['__test_selected_theme']);
});
