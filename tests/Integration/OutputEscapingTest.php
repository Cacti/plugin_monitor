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
 | Behavioural coverage lives in tests/Unit, which loads plugin code.       |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 2);

dataset('escaped_outputs', [
	['includes/controller.php', "html_escape(get_request_var('downhosts'))"],
	['includes/controller.php', "html_escape(get_request_var('mute'))"],
	['includes/controller.php', "html_escape(get_request_var('tree'))"],
	['includes/controller.php', "html_escape(get_request_var('site'))"],
	['includes/controller.php', "html_escape(get_request_var('template'))"],
	['includes/controller.php', "html_escape(get_request_var('size'))"],
	['includes/controller.php', "html_escape(get_request_var('trim'))"],
	['includes/render.php',     "rawurlencode(get_request_var('rfilter'))"],
]);

it('escapes request values before printing them', function (string $file, string $pattern) use ($root) {
	expect(file_get_contents($root . '/' . $file))->toContain($pattern);
})->with('escaped_outputs');

dataset('raw_reuse', [
	['includes/controller.php', "get_request_var('downhosts') . '\"><input id=\"mute\" type=\"hidden\" value=\"' . get_request_var('mute')"],
	['includes/controller.php', "get_request_var('tree') . '\"></td>'"],
	['includes/controller.php', "get_request_var('site') . '\"></td>'"],
	['includes/controller.php', "get_request_var('template') . '\"></td>'"],
	['includes/controller.php', "get_request_var('size') . '\"></td>'"],
	['includes/controller.php', "get_request_var('trim') . '\"></td>'"],
	['includes/render.php',     "monitor.php?rfilter=' . get_request_var('rfilter')"],
]);

it('never concatenates a raw request value into markup', function (string $file, string $pattern) use ($root) {
	expect(file_get_contents($root . '/' . $file))->not->toContain($pattern);
})->with('raw_reuse');
