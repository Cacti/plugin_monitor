<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2021 The Cacti Group                                 |
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

$dir = dirname(__FILE__);
chdir($dir);

include('../../include/cli_check.php');
include_once($config['base_path'] . '/lib/reports.php');

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting(E_ALL);

/* record the start time */
$poller_start = microtime(true);
$start_date   = date('Y-m-d H:i:s');

global $config, $database_default, $purged_r, $purged_n;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = false;
$force    = false;
$purged_r = 0;
$purged_n = 0;

if (sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--version' :
			case '-V' :
			case '-v' :
				display_version();
				exit;
			case '--help' :
			case '-H' :
			case '-h' :
				display_help();
				exit;
			case '--force' :
				$force = true;
				break;
			case '--debug' :
				$debug = true;
				break;
			default :
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit;
		}
	}
}

monitor_debug('Monitor Starting Checks');

list($reboots, $recent_down) = monitor_uptime_checker();

$warning_criticality = read_config_option('monitor_warn_criticality');
$alert_criticality   = read_config_option('monitor_alert_criticality');

$lists               = array();
$notifications       = 0;
$global_list         = array();
$notify_list         = array();
$last_time           = date('Y-m-d H:i:s', time() - read_config_option('monitor_resend_frequency') * 60);

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

	monitor_debug('Lists Flattened there are ' . sizeof($global_list) . ' Global Notifications and ' . sizeof($notify_list) . ' Notification List Notifications.');

	if (strlen(read_config_option('alert_email')) == 0) {
		monitor_debug('WARNING: No Global List Defined.  Please set under Settings -> Thresholds');
		cacti_log('WARNING: No Global Notification List defined.  Please set under Settings -> Thresholds', false, 'MONITOR');
	}

	if (sizeof($global_list) || sizeof($notify_list)) {
		// array of email[list|'g'] = true;
		$notification_emails = get_emails_and_lists($lists);

		// Send out emails to each emails address with all notifications in one
		if (sizeof($notification_emails)) {
			foreach ($notification_emails as $email => $lists) {
				monitor_debug('Processing the email address: ' . $email);
				process_email($email, $lists, $global_list, $notify_list);

				$notifications++;
			}
		}
	}
} else {
	monitor_debug('Both Warning and Alert Notification are Disabled.');
}

list($purge_n, $purge_r) = purge_event_records();

$poller_end = microtime(true);

$stats =
	'Time:'           . round($poller_end-$poller_start, 4) .
	' Reboots:'       . $reboots .
	' DownDevices:'   . $recent_down .
	' Notifications:' . $notifications .
	' Purges:'        . ($purge_n + $purge_r);

cacti_log('MONITOR STATS: ' . $stats, false, 'SYSTEM');
set_config_option('stats_monitor', $stats);

exit;

function monitor_addemails(&$reboot_emails, $alert_emails, $host_id) {
	if (sizeof($alert_emails)) {
		foreach ($alert_emails as $email) {
			$reboot_emails[trim(strtolower($email))][$host_id] = $host_id;
		}
	}
}

function monitor_addnotificationlist(&$reboot_emails, $notify_list, $host_id, $notification_lists) {
	if ($notify_list > 0) {
		if (isset($notification_lists[$notify_list])) {
			$emails = explode(',', $notification_lists[$notify_list]);
			monitor_addemails($reboot_emails, $emails, $host_id);
		}
	}
}

