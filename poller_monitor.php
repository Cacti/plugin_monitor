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
    fwrite(STDERR, 'Monitor plugin requires PHP 8.1.0 or newer. Current runtime: ' . PHP_VERSION . PHP_EOL);
    exit(1);
}

$dir = __DIR__;
chdir($dir);

include_once '../../include/cli_check.php';
include_once $config['base_path'] . '/lib/reports.php';

// let PHP run just as long as it has to
ini_set('max_execution_time', '0');

error_reporting(E_ALL);

const MONITOR_DATE_TIME_FORMAT = 'Y-m-d H:i:s';
const MONITOR_PING_NOTIFICATION_SUBJECT = 'Cacti Monitor Plugin Ping Threshold Notification';
const MONITOR_ALERT_PING_LABEL = 'Alert Ping';
const MONITOR_CURRENT_PING_LABEL = 'Current Ping';

// record the start time
$poller_start = microtime(true);
$start_date   = date(MONITOR_DATE_TIME_FORMAT);

global $config, $database_default;

include_once __DIR__ . '/poller_functions.php';

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;

if (cacti_sizeof($parms)) {
    foreach ($parms as $parameter) {
        if (strpos($parameter, '=')) {
            [$arg, $value] = explode('=', $parameter);
        } else {
            $arg   = $parameter;
            $value = '';
        }

        switch ($arg) {
            case '--version':
            case '-V':
            case '-v':
                displayVersion();
                exit;
            case '--help':
            case '-H':
            case '-h':
                displayHelp();
                exit;
            case '--debug':
                $debug = true;

                break;
            default:
                print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
                displayHelp();
                exit;
        }
    }
}

monitorDebug('Monitor Starting Checks');

[$reboots, $recent_down] = monitorUptimeChecker();

$warning_criticality = read_config_option('monitor_warn_criticality');
$alert_criticality   = read_config_option('monitor_alert_criticality');

$lists         = [];
$notifications = 0;
$global_list   = [];
$notify_list   = [];

if ($warning_criticality > 0 || $alert_criticality > 0) {
    monitorDebug('Monitor Notification Enabled for Devices');

    // Get hosts that are above threshold. Start with Alert, and then Warning.
    if ($alert_criticality) {
        getHostsByListType('alert', $alert_criticality, $global_list, $notify_list, $lists);
    }

    if ($warning_criticality) {
        getHostsByListType('warn', $warning_criticality, $global_list, $notify_list, $lists);
    }

    flattenLists($global_list, $notify_list);

    monitorDebug('Lists Flattened there are ' . sizeof($global_list) . ' Global Notifications and ' . sizeof($notify_list) . ' Notification List Notifications.');

    if (strlen(read_config_option('alert_email')) == 0) {
        monitorDebug('WARNING: No Global List Defined.  Please set under Settings -> Thresholds');
        cacti_log('WARNING: No Global Notification List defined.  Please set under Settings -> Thresholds', false, 'MONITOR');
    }

    if (cacti_sizeof($global_list) || sizeof($notify_list)) {
        // array of email[list|'g'] = true;
        $notification_emails = getEmailsAndLists($lists);

        // Send out emails to each emails address with all notifications in one
        if (cacti_sizeof($notification_emails)) {
            foreach ($notification_emails as $email => $lists) {
                monitorDebug('Processing the email address: ' . $email);
                processEmail($email, $lists, $global_list, $notify_list);

                $notifications++;
            }
        }
    }
} else {
    monitorDebug('Both Warning and Alert Notification are Disabled.');
}

[$purge_n, $purge_r] = purgeEventRecords();

$poller_end = microtime(true);

$stats =
    'Time:' . round($poller_end - $poller_start, 2) .
    ' Reboots:' . $reboots .
    ' DownDevices:' . $recent_down .
    ' Notifications:' . $notifications .
    ' Purges:' . ($purge_n + $purge_r);

cacti_log('MONITOR STATS: ' . $stats, false, 'SYSTEM');
set_config_option('stats_monitor', $stats);

exit;
