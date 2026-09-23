<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_monitor_version() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

it('parses the plugin INFO file into an info array', function () {
	$info = plugin_monitor_version();

	expect($info)->toBeArray();
	expect($info)->toHaveKey('name');
	expect($info)->toHaveKey('version');
	expect($info['name'])->toBe('monitor');
});
