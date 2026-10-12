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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

$guest_account = true;

chdir('../../');
require_once('./include/auth.php');

set_default_action();

// Record Start Time
$start = microtime(true);

$criticalities = [
	-1 => __('All', 'monitor'),
	0 => __('Disabled', 'monitor'),
	1 => __('Low', 'monitor'),
	2 => __('Medium', 'monitor'),
	3 => __('High', 'monitor'),
	4 => __('Mission Critical', 'monitor')
];

$iclasses = [
	0 => 'deviceUnknown',
	1 => 'deviceDown',
	2 => 'deviceRecovering',
	3 => 'deviceUp',
	4 => 'deviceThreshold',
	5 => 'deviceDownMuted',
	6 => 'deviceUnmonitored',
	7 => 'deviceWarning',
	8 => 'deviceAlert',
	9 => 'deviceThresholdMuted',
	10 => 'deviceServiceCheck',
];

$icolorsdisplay = [
	0 => __('Unknown', 'monitor'),
	1 => __('Down', 'monitor'),
	2 => __('Recovering', 'monitor'),
	3 => __('Up', 'monitor'),
	4 => __('Triggered', 'monitor'),
	9 => __('Triggered (Muted/Acked)', 'monitor'),
	5 => __('Down (Muted/Acked)', 'monitor'),
	6 => __('No Availability Check', 'monitor'),
	7 => __('Warning Ping', 'monitor'),
	8 => __('Alert Ping', 'monitor'),
	10 => __('Service Check Failed', 'monitor'),
];

$classes = [
	'monitor_exsmall'   => __('Extra Small', 'monitor'),
	'monitor_small'     => __('Small', 'monitor'),
	'monitor_medium'    => __('Medium', 'monitor'),
	'monitor_large'     => __('Large', 'monitor'),
	'monitor_exlarge'   => __('Extra Large', 'monitor'),
	'monitor_errorzoom' => __('Zoom', 'monitor')
];

$monitor_status = [
	-1 => __('All Monitored Devices', 'monitor'),
	-2 => __('All Devices', 'monitor'),
	-3 => __('Devices not Monitored', 'monitor'),
	-4 => __('Devices without Thresholds', 'monitor'),
];

// Only offer the service-check scope when the servcheck plugin is installed
// and the user has access to it.
if (api_plugin_is_enabled('servcheck') && api_plugin_user_realm_auth('servcheck_test.php')) {
	$monitor_status[-5] = __('Devices without Service Checks', 'monitor');
}

// Additive event classes for the multi-select Events filter and the Events
// column pills. Keys are stable filter tokens; a device can match several.
$monitor_events = [
	'down'         => __('Down', 'monitor'),
	'recovering'   => __('Recovering', 'monitor'),
	'triggered'    => __('Triggered', 'monitor'),
	'down_ma'      => __('Down (M/A)', 'monitor'),
	'triggered_ma' => __('Triggered (M/A)', 'monitor'),
	'warn_ping'    => __('Warn Ping', 'monitor'),
	'alert_ping'   => __('Alert Ping', 'monitor'),
];

// Only offer the Failed Svc event when the servcheck plugin is installed and
// the user has access to it; otherwise the service-check scope is always empty
// and the option would only ever produce an empty result.
if (api_plugin_is_enabled('servcheck') && api_plugin_user_realm_auth('servcheck_test.php')) {
	$monitor_events['failed_svc'] = __('Failed Svc', 'monitor');
}

$monitor_view_type = [
	'default'  => __('Default', 'monitor'),
	'list'     => __('List', 'monitor'),
	'tiles'    => __('Tiles', 'monitor'),
	'tilesadt' => __('Tiles & Time', 'monitor')
];

$monitor_grouping = [
	'default'  => __('Default', 'monitor'),
	'tree'     => __('Tree', 'monitor'),
	'site'     => __('Site', 'monitor'),
	'template' => __('Device Template', 'monitor')
];

$monitor_trim = [
	0   => __('Default', 'monitor'),
	-1  => __('Full', 'monitor'),
	10  => __('10 Chars', 'monitor'),
	20  => __('20 Chars', 'monitor'),
	30  => __('30 Chars', 'monitor'),
	40  => __('40 Chars', 'monitor'),
	50  => __('50 Chars', 'monitor'),
	75  => __('75 Chars', 'monitor'),
	100 => __('100 Chars', 'monitor'),
];

global $thold_hosts, $servcheck_hosts, $maxchars;

$dozoomrefresh   = false;
$dozoombgndcolor = false;

$maxchars = 12;

$_SESSION['names'] = 0;

if (!isset($_SESSION['monitor_muted_hosts'])) {
	$_SESSION['monitor_muted_hosts'] = [];
}

include_once __DIR__ . '/includes/functions.php';
include_once __DIR__ . '/includes/render.php';
include_once __DIR__ . '/includes/controller.php';

validateRequestVars();

$thold_hosts = checkTholds();

$servcheck_hosts = checkServchecks();

// The per-host Thresholds / Service Checks pill counts are only rendered in the
// List view, so skip these two full grouped scans on every other request
// (tiles/cards, tooltip and dialog AJAX, saves, and card-order updates).
$thold_counts     = [];
$servcheck_counts = [];

if (get_request_var('view') == 'list') {
	$thold_counts = monitorTholdCounts();

	$servcheck_counts = monitorServcheckCounts();
}

switch (get_nfilter_request_var('action')) {
	case 'ajax_status':
		ajaxStatus();

		break;
	case 'ajax_hostpanel':
		ajaxHostPanel();

		break;
	case 'ajax_saveorder':
		saveCardOrder();

		break;
	case 'ajax_mute_all':
		muteAllHosts();
		drawPage();

		break;
	case 'ajax_unmute_all':
		unmuteAllHosts();
		drawPage();

		break;
	case 'dbchange':
		loadDashboardSettings();
		drawPage();

		break;
	case 'remove':
		removeDashboard();
		drawPage();

		break;
	case 'saveDb':
		saveSettings();
		drawPage();

		break;
	case 'save':
		saveSettings();

		break;
	default:
		drawPage();
}

exit;
