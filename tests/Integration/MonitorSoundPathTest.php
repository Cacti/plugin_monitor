<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

it('resolves the alert sound against the plugin sounds directory', function () {
	$root     = dirname(__DIR__, 2);
	$contents = file_get_contents($root . '/includes/controller.php');

	expect($contents)->not->toBeFalse();
	expect($contents)->toContain("dirname(__DIR__) . '/sounds/'");
	expect($contents)->not->toContain("__DIR__ . '/sounds/'");
	expect(is_dir($root . '/sounds'))->toBeTrue();
	expect(is_dir($root . '/includes/sounds'))->toBeFalse();
});
