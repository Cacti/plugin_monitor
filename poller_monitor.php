<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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
*/

/* we are not talking to the browser */
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset ($_SERVER['argv'][0]) || isset ($_SERVER['REQUEST_METHOD']) || isset ($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting(E_ALL);
$dir = dirname(__FILE__);
chdir($dir);

/* record the start time */
$poller_start         = microtime(true);

include('../../include/global.php');
include_once($config['base_path'] . '/lib/reports.php');

global $config, $database_default, $purged_r, $purged_n;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = FALSE;
$force    = FALSE;
$purged_r = 0;
$purged_n = 0;

foreach ($parms as $parameter) {
	@list ($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
		case '-h' :
		case '-v' :
		case '--version' :
		case '--help' :
			display_help();
			exit;
		case '--force' :
			$force = true;
			break;
		case '--debug' :
			$debug = true;
			break;
		default :
			print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
			display_help();
			exit;
	}
}

monitor_debug('Monitor Starting Checks');

$warning_criticality = read_config_option('monitor_warn_criticality');
$alert_criticality   = read_config_option('monitor_alert_criticality');

$lists       = array();
$global_list = array();
$notify_list = array();
$last_time   = date("Y-m-d H:i:s", time() - read_config_option('monitor_resend_frequency') * 60);

if ($warning_criticality > 0 || $alert_criticality > 0) {
	monitor_debug('Monitor Notification Enabled for Devices');
	// Get hosts that are above threshold.  Start with Alert, and then Warning
	if ($alert_criticality) {
		get_hosts_by_list_type('alert', $alert_criticality, $global_list, $notify_list, $lists);
	}

	if ($warning_criticality) {
		get_hosts_by_list_type('warn', $warning_criticality, $global_list, $notify_list, $lists);
	}

	flatten_lists($global_list, $notify_list);

	if (sizeof($global_list) || sizeof($notify_list)) {
		// array of email[list|'g'] = true;
		$notification_emails = get_emails_and_lists($lists);

		// Send out emails to each emails address with all notifications in one
		if (sizeof($notification_emails)) {
			foreach($notification_emails as $email => $lists) {
				monitor_debug('Processing the email address: ' . $email);
				process_email($email, $lists, $global_list, $notify_list);
			}
		}
	}
}else{
	monitor_debug('Both Warning and Alert Notification are Disabled.');
}

function process_email($email, $lists, $global_list, $notify_list) {
	monitor_debug("Into Processing");
	$alert_hosts = array();
	$warn_hosts  = array();

	$criticalities = array(
		0 => __('Disabled'),
		1 => __('Low'),
		2 => __('Medium'),
		3 => __('High'),
		4 => __('Mission Critical')
	);

	foreach($lists as $list) {
		switch($list) {
		case 'g':
			$hosts = array();
			if (isset($global_list['alert'])) {
				$alert_hosts += explode(',', $global_list['alert']);
			}
			if (isset($global_list['warn'])) {
				$warn_hosts += explode(',', $global_list['warn']);
			}
			break;
		default:
			if (isset($notify_list[$list]['alert'])) {
				$alert_hosts = explode(',', $notify_list[$list]['alert']);
			}
			if (isset($notify_list[$list]['warn'])) {
				$warn_hosts = explode(',', $notify_list[$list]['warn']);
			}
			break;
		}
	}

	monitor_debug("Lists Processed");

	if (sizeof($alert_hosts)) {
		$alert_hosts = array_unique($alert_hosts, SORT_NUMERIC);
	}

	if (sizeof($warn_hosts)) {
		$warn_hosts = array_unique($warn_hosts, SORT_NUMERIC);
	}

	monitor_debug("Found " . sizeof($alert_hosts) . " Alert Hosts, and " . sizeof($warn_hosts) . " Warn Hosts");

	if (sizeof($alert_hosts) || sizeof($warn_hosts)) {
		monitor_debug("Formatting Email");
		$freq    = read_config_option('monitor_resend_frequency');
		$subject = __('Cacti Monitor Plugin Ping Threshold Notification');

		$body  = "<h1>" . __('Cacti Monitor Plugin Ping Threshold Notication') . "</h1>";

		$body .= "<p>" . __('The following report will identify Devices that have eclipsed their ping
			latency thresholds.  You are receiving this report due to that you are subscribed for notification
			to a Device associated with the Cacti system located at the following URL below.') . "</p>";

		$body .= "<h2>" . read_config_option('base_url') . "</h2>";

		if ($freq > 0) {
			$body .= "<p>" . __('You will receive notifications every %d minutes if the Device is above its threshold.', $freq) . "</p>";
		}else{
			$body .= "<p>" . __('You will receive notifications every time the Device is above its threshold.') . "</p>";
		}

		if (sizeof($alert_hosts)) {
			$body .= "<p>" . __('The following Devices have breached their Alert Notification Threshold.') . "</p>";
			$body .= "<table style='width:100%;border:1px solid black;padding:4px;margin:2px;><tr>";
			$body .= "<th>Hostname</th><th>Criticality</th><th>Alert Ping</th><th>Cur Ping</th>";
			$body .= "</tr>";

			$hosts = db_fetch_assoc("SELECT * FROM host WHERE id IN(" . implode(',', $alert_hosts) . ")");
			if (sizeof($hosts)) {
				foreach($hosts as $host) {
					$body .= "<tr>";
					$body .= "<td style='text-align:left;'>" . $host['description']  . "</td>";
					$body .= "<td style='text-align:left;'>" . $criticalities[$host['monitor_criticality']]  . "</td>";
					$body .= "<td style='text-align:right;'>" . $host['monitor_alert']  . " ms</td>";
					$body .= "<td style='text-align:right;'>" . round($host['cur_time'],2)  . " ms</td>";
					$body .= "</tr>";
				}
			}
			$body .= "</table>";
		}

		if (sizeof($warn_hosts)) {
			$body .= "<p>" . __('The following Devices have breached their Warning Notification Threshold.') . "</p><br>";

			$body .= "<table style='width:100%;border:1px solid black;padding:4px;margin:2px;><tr>";
			$body .= "<th>Hostname</th><th>Criticality</th><th>Alert Ping</th><th>Cur Ping</th>";
			$body .= "</tr>";

			$hosts = db_fetch_assoc("SELECT * FROM host WHERE id IN(" . implode(',', $warn_hosts) . ")");
			if (sizeof($hosts)) {
				foreach($hosts as $host) {
					$body .= "<tr>";
					$body .= "<td style='text-align:left;'>" . $host['description']  . "</td>";
					$body .= "<td style='text-align:left;'>" . $criticalities[$host['monitor_criticality']]  . "</td>";
					$body .= "<td style='text-align:right;'>" . $host['monitor_warn']  . " ms</td>";
					$body .= "<td style='text-align:right;'>" . round($host['cur_time'],2)  . " ms</td>";
					$body .= "</tr>";
				}
			}
			$body .= "</table>\n";
		}

		$output     = '';
		$report_tag = '';
		$theme      = 'modern';

		monitor_debug("Loading Format File");

		$format_ok = reports_load_format_file(read_config_option('monitor_format_file'), $output, $report_tag, $theme);

		monitor_debug("Format File Loaded, Format is " . ($format_ok ? 'Ok':'Not Ok') . ", Report Tag is $report_tag");

		if ($format_ok) {
			if ($report_tag) {
				$output = str_replace('<REPORT>', $body, $output);
			} else {
				$output = $output . "\n" . $body;
			}
		} else {
			$output = $body;
		}

		monitor_debug("HTML Processed");

		$v = db_fetch_cell('SELECT cacti FROM version');
		$headers['User-Agent'] = 'Cacti-Monitor-v' . $v;

		$from_email = read_config_option('settings_from_email');
		if ($from_email == '') {
			$from_email = 'root@localhost';
		}

		$from_name  = read_config_option('settings_from_name');
		if ($from_name == '') {
			$from_name = 'Cacti Reporting';
		}

		monitor_debug("Sending Email to '$email'");

		$error = mailer(
			array($from_email, $from_name),
			$email,
			'',
			'',
			'',
			$subject,
			$body,
			'Cacti Monitor Plugin Requires an HTML Email Client',
			'',
			$headers
	    );

		monitor_debug("The return from the mailer was '$error'");

		if (strlen($error)) {
            cacti_log("WARNING: Monitor had problems sending Notification Report to '$email'.  The error was '$error'", false, 'MONITOR');
		}else{
			cacti_log("NOTICE: Email Notification Sent to '$email' for " . 
				(sizeof($alert_hosts) ? sizeof($alert_hosts) . ' Alert Notificaitons':'') . 
				(sizeof($warn_hosts) ? (sizeof($alert_hosts) ? ', and ':'') . 
					sizeof($warn_hosts) . ' Warning Notifications':''). '.', false, 'MONITOR');
		}
	}
}

function get_hosts_by_list_type($type, $criticality, &$global_list, &$notify_list, &$lists) {
	global $force;

	$last_time = date("Y-m-d H:i:s", time() - read_config_option('monitor_resend_frequency') * 60);

	$hosts = db_fetch_cell_prepared("SELECT count(*)
		FROM host 
		WHERE status=3 
		AND thold_send_email>0 
		AND monitor_criticality >= ?
		AND cur_time > monitor_$type", array($criticality));

	if ($hosts > 0) {
		$groups = db_fetch_assoc_prepared("SELECT 
			thold_send_email, thold_host_email, GROUP_CONCAT(host.id) AS id
			FROM host
			LEFT JOIN plugin_monitor_notify_history AS nh
			ON host.id=nh.host_id
			WHERE status=3 
			AND thold_send_email>0 
			AND monitor_criticality >= ?
			AND cur_time > monitor_$type " . ($type == "warn" ? " AND cur_time < monitor_alert":"") ."
			AND (notification_time < ? OR notification_time IS NULL)
			GROUP BY thold_host_email, thold_send_email
			ORDER BY thold_host_email, thold_send_email", array($criticality, $last_time));

		if (sizeof($groups)) {
			foreach($groups as $entry) {
				switch($entry['thold_send_email']) {
				case '1': // Global List
					$global_list[$type][] = $entry;
					break;
				case '2': // Notification List
					if ($entry['thold_host_email'] > 0) {
						$notify_list[$type][$entry['thold_host_email']][] = $entry;
						$lists[$entry['thold_host_email']] = $entry['thold_host_email'];
					}
					break;
				case '3': // Both Notification and Global
					$global_list[$type][] = $entry;
					if ($entry['thold_host_email'] > 0) {
						$notify_list[$type][$entry['thold_host_email']][] = $entry;
						$lists[$entry['thold_host_email']] = $entry['thold_host_email'];
					}
				}
			}
		}
	}
}

function flatten_lists(&$global_list, &$notify_list) {
	if (sizeof($global_list)) {
		foreach($global_list as $severity => $list) {
			foreach($list as $item) {
				$new_global[$severity] = (isset($new_global[$severity]) ? $new_global[$severity] . ',':'') . $item['id'];
			}
		}
		$global_list = $new_global;
	}

	if (sizeof($notify_list)) {
		foreach($notify_list as $severity => $lists) {
			foreach($lists as $id => $list) {
				foreach($list as $item) {
					$new_list[$severity][$id] = (isset($new_list[$severity][$id]) ? $new_list[$severity][$id] . ',':'') . $item['id'];
				}
			}
		}
		$notify_list = $new_list;
	}
}

function get_emails_and_lists($lists) {
	$notification_emails = array();

	$global_emails = explode(',', read_config_option('alert_email'));
	foreach($global_emails as $index => $user) {
		if (trim($user) != '') {
			$notification_emails[trim($email)]['global'] = true;
		}
	}

	if (sizeof($lists)) {
		$list_emails = db_fetch_assoc('SELECT id, emails 
			FROM plugin_notification_lists 
			WHERE id IN (' . implode(',', $lists) . ')');

		foreach($list_emails as $email) {
			$emails = explode(',', $email['emails']);
			foreach($emails as $user) {
				if (trim($user) != '') {
					$notification_emails[trim($user)][$email['id']] = true;
				}
			}
		}
	}

	return $notification_emails;
}

function purge_event_records() {
	// Purge old records
	db_execute('DELETE FROM plugin_monitor_notify_history 
		WHERE notification_time<FROM_UNIXTIME(UNIX_TIMESTAMP()-(? * 86400)', 
		array(read_config_option('monitor_log_storage')));
	$purge_n = db_affected_rows();

	db_execute('DELETE FROM plugin_monitor_reboot_history 
		WHERE notification_time<FROM_UNIXTIME(UNIX_TIMESTAMP()-(? * 86400)', 
		array(read_config_option('monitor_log_storage')));
	$purge_r = db_affected_rows();
}

function monitor_debug($message) {
	global $debug;

	if ($debug) {
		echo trim($message) . "\n";
	}
}

/*
 * display_help
 * displays the usage of the function
 */
function display_help() {
	$version = db_fetch_cell('SELECT cacti FROM version');
	print "Cacti Monitor Poller, Version $version, " . COPYRIGHT_YEARS . "\n\n";
	print "usage: poller_monitor.php [--force] [--debug] [--help] [--version]\n\n";
	print "--force       - force execution, e.g. for testing\n";
	print "--debug       - debug execution, e.g. for testing\n\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - display this help message\n";
}
