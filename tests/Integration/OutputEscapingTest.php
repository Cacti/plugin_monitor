<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Source rules, not behavioural tests.  They match the text of the web     |
 | entry points, so they catch an escaping call being deleted and nothing   |
 | else: a new unescaped sink written with different markup passes.  The    |
 | pages chdir() to the Cacti root and include auth.php, so asserting on    |
 | rendered output needs a live install, which tests/e2e would cover.       |
 |                                                                          |
 | Behavioural coverage lives in tests/unit, which loads plugin code.       |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 2);

dataset('escaped_outputs', [
	['monitor_controller.php', "html_escape(get_request_var('downhosts'))"],
	['monitor_controller.php', "html_escape(get_request_var('mute'))"],
	['monitor_controller.php', "html_escape(get_request_var('tree'))"],
	['monitor_controller.php', "html_escape(get_request_var('site'))"],
	['monitor_controller.php', "html_escape(get_request_var('template'))"],
	['monitor_controller.php', "html_escape(get_request_var('size'))"],
	['monitor_controller.php', "html_escape(get_request_var('trim'))"],
	['monitor_render.php',     "rawurlencode(get_request_var('rfilter'))"],
]);

it('escapes request values before printing them', function (string $file, string $pattern) use ($root) {
	expect(file_get_contents($root . '/' . $file))->toContain($pattern);
})->with('escaped_outputs');

dataset('raw_reuse', [
	['monitor_controller.php', "get_request_var('tree') . '\"></td>'"],
	['monitor_controller.php', "get_request_var('site') . '\"></td>'"],
	['monitor_controller.php', "get_request_var('template') . '\"></td>'"],
	['monitor_controller.php', "get_request_var('size') . '\"></td>'"],
	['monitor_controller.php', "get_request_var('trim') . '\"></td>'"],
	['monitor_render.php',     "monitor.php?rfilter=' . get_request_var('rfilter')"],
]);

it('never concatenates a raw request value into markup', function (string $file, string $pattern) use ($root) {
	expect(file_get_contents($root . '/' . $file))->not->toContain($pattern);
})->with('raw_reuse');
