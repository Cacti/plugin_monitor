<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

dataset('raw_request_reuse', [
	'controller downhosts/mute' => ['monitor_controller.php', "get_request_var('downhosts') . '\"><input id=\"mute\" type=\"hidden\" value=\"' . get_request_var('mute')"],
	'controller tree'           => ['monitor_controller.php', "get_request_var('tree') . '\"></td>'"],
	'controller site'           => ['monitor_controller.php', "get_request_var('site') . '\"></td>'"],
	'controller template'       => ['monitor_controller.php', "get_request_var('template') . '\"></td>'"],
	'controller size'           => ['monitor_controller.php', "get_request_var('size') . '\"></td>'"],
	'controller trim'           => ['monitor_controller.php', "get_request_var('trim') . '\"></td>'"],
	'render rfilter'            => ['monitor_render.php', "monitor.php?rfilter=' . get_request_var('rfilter')"],
]);

it('does not reintroduce raw request var reuse', function (string $file, string $pattern) {
	$contents = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

	expect($contents)->not->toBeFalse();
	expect($contents)->not->toContain($pattern);
})->with('raw_request_reuse');