function monitor_uptime_checker() {
	monitor_debug('Checking for Uptime of Devices');

	$start = date('Y-m-d H:i:s');

	$reboot_emails = array();
	$alert_emails  = explode(',', read_config_option('alert_email'));

	// Remove unneeded device records in associated tables
	$removed_hosts = db_fetch_assoc('SELECT mu.host_id
		FROM plugin_monitor_uptime AS mu
		LEFT JOIN host AS h
		ON h.id = mu.host_id
		WHERE h.id IS NULL');

	if (cacti_sizeof($removed_hosts)) {
		db_execute('DELETE mu
			FROM plugin_monitor_uptime AS mu
			LEFT JOIN host AS h
			ON h.id = mu.host_id
			WHERE h.id IS NULL');
	}

	$removed_hosts = db_fetch_assoc('SELECT mu.host_id
		FROM plugin_monitor_reboot_history AS mu
		LEFT JOIN host AS h
		ON h.id = mu.host_id
		WHERE h.id IS NULL');

	if (cacti_sizeof($removed_hosts)) {
		db_execute('DELETE mu
			FROM plugin_monitor_reboot_history AS mu
			LEFT JOIN host AS h
			ON h.id = mu.host_id
			WHERE h.id IS NULL');
	}

	// Get the rebooted devices
	$rebooted_hosts = db_fetch_assoc('SELECT h.id, h.description,
		h.hostname, h.snmp_sysUpTimeInstance, mu.uptime
		FROM host AS h
		LEFT JOIN plugin_monitor_uptime AS mu
		ON h.id = mu.host_id
		WHERE h.snmp_version > 0
		AND status IN (2,3)
		AND h.deleted = ""
		AND (mu.uptime IS NULL OR mu.uptime > h.snmp_sysUpTimeInstance)
		AND h.snmp_sysUpTimeInstance > 0');

	if (cacti_sizeof($rebooted_hosts)) {
		$notification_lists = array_rekey(
			db_fetch_assoc('SELECT id, emails
				FROM plugin_notification_lists
				ORDER BY id'),
			'id', 'emails'
		);

		$monitor_list  = read_config_option('monitor_list');
		$monitor_thold = read_config_option('monitor_reboot_thold');

		foreach ($rebooted_hosts as $host) {
			db_execute_prepared('INSERT INTO plugin_monitor_reboot_history
				(host_id, reboot_time)
				VALUES (?, ?)',
				array($host['id'], date('Y-m-d H:i:s', time()-$host['snmp_sysUpTimeInstance'])));

			monitor_addnotificationlist($reboot_emails, $monitor_list, $host['id'], $notification_lists);

			if ($monitor_thold == 'on') {
				$notify = db_fetch_row_prepared('SELECT thold_send_email, thold_host_email
					FROM host
					WHERE id = ?',
					array($host['id']));

				if (cacti_sizeof($notify)) {
					switch($notify['thold_send_email']) {
						case '0': // Disabled

							break;
						case '1': // Global List
							monitor_addemails($reboot_emails, $alert_emails, $host['id']);

							break;
						case '2': // Nofitication List
							monitor_addnotificationlist($reboot_emails, $notify['thold_host_email'],
								$host['id'], $notification_lists);

							break;
						case '3': // Both Global and Nofication list
							monitor_addemails($reboot_emails, $alert_emails, $host['id']);
							monitor_addnotificationlist($reboot_emails, $notify['thold_host_email'],
								$host['id'], $notification_lists);

							break;
					}
				}
			}
		}

		if (sizeof($reboot_emails)) {
			foreach ($reboot_emails as $email => $hosts) {
				if ($email != '') {
					monitor_debug('Processing the Email address: ' . $email);
					process_reboot_email($email, $hosts);
				} else {
					monitor_debug('Unable to process reboot notification due to empty Email address.');
				}
			}
		}
	}

	// Freshen the uptimes
	db_execute('REPLACE INTO plugin_monitor_uptime
		(host_id, uptime)
		SELECT id, snmp_sysUpTimeInstance
		FROM host
		WHERE snmp_version > 0
		AND status IN(2,3)
		AND deleted = ""
		AND snmp_sysUpTimeInstance > 0');

	// Log Recently Down
	db_execute('INSERT IGNORE INTO plugin_monitor_notify_history
		(host_id, notify_type, notification_time, notes)
		SELECT h.id, "3" AS notify_type, status_fail_date AS notification_time, status_last_error AS notes
		FROM host AS h
		WHERE status = 1
		AND deleted = ""
		AND status_event_count = 1');

	$recent = db_affected_rows();

	return array(sizeof($rebooted_hosts), $recent);
}

function process_reboot_email($email, $hosts) {
	monitor_debug("Reboot Processing for $email starting");

	$body_txt = '';

	$body  = '<table class="report_table">' . PHP_EOL;
	$body .= '<tr class="header_row">' . PHP_EOL;

	$body .=
		'<th class="left">' . __('Description', 'monitor') . '</th>' .
		'<th class="left">' . __('Hostname', 'monitor')    . '</th>' . PHP_EOL;

	$body .= '</tr>' . PHP_EOL;

	foreach ($hosts as $host) {
		$host = db_fetch_row_prepared('SELECT description, hostname
			FROM host
			WHERE id = ?',
			array($host));

		if (sizeof($host)) {
			$body .= '<tr>' .
				'<td class="left">' . $host['description'] . '</td>' .
				'<td class="left">' . $host['hostname']    . '</td>' .
				'</tr>' . PHP_EOL;

			$body_txt .=
				__('Description: ', 'monitor') . $host['description'] . PHP_EOL .
				__('Hostname: ', 'monitor')    . $host['hostname']    . PHP_EOL . PHP_EOL;
		}
	}

	$body .= '</table>' . PHP_EOL;

	$subject = read_config_option('monitor_subject');
	$output  = read_config_option('monitor_body');
	$output  = str_replace('<DETAILS>', $body, $output);

	if (strpos($output, '<DETAILS>') !== false) {
		$toutput = str_replace('<DETAILS>', $body_txt, $output);
	} else {
		$toutput = $body_txt;
	}

	if (read_config_option('monitor_reboot_notify') == 'on') {
		$report_tag = '';
		$theme      = 'modern';

		monitor_debug('Loading Format File');

		$format_ok = reports_load_format_file(read_config_option('monitor_format_file'), $output, $report_tag, $theme);

		monitor_debug('Format File Loaded, Format is ' . ($format_ok ? 'Ok':'Not Ok') . ', Report Tag is ' . $report_tag);

		if ($format_ok) {
			if ($report_tag) {
				$output = str_replace('<REPORT>', $body, $output);
			} else {
				$output = $output . PHP_EOL . $body;
			}
		} else {
			$output = $body;
		}

		monitor_debug('HTML Processed');

		if (defined('CACTI_VERSION')) {
			$v = CACTI_VERSION;
		} else {
			$v = get_cacti_version();
		}

		$headers['User-Agent'] = 'Cacti-Monitor-v' . $v;

		$status = 'Reboot Notifications';

		process_send_email($email, $subject, $output, $toutput, $headers, $status);
	}
}

function process_email($email, $lists, $global_list, $notify_list) {
	global $config;

	monitor_debug('Into Processing');

	$alert_hosts = array();
	$warn_hosts  = array();

	$criticalities = array(
		0 => __('Disabled', 'monnitor'),
		1 => __('Low', 'monnitor'),
		2 => __('Medium', 'monnitor'),
		3 => __('High', 'monnitor'),
		4 => __('Mission Critical', 'monnitor')
	);

	foreach ($lists as $list) {
		switch($list) {
		case 'global':
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

	monitor_debug('Lists Processed');

	if (sizeof($alert_hosts)) {
		$alert_hosts = array_unique($alert_hosts, SORT_NUMERIC);

		log_messages('alert', $alert_hosts);
	}

	if (sizeof($warn_hosts)) {
		$warn_hosts = array_unique($warn_hosts, SORT_NUMERIC);

		log_messages('warn', $alert_hosts);
	}

	monitor_debug('Found ' . sizeof($alert_hosts) . ' Alert Hosts, and ' . sizeof($warn_hosts) . ' Warn Hosts');

	if (sizeof($alert_hosts) || sizeof($warn_hosts)) {
		monitor_debug('Formatting Email');

		$freq    = read_config_option('monitor_resend_frequency');
		$subject = __('Cacti Monitor Plugin Ping Threshold Notification', 'monitor');

		$body = '<h1>' . __('Cacti Monitor Plugin Ping Threshold Notification', 'monitor') . '</h1>' . PHP_EOL;
		$body_txt = __('Cacti Monitor Plugin Ping Threshold Notification', 'monitor') . PHP_EOL;

		$body .= '<p>' . __('The following report will identify Devices that have eclipsed their ping latency thresholds.  You are receiving this report since you are subscribed to a Device associated with the Cacti system located at the following URL below.') . '</p>' . PHP_EOL;

		$body_txt .= __('The following report will identify Devices that have eclipsed their ping latency thresholds.  You are receiving this report since you are subscribed to a Device associated with the Cacti system located at the following URL below.') . PHP_EOL;

		$body .= '<h2><a href="' . read_config_option('base_url') . '">Cacti Monitoring Site</a></h2>' . PHP_EOL;

		$body_txt .= __('Cacti Monitoring Site', 'monitor') . PHP_EOL;

		if ($freq > 0) {
			$body .= '<p>' . __('You will receive notifications every %d minutes if the Device is above its threshold.', $freq, 'monitor') . '</p>' . PHP_EOL;

			$body_txt .= __('You will receive notifications every %d minutes if the Device is above its threshold.', $freq, 'monitor') . PHP_EOL;
		} else {
			$body .= '<p>' . __('You will receive notifications every time the Device is above its threshold.', 'monitor') . '</p>' . PHP_EOL;

			$body_txt .= __('You will receive notifications every time the Device is above its threshold.', 'monitor') . PHP_EOL;
		}

		if (sizeof($alert_hosts)) {
			$body .= '<p>' . __('The following Devices have breached their Alert Notification Threshold.', 'monitor') . '</p>' . PHP_EOL;

			$body_txt .= __('The following Devices have breached their Alert Notification Threshold.', 'monitor') . PHP_EOL;

			$body .= '<table class="report_table">' . PHP_EOL;
			$body .= '<tr class="header_row">' . PHP_EOL;

			$body .=
				'<th class="left">'  . __('Hostname', 'monitor')     . '</th>' .
				'<th class="left">'  . __('Criticality', 'monitor')  . '</th>' .
				'<th class="right">' . __('Alert Ping', 'monitor')   . '</th>' .
				'<th class="right">' . __('Current Ping', 'monitor') . '</th>' . PHP_EOL;

			$body_txt .=
				__('Hostname', 'monitor')     . "\t" .
				__('Criticality', 'monitor')  . "\t" .
				__('Alert Ping', 'monitor')   . "\t" .
				__('Current Ping', 'monitor') . PHP_EOL;

			$body .= '</tr>' . PHP_EOL;

			$hosts = db_fetch_assoc('SELECT *
				FROM host
				WHERE id IN(' . implode(',', $alert_hosts) . ')
				AND deleted = ""');

			if (cacti_sizeof($hosts)) {
				foreach ($hosts as $host) {
					$body .= '<tr>' . PHP_EOL;
					$body .= '<td class="left"><a class="hyperLink" href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . $host['description']  . '</a></td>' . PHP_EOL;

					$body .= '<td class="left">' . $criticalities[$host['monitor_criticality']]  . '</td>'    . PHP_EOL;
					$body .= '<td class="right">' . number_format_i18n($host['monitor_alert'],2) . ' ms</td>' . PHP_EOL;
					$body .= '<td class="right">' . number_format_i18n($host['cur_time'],2)      . ' ms</td>' . PHP_EOL;

					$body_txt .=
						$host['description'] . "\t" .
						$criticalities[$host['monitor_criticality']] . "\t" .
						number_format_i18n($host['monitor_alert'],2) . " ms\t" .
						number_format_i18n($host['cur_time'],2)      . " ms" . PHP_EOL;

					$body .= '</tr>' . PHP_EOL;
				}
			}

			$body .= '</table>' . PHP_EOL;
		}

		if (sizeof($warn_hosts)) {
			$body .= '<p>' . __('The following Devices have breached their Warning Notification Threshold.', 'monitor') . '</p>' . PHP_EOL;

			$body_txt .= __('The following Devices have breached their Warning Notification Threshold.', 'monitor') . PHP_EOL;

			$body .= '<table class="report_table">' . PHP_EOL;
			$body .= '<tr class="header_row">' . PHP_EOL;

			$body .=
				'<th class="left">'  . __('Hostname', 'monitor')     . '</th>' .
				'<th class="left">'  . __('Criticality', 'monitor')  . '</th>' .
				'<th class="right">' . __('Alert Ping', 'monitor')   . '</th>' .
				'<th class="right">' . __('Current Ping', 'monitor') . '</th>' . PHP_EOL;

			$body_txt .=
				__('Hostname', 'monitor')     . "\t" .
				__('Criticality', 'monitor')  . "\t" .
				__('Alert Ping', 'monitor')   . "\t" .
				__('Current Ping', 'monitor') . PHP_EOL;

			$body .= '</tr>' . PHP_EOL;

			$hosts = db_fetch_assoc('SELECT *
				FROM host
				WHERE id IN(' . implode(',', $warn_hosts) . ')
				AND deleted = ""');

			if (sizeof($hosts)) {
				foreach ($hosts as $host) {
					$body .= '<tr>' . PHP_EOL;
					$body .= '<td class="left"><a class="hyperLink" href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . $host['description']  . '</a></td>' . PHP_EOL;

					$body .= '<td class="left">' . $criticalities[$host['monitor_criticality']]  . '</td>'    . PHP_EOL;
					$body .= '<td class="right">' . number_format_i18n($host['monitor_warn'],2)  . ' ms</td>' . PHP_EOL;
					$body .= '<td class="right">' . number_format_i18n($host['cur_time'],2)      . ' ms</td>' . PHP_EOL;

					$body_txt .=
						$host['description'] . "\t" .
						$criticalities[$host['monitor_criticality']] . "\t" .
						number_format_i18n($host['monitor_alert'],2) . " ms\t" .
						number_format_i18n($host['cur_time'],2)      . " ms" . PHP_EOL;

					$body .= '</tr>' . PHP_EOL;
				}
			}
			$body .= '</table>' . PHP_EOL;
		}

		$output     = '';
		$toutput    = $body_txt;
		$report_tag = '';
		$theme      = 'modern';

		monitor_debug('Loading Format File');

		$format_ok = reports_load_format_file(read_config_option('monitor_format_file'), $output, $report_tag, $theme);

		monitor_debug('Format File Loaded, Format is ' . ($format_ok ? 'Ok':'Not Ok') . ', Report Tag is ' . $report_tag);

		if ($format_ok) {
			if ($report_tag) {
				$output = str_replace('<REPORT>', $body, $output);
			} else {
				$output = $output . PHP_EOL . $body;
			}
		} else {
			$output = $body;
		}

		monitor_debug('HTML Processed');

		$v = get_cacti_version();
		$headers['User-Agent'] = 'Cacti-Monitor-v' . $v;

		$status = (sizeof($alert_hosts) ? sizeof($alert_hosts) . ' Alert Notifications' : '') .
			(sizeof($warn_hosts) ? (sizeof($alert_hosts) ? ', and ' : '') .
				sizeof($warn_hosts) . ' Warning Notifications' : '');

		process_send_email($email, $subject, $output, $toutput, $headers, $status);
	}
}

function process_send_email($email, $subject, $output, $toutput, $headers, $status) {
	$from_email = read_config_option('monitor_fromemail');
	if ($from_email == '') {
		$from_email = read_config_option('settings_from_email');

		if ($from_email == '') {
			$from_email = 'Cacti@cacti.net';
		}
	}

	$from_name = read_config_option('monitor_fromname');
	if ($from_name != '') {
		$from_name  = read_config_option('settings_from_name');

		if ($from_name == '') {
			$from_name = 'Cacti Reporting';
		}
	}

	$html = true;
	if (read_config_option('thold_send_text_only') == 'on') {
		$output = monitor_text($toutput);
		$html = false;
	}

	monitor_debug("Sending Email to '$email' for $status");

	$error = mailer(
		array($from_email, $from_name),
		$email,
		'',
		'',
		'',
		$subject,
		$output,
		monitor_text($toutput),
		'',
		$headers,
		$html
	);

	monitor_debug("The return from the mailer was '$error'");

	if (strlen($error)) {
		cacti_log("WARNING: Monitor had problems sending to '$email' for $status.  The error was '$error'", false, 'MONITOR');
	} else {
		cacti_log("NOTICE: Email Notification Sent to '$email' for $status.", false, 'MONITOR');
	}
}

function monitor_text($output) {
	$output = explode(PHP_EOL, $output);

	$new_output = '';

	if (sizeof($output)) {
		foreach ($output as $line) {
			$line = str_replace('<br>', PHP_EOL, $line);
			$line = str_replace('<br />', PHP_EOL, $line);
			$line = trim(strip_tags($line));
			$new_output .= $line . PHP_EOL;
		}
	}

	return $new_output;
}

function log_messages($type, $alert_hosts) {
	global $start_date;

	static $processed = array();

	if ($type == 'warn') {
		$type   = '0';
		$column = 'monitor_warn';
	} elseif ($type == 'alert') {
		$type = '1';
		$column = 'monitor_alert';
	}

	foreach ($alert_hosts as $id) {
		if (!isset($processed[$id])) {
			db_execute_prepared("INSERT INTO plugin_monitor_notify_history
				(host_id, notify_type, ping_time, ping_threshold, notification_time)
				SELECT id, '$type' AS notify_type, cur_time, $column, '$start_date' AS notification_time
				FROM host
				WHERE deleted = ''
				AND id = ?",
				array($id));
		}

		$processed[$id] = true;
	}
}

function get_hosts_by_list_type($type, $criticality, &$global_list, &$notify_list, &$lists) {
	global $force;

	$last_time = date('Y-m-d H:i:s', time() - read_config_option('monitor_resend_frequency') * 60);

	$hosts = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM host
		WHERE status = 3
		AND deleted = ''
		AND thold_send_email > 0
		AND monitor_criticality >= ?
		AND cur_time > monitor_$type",
		array($criticality));

	if ($type == 'warn') {
		$htype = 1;
	} else {
		$htype = 0;
	}

	if ($hosts > 0) {
		$groups = db_fetch_assoc_prepared("SELECT
			thold_send_email, thold_host_email, GROUP_CONCAT(host.id) AS id
			FROM host
			LEFT JOIN (
				SELECT host_id, MAX(notification_time) AS notification_time
				FROM plugin_monitor_notify_history
				WHERE notify_type = ?
				GROUP BY host_id
			) AS nh
			ON host.id=nh.host_id
			WHERE status = 3
			AND deleted = ''
			AND thold_send_email > 0
			AND monitor_criticality >= ?
			AND cur_time > monitor_$type " . ($type == 'warn' ? ' AND cur_time < monitor_alert':'') . '
			AND (notification_time < ? OR notification_time IS NULL)
			AND host.total_polls > 1
			GROUP BY thold_host_email, thold_send_email
			ORDER BY thold_host_email, thold_send_email',
			array($htype, $criticality, $last_time));

		if (sizeof($groups)) {
			foreach ($groups as $entry) {
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

					break;
				}
			}
		}
	}
}

function flatten_lists(&$global_list, &$notify_list) {
	if (sizeof($global_list)) {
		foreach ($global_list as $severity => $list) {
			foreach ($list as $item) {
				$new_global[$severity] = (isset($new_global[$severity]) ? $new_global[$severity] . ',':'') . $item['id'];
			}
		}
		$global_list = $new_global;
	}

	if (sizeof($notify_list)) {
		foreach ($notify_list as $severity => $lists) {
			foreach ($lists as $id => $list) {
				foreach ($list as $item) {
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
	foreach ($global_emails as $index => $user) {
		if (trim($user) != '') {
			$notification_emails[trim($user)]['global'] = true;
		}
	}

	if (sizeof($lists)) {
		$list_emails = db_fetch_assoc('SELECT id, emails
			FROM plugin_notification_lists
			WHERE id IN (' . implode(',', $lists) . ')');

		foreach ($list_emails as $email) {
			$emails = explode(',', $email['emails']);
			foreach ($emails as $user) {
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
	$days = read_config_option('monitor_log_storage');

	if (empty($days)) {
		$days = 120;
	}

	db_execute_prepared('DELETE FROM plugin_monitor_notify_history
		WHERE notification_time < FROM_UNIXTIME(UNIX_TIMESTAMP() - (? * 86400))',
		array($days));

	$purge_n = db_affected_rows();

	db_execute_prepared('DELETE FROM plugin_monitor_reboot_history
		WHERE log_time < FROM_UNIXTIME(UNIX_TIMESTAMP() - (? * 86400))',
		array($days));

	$purge_r = db_affected_rows();

	return array($purge_n, $purge_r);
}

function monitor_debug($message) {
	global $debug;

	if ($debug) {
		print trim($message) . PHP_EOL;
	}
}

function display_version() {
	global $config;

	if (!function_exists('plugin_monitor_version')) {
		include_once($config['base_path'] . '/plugins/monitor/setup.php');
	}

	$info = plugin_monitor_version();
	print 'Cacti Monitor Poller, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/*
 * display_help
 * displays the usage of the function
 */
function display_help() {
	display_version();

	print PHP_EOL;
	print 'usage: poller_monitor.php [--force] [--debug]' . PHP_EOL . PHP_EOL;
	print '  --force       - force execution, e.g. for testing' . PHP_EOL;
	print '  --debug       - debug execution, e.g. for testing' . PHP_EOL . PHP_EOL;
}

