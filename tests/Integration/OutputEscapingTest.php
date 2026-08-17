<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Request values reach hidden inputs and query strings.  These match the   |
 | source text rather than running the pages, so they detect an escaping    |
 | call being deleted; they prove nothing about runtime behaviour.  The     |
 | pages chdir() to the Cacti root and include auth.php, so exercising them |
 | needs a live install.                                                    |
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

it('escapes a payload that would break out of a hidden input', function () {
	$payload = '" autofocus onfocus="alert(1)';
	$escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

	expect($escaped)->not->toContain('"')->and($escaped)->toContain('&quot;');
});
