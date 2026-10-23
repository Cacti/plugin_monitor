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
 * its argument as-is, so renderHostList() has to escape them itself.
 */

beforeAll(function () {
	// Same output as Cacti's lib/html_utility.php versions of these helpers.
	if (!function_exists('form_selectable_cell')) {
		function form_selectable_cell($contents, $id, $width = '', $style_or_class = '', $title = '') {
			print "\t<td>" . $contents . "</td>\n";
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

	$output = renderHostList($host);

	expect($output)->toContain('&lt;b&gt;core&lt;/b&gt; &amp; &quot;edge&quot;');
	expect($output)->toContain('&lt;i&gt;rack&lt;/i&gt; &amp; &apos;row&apos;');
	expect($output)->toContain('&lt;u&gt;HQ&lt;/u&gt; &amp; &quot;annex&quot;');
	expect($output)->toContain('&lt;s&gt;note&lt;/s&gt; &amp; &quot;text&quot;');

	foreach (['<b>', '<i>', '<u>', '<s>'] as $markup) {
		expect($output)->not->toContain($markup);
	}

	expect($output)->not->toContain('&amp;amp;');
});
