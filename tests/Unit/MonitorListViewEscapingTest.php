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
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * The List view hands device fields to form_selectable_cell(), which prints
 * its argument as-is, so renderHostList() passes plain device fields through the core escaping cell renderer.
 */

beforeAll(function () {
	// Same output as Cacti's lib/html_utility.php versions of these helpers.
	if (!function_exists('form_selectable_cell')) {
		function form_selectable_cell($contents, $id, $width = '', $style_or_class = '', $title = '') {
			$trace = debug_backtrace(0, 2);
			if (($trace[1]['function'] ?? '') === 'form_selectable_ecell') {
				$GLOBALS['monitor_test_ecell_values'][] = $trace[1]['args'][0];
			}
			print "\t<td>" . $contents . "</td>\n";

			return true;
		}
	}

	if (!function_exists('form_end_row')) {
		function form_end_row() {
			print "</tr>\n";
		}
	}

	if (!function_exists('get_timeinstate')) {
		function get_timeinstate($host) {
			return '1m';
		}
	}

	$source = file_get_contents($GLOBALS['config']['base_path'] . '/lib/html_utility.php');
	if (!preg_match('/^function form_selectable_ecell\([^\n]*\n.*?^}/ms', $source, $definition)) {
		throw new RuntimeException('Cannot load the core escaping cell renderer');
	}
	$path = tempnam(sys_get_temp_dir(), 'monitor-core-cell-');
	try {
		file_put_contents($path, '<?php ' . $definition[0]);
		require $path;
	} finally {
		unlink($path);
	}

	require_once __DIR__ . '/../../includes/render.php';
});

it('escapes device fields in the list view', function () {
	global $criticalities, $iclasses, $thold_counts, $servcheck_counts;

	$criticalities    = [];
	$iclasses         = [];
	$thold_counts     = [];
	$servcheck_counts = [];

	$host = [
		'id'                  => 7,
		'status'              => 1,
		'status_rec_date'     => '2026-01-01 00:00:00',
		'status_fail_date'    => '2026-01-01 00:00:00',
		'description'         => '<b>core</b> & "edge"',
		'location'            => '<i>rack</i> & \'row\'',
		'site_name'           => '<u>HQ</u> & "annex"',
		'monitor_text'        => '<s>note</s> & "text"',
		'monitor_criticality' => 0,
		'availability_method' => 0,
		'availability'        => 100,
	];

	$GLOBALS['monitor_test_ecell_values'] = [];
	$output = renderHostList($host);

	expect($GLOBALS['monitor_test_ecell_values'])->toBe([$host['description'], $host['location'], $host['site_name'], $host['monitor_text']]);

	expect($output)->toContain('&lt;b&gt;core&lt;/b&gt; &amp; &quot;edge&quot;');
	expect($output)->toContain('&lt;i&gt;rack&lt;/i&gt; &amp; &apos;row&apos;');
	expect($output)->toContain('&lt;u&gt;HQ&lt;/u&gt; &amp; &quot;annex&quot;');
	expect($output)->toContain('&lt;s&gt;note&lt;/s&gt; &amp; &quot;text&quot;');

	foreach (['<b>', '<i>', '<u>', '<s>'] as $markup) {
		expect($output)->not->toContain($markup);
	}

	expect($output)->not->toContain('&amp;amp;');
});
