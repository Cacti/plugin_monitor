<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

dataset('output_hardening', [
	'controller downhosts' => ['includes/controller.php', "html_escape(get_request_var('downhosts'))"],
	'controller mute'      => ['includes/controller.php', "html_escape(get_request_var('mute'))"],
	'controller tree'      => ['includes/controller.php', "html_escape(get_request_var('tree'))"],
	'controller site'      => ['includes/controller.php', "html_escape(get_request_var('site'))"],
	'controller template'  => ['includes/controller.php', "html_escape(get_request_var('template'))"],
	'controller size'      => ['includes/controller.php', "html_escape(get_request_var('size'))"],
	'controller trim'      => ['includes/controller.php', "html_escape(get_request_var('trim'))"],
	'render rfilter'       => ['includes/render.php', "rawurlencode(get_request_var('rfilter'))"],
]);

it('keeps expected output hardening in place', function (string $file, string $pattern) {
	$contents = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

	expect($contents)->not->toBeFalse();
	expect($contents)->toContain($pattern);
})->with('output_hardening');
