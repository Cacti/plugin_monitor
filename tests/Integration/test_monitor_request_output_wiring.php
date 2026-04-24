<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$checks = [
	__DIR__ . '/../../monitor_controller.php' => [
		"html_escape(get_request_var('downhosts'))",
		"html_escape(get_request_var('mute'))",
		"html_escape(get_request_var('tree'))",
		"html_escape(get_request_var('site'))",
		"html_escape(get_request_var('template'))",
		"html_escape(get_request_var('size'))",
		"html_escape(get_request_var('trim'))",
	],
	__DIR__ . '/../../monitor_render.php' => [
		"rawurlencode(get_request_var('rfilter'))",
	],
];

foreach ($checks as $path => $patterns) {
	$contents = file_get_contents($path);

	if ($contents === false) {
		fwrite(STDERR, "Unable to read {$path}\n");
		exit(1);
	}

	foreach ($patterns as $pattern) {
		if (strpos($contents, $pattern) === false) {
			fwrite(STDERR, "Missing expected output hardening: {$pattern}\n");
			exit(1);
		}
	}
}

print "OK\n";
