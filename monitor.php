<?php

declare(strict_types=1);

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

if (PHP_VERSION_ID < 80100) {
    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    header($protocol . ' 500 Internal Server Error', true, 500);
    print 'Monitor plugin requires PHP 8.1.0 or newer. Current runtime: ' . PHP_VERSION;
    exit;
}

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

set_default_action();

// Record Start Time
$start = microtime(true);

$criticalities = [
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
    -2 => __('All Devices', 'monitor'),
    -1 => __('All Monitored Devices', 'monitor'),
    0  => __('Not Up', 'monitor'),
    1  => __('Not Up or Triggered', 'monitor'),
    2  => __('Not Up, Triggered or Breached', 'monitor'),
    -4 => __('Devices without Thresholds', 'monitor'),
    -3 => __('Devices not Monitored', 'monitor'),
];

$monitor_view_type = [
    'default'  => __('Default', 'monitor'),
    'list'     => __('List', 'monitor'),
    'names'    => __('Names only', 'monitor'),
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

global $thold_hosts, $maxchars;

$dozoomrefresh   = false;
$dozoombgndcolor = false;

$maxchars = 12;

$_SESSION['names'] = 0;

if (!isset($_SESSION['monitor_muted_hosts'])) {
    $_SESSION['monitor_muted_hosts'] = [];
}


include_once __DIR__ . '/db_functions.php';
include_once __DIR__ . '/monitor_render.php';
include_once __DIR__ . '/monitor_controller.php';

validateRequestVars();

if (!db_column_exists('host', 'monitor_icon')) {
    monitorSetupTable();
}

$thold_hosts = checkTholds();

switch (get_nfilter_request_var('action')) {
    case 'ajax_status':
        ajaxStatus();

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
