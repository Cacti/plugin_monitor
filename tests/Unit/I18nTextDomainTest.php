<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+

/*
 * Every translation helper call in the plugin's own PHP files must pass the
 * 'monitor' text domain, so its strings are looked up in this plugin's catalog
 * and not silently in Cacti's core one. The scan itself lives in
 * tests/bin/check-i18n-domain.php; it uses PHP's tokenizer and needs no Cacti
 * checkout.
 */

require_once __DIR__ . '/../bin/check-i18n-domain.php';

it('passes the monitor text domain to every translation helper call', function () {
	/*
	 * Calls that already lacked the domain before this check existed, as
	 * file => number of calls. They are listed so the check passes without
	 * widening the change that introduced it. The count must match exactly:
	 * fixing a call without lowering its count here fails the test, so the
	 * list can only shrink. Do not add entries for new code.
	 */
	$known = [
		// nav bar and list labels with no domain (one is the core 'Devices' label)
		'includes/render.php' => 2,
		// report e-mail paragraph
		'poller_functions.php' => 1,
		// core role name passed to auth_augment_roles()
		'setup.php' => 1,
	];

	$found = [];

	foreach (i18n_domain_offenders(dirname(__DIR__, 2), 'monitor') as $offender) {
		$found[$offender['file']][] = $offender['file'] . ':' . $offender['line'] . ' ' . $offender['call'] . ': ' . $offender['reason'];
	}

	$unexpected = [];

	foreach ($found as $file => $lines) {
		if (count($lines) !== ($known[$file] ?? 0)) {
			$unexpected = array_merge($unexpected, $lines);
		}
	}

	foreach ($known as $file => $count) {
		if (!isset($found[$file])) {
			$unexpected[] = $file . ': expected ' . $count . ' known calls without the text domain, found none; remove it from the list';
		}
	}

	expect($unexpected)->toBe([]);
});
