<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.    |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for monitor_legend(): one rounded .monitorLegendItem chip per
 * status, and the container's --monitor-chip-min variable sized to the longest
 * label.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';
});

it('sizes the monitor legend chips to the longest label', function () {
	$iclasses       = ['deviceUp', 'deviceDown', 'deviceRecovering'];
	$icolorsdisplay = ['Up', 'Down', 'Recovering'];

	$expected = 0;
	foreach ($icolorsdisplay as $label) {
		$expected = max($expected, mb_strlen($label));
	}

	ob_start();
	monitor_legend($iclasses, $icolorsdisplay);
	$output = ob_get_clean();

	expect($output)->toContain("<div class='monitorLegend' style='--monitor-chip-min: calc(" . $expected . "ch + 1.5rem)'>");
	expect(substr_count($output, 'monitorLegendItem'))->toBe(count($iclasses));

	foreach ($iclasses as $index => $class) {
		expect($output)->toContain("<div class='monitorLegendItem {$class}Full'>" . $icolorsdisplay[$index] . '</div>');
	}
});
