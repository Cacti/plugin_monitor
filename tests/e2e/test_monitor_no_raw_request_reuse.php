<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$checks = array(
	__DIR__ . '/../../monitor_controller.php' => array(
		"get_request_var('downhosts') . '\"><input id=\"mute\" type=\"hidden\" value=\"' . get_request_var('mute')",
		"get_request_var('tree') . '\"></td>'",
		"get_request_var('site') . '\"></td>'",
		"get_request_var('template') . '\"></td>'",
		"get_request_var('size') . '\"></td>'",
		"get_request_var('trim') . '\"></td>'",
	),
	__DIR__ . '/../../monitor_render.php' => array(
		"monitor.php?rfilter=' . get_request_var('rfilter')",
	),
);

foreach ($checks as $path => $patterns) {
	$contents = file_get_contents($path);

	if ($contents === false) {
		fwrite(STDERR, "Unable to read {$path}\n");
		exit(1);
	}

	foreach ($patterns as $pattern) {
		if (strpos($contents, $pattern) !== false) {
			fwrite(STDERR, "Raw request reuse remains: {$pattern}\n");
			exit(1);
		}
	}
}

print "OK\n";
