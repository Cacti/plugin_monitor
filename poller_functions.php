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

/**
 * Add host to reboot email recipients.
 */
function monitorAddEmails(&$reboot_emails, $alert_emails, $host_id) {
    if (cacti_sizeof($alert_emails)) {
        foreach ($alert_emails as $email) {
            $reboot_emails[trim(strtolower($email))][$host_id] = $host_id;
        }
    }
}

/**
 * Add host to recipients from a notification list.
 */
function monitorAddNotificationList(&$reboot_emails, $notify_list, $host_id, $notification_lists) {
    if ($notify_list > 0 && isset($notification_lists[$notify_list])) {
        $emails = explode(',', $notification_lists[$notify_list]);
        monitorAddEmails($reboot_emails, $emails, $host_id);
    }
}

/**
 * Return configured global alert emails.
 */
function getAlertEmails() {
    $alert_email = read_config_option('alert_email');

    return ($alert_email != '') ? explode(',', $alert_email) : [];
}

/**
 * Remove orphan monitor rows for a monitor table.
 */
function purgeOrphanMonitorRows($table_name) {
    $removed_hosts = db_fetch_assoc("SELECT mu.host_id
        FROM $table_name AS mu
        LEFT JOIN host AS h
        ON h.id = mu.host_id
        WHERE h.id IS NULL");

    if (cacti_sizeof($removed_hosts)) {
        db_execute("DELETE mu
            FROM $table_name AS mu
            LEFT JOIN host AS h
            ON h.id = mu.host_id
            WHERE h.id IS NULL");
    }
}

/**
 * Return hosts detected as rebooted.
 */
function getRebootedHosts() {
    return db_fetch_assoc('SELECT h.id, h.description,
        h.hostname, h.snmp_sysUpTimeInstance, mu.uptime
        FROM host AS h
        LEFT JOIN plugin_monitor_uptime AS mu
        ON h.id = mu.host_id
        WHERE h.snmp_version > 0
        AND status IN (2,3)
        AND h.deleted = ""
        AND h.monitor = "on"
        AND (mu.uptime IS NULL OR mu.uptime > h.snmp_sysUpTimeInstance)
        AND h.snmp_sysUpTimeInstance > 0');
}

/**
 * Return notification list id-to-emails map.
 */
function getNotificationListsMap() {
    return array_rekey(
        db_fetch_assoc('SELECT id, emails
            FROM plugin_notification_lists
            ORDER BY id'),
        'id', 'emails'
    );
}

/**
 * Add threshold-configured recipients for rebooted host.
 */
function addTholdRebootRecipients(&$reboot_emails, $host_id, $alert_emails, $notification_lists) {
    $notify = db_fetch_row_prepared('SELECT thold_send_email, thold_host_email
        FROM host
        WHERE id = ?',
        [$host_id]);

    if (!cacti_sizeof($notify)) {
        return;
    }

    switch ($notify['thold_send_email']) {
        case '1':
            monitorAddEmails($reboot_emails, $alert_emails, $host_id);

            break;
        case '2':
            monitorAddNotificationList($reboot_emails, $notify['thold_host_email'], $host_id, $notification_lists);

            break;
        case '3':
            monitorAddEmails($reboot_emails, $alert_emails, $host_id);
            monitorAddNotificationList($reboot_emails, $notify['thold_host_email'], $host_id, $notification_lists);

            break;
        default:
            break;
    }
}

/**
 * Build reboot email recipient map for rebooted hosts.
 */
function buildRebootEmailMap($rebooted_hosts, $alert_emails) {
    $reboot_emails      = [];
    $notification_lists = getNotificationListsMap();
    $monitor_list       = read_config_option('monitor_list');
    $monitor_thold      = read_config_option('monitor_reboot_thold');

    foreach ($rebooted_hosts as $host) {
        db_execute_prepared('INSERT INTO plugin_monitor_reboot_history
            (host_id, reboot_time)
            VALUES (?, ?)',
            [$host['id'], date(MONITOR_DATE_TIME_FORMAT, time() - intval($host['snmp_sysUpTimeInstance']))]);

        monitorAddNotificationList($reboot_emails, $monitor_list, $host['id'], $notification_lists);

        if ($monitor_thold == 'on') {
            addTholdRebootRecipients($reboot_emails, $host['id'], $alert_emails, $notification_lists);
        }
    }

    return $reboot_emails;
}

/**
 * Send reboot notifications using configured delivery mode.
 */
function sendRebootNotifications($reboot_emails) {
    $monitor_send_one_email = read_config_option('monitor_send_one_email');

    if (!cacti_sizeof($reboot_emails)) {
        return;
    }

    $all_hosts = [];
    $to_email  = '';

    foreach ($reboot_emails as $email => $hosts) {
        if ($email == '') {
            monitorDebug('Unable to process reboot notification due to empty Email address.');

            continue;
        }

        $to_email .= ($to_email != '' ? ',' : '') . $email;
        $all_hosts = array_unique(array_merge($all_hosts, array_values($hosts)));

        if ($monitor_send_one_email !== 'on') {
            monitorDebug('Processing the Email address: ' . $email);
            processRebootEmail($email, $hosts);
        }
    }

    if ($monitor_send_one_email == 'on' && $to_email !== '') {
        monitorDebug('Processing the Email address: ' . $to_email);
        processRebootEmail($to_email, $all_hosts);
    }
}

/**
 * Check uptime/reboot events and process reboot notifications.
 */
function monitorUptimeChecker() {
    monitorDebug('Checking for Uptime of Devices');

    $alert_emails  = getAlertEmails();

    purgeOrphanMonitorRows('plugin_monitor_uptime');
    purgeOrphanMonitorRows('plugin_monitor_reboot_history');

    $rebooted_hosts = getRebootedHosts();

    if (cacti_sizeof($rebooted_hosts)) {
        $reboot_emails = buildRebootEmailMap($rebooted_hosts, $alert_emails);
        sendRebootNotifications($reboot_emails);
    }

    // Freshen the uptimes
    db_execute('REPLACE INTO plugin_monitor_uptime
        (host_id, uptime)
        SELECT id, snmp_sysUpTimeInstance
        FROM host
        WHERE snmp_version > 0
        AND status IN(2,3)
        AND deleted = ""
        AND monitor = "on"
        AND snmp_sysUpTimeInstance > 0');

    // Log Recently Down
    db_execute('INSERT IGNORE INTO plugin_monitor_notify_history
        (host_id, notify_type, notification_time, notes)
        SELECT h.id, "3" AS notify_type, status_fail_date AS notification_time, status_last_error AS notes
        FROM host AS h
        WHERE status = 1
        AND deleted = ""
        AND monitor = "on"
        AND status_event_count = 1');

    $recent = db_affected_rows();

    return [cacti_sizeof($rebooted_hosts), $recent];
}

/**
 * Build reboot details for both HTML and plain text mail bodies.
 */
function buildRebootDetails($hosts) {
    $body_txt  = '';
    $last_host = [];

    $body  = '<table class="report_table">' . PHP_EOL;
    $body .= '<tr class="header_row">' . PHP_EOL;
    $body .=
        '<th class="left">' . __('Description', 'monitor') . '</th>' .
        '<th class="left">' . __('Hostname', 'monitor') . '</th>' . PHP_EOL;
    $body .= '</tr>' . PHP_EOL;

    foreach ($hosts as $host_id) {
        $host = db_fetch_row_prepared('SELECT description, hostname
            FROM host
            WHERE id = ?',
            [$host_id]);

        if (!cacti_sizeof($host)) {
            continue;
        }

        $last_host = $host;
        $body .= '<tr>' .
            '<td class="left">' . $host['description'] . '</td>' .
            '<td class="left">' . $host['hostname'] . '</td>' .
            '</tr>' . PHP_EOL;

        $body_txt .=
            __('Description: ', 'monitor') . $host['description'] . PHP_EOL .
            __('Hostname: ', 'monitor') . $host['hostname'] . PHP_EOL . PHP_EOL;
    }

    $body .= '</table>' . PHP_EOL;

    return [$body, $body_txt, $last_host];
}

/**
 * Build reboot notification email subject.
 */
function buildRebootSubject($hosts, $last_host) {
    $subject                = read_config_option('monitor_subject');
    $monitor_send_one_email = read_config_option('monitor_send_one_email');

    if ($monitor_send_one_email == 'on' && cacti_sizeof($last_host)) {
        return $subject . ' ' . $last_host['description'] . ' (' . $last_host['hostname'] . ')';
    }

    if (cacti_sizeof($hosts) == 1 && cacti_sizeof($last_host)) {
        return $subject . ' 1 device  - ' . $last_host['description'] . ' (' . $last_host['hostname'] . ')';
    }

    return $subject . ' ' . cacti_sizeof($hosts) . ' devices';
}

/**
 * Prepare report wrapper output and headers for monitor notifications.
 */
function prepareReportOutput($body, $body_txt) {
    $output = '';

    $report_tag = '';
    $theme      = 'modern';

    monitorDebug('Loading Format File');

    $format_ok = reports_load_format_file(read_config_option('monitor_format_file'), $output, $report_tag, $theme);

    monitorDebug('Format File Loaded, Format is ' . ($format_ok ? 'Ok' : 'Not Ok') . ', Report Tag is ' . $report_tag);

    if ($format_ok) {
        if ($report_tag) {
            $output = str_replace('<REPORT>', $body, $output);
        } else {
            $output = $output . PHP_EOL . $body;
        }
    } else {
        $output = $body;
    }

    monitorDebug('HTML Processed');

    if (defined('CACTI_VERSION')) {
        $version = CACTI_VERSION;
    } else {
        $version = get_cacti_version();
    }

    $headers = ['User-Agent' => 'Cacti-Monitor-v' . $version];

    return [$output, $body_txt, $headers];
}

/**
 * Process and send reboot notification email.
 */
function processRebootEmail($email, $hosts) {
    monitorDebug("Reboot Processing for $email starting");

    [$body, $body_txt, $last_host] = buildRebootDetails($hosts);
    $subject                        = buildRebootSubject($hosts, $last_host);

    $template_output = read_config_option('monitor_body');
    $template_output = str_replace('<DETAILS>', $body, $template_output) . PHP_EOL;

    if (strpos($template_output, '<DETAILS>') !== false) {
        $toutput = str_replace('<DETAILS>', $body_txt, $template_output) . PHP_EOL;
    } else {
        $toutput = $body_txt;
    }

    if (read_config_option('monitor_reboot_notify') != 'on') {
        return;
    }

    [$output, $toutput, $headers] = prepareReportOutput($body, $toutput);

    processSendEmail($email, $subject, $output, $toutput, $headers, 'Reboot Notifications');
}

/**
 * Collect alert and warning host ids from requested notification lists.
 */
function collectNotificationHosts($lists, $global_list, $notify_list) {
    $alert_hosts = [];
    $warn_hosts  = [];

    foreach ($lists as $list) {
        if ($list === 'global') {
            if (isset($global_list['alert'])) {
                $alert_hosts = array_merge($alert_hosts, explode(',', $global_list['alert']));
            }

            if (isset($global_list['warn'])) {
                $warn_hosts = array_merge($warn_hosts, explode(',', $global_list['warn']));
            }

            continue;
        }

        if (isset($notify_list[$list]['alert'])) {
            $alert_hosts = array_merge($alert_hosts, explode(',', $notify_list[$list]['alert']));
        }

        if (isset($notify_list[$list]['warn'])) {
            $warn_hosts = array_merge($warn_hosts, explode(',', $notify_list[$list]['warn']));
        }
    }

    return [$alert_hosts, $warn_hosts];
}

/**
 * Log and de-duplicate notification host ids.
 */
function normalizeAndLogNotificationHosts(&$alert_hosts, &$warn_hosts) {
    if (cacti_sizeof($alert_hosts)) {
        $alert_hosts = array_unique($alert_hosts, SORT_NUMERIC);
        logMessages('alert', $alert_hosts);
    }

    if (cacti_sizeof($warn_hosts)) {
        $warn_hosts = array_unique($warn_hosts, SORT_NUMERIC);
        logMessages('warn', $warn_hosts);
    }
}

/**
 * Build base intro text for ping threshold notification.
 */
function buildPingNotificationIntro($freq) {
    $body     = '<h1>' . __(MONITOR_PING_NOTIFICATION_SUBJECT, 'monitor') . '</h1>' . PHP_EOL;
    $body_txt = __(MONITOR_PING_NOTIFICATION_SUBJECT, 'monitor') . PHP_EOL;

    $message = __('The following report will identify Devices that have eclipsed their ping latency thresholds.  You are receiving this report since you are subscribed to a Device associated with the Cacti system located at the following URL below.');

    $body .= '<p>' . $message . '</p>' . PHP_EOL;
    $body_txt .= $message . PHP_EOL;

    $body .= '<h2><a href="' . read_config_option('base_url') . '">Cacti Monitoring Site</a></h2>' . PHP_EOL;
    $body_txt .= __('Cacti Monitoring Site', 'monitor') . PHP_EOL;

    if ($freq > 0) {
        $body .= '<p>' . __('You will receive notifications every %d minutes if the Device is above its threshold.', $freq, 'monitor') . '</p>' . PHP_EOL;
        $body_txt .= __('You will receive notifications every %d minutes if the Device is above its threshold.', $freq, 'monitor') . PHP_EOL;
    } else {
        $body .= '<p>' . __('You will receive notifications every time the Device is above its threshold.', 'monitor') . '</p>' . PHP_EOL;
        $body_txt .= __('You will receive notifications every time the Device is above its threshold.', 'monitor') . PHP_EOL;
    }

    return [$body, $body_txt];
}

/**
 * Append one threshold breach section to notification body.
 */
function appendThresholdSection(&$body, &$body_txt, $host_ids, $criticalities, $section_text, $threshold_field) {
    global $config;

    if (!cacti_sizeof($host_ids)) {
        return;
    }

    $body .= '<p>' . __($section_text, 'monitor') . '</p>' . PHP_EOL;
    $body_txt .= __($section_text, 'monitor') . PHP_EOL;

    $body .= '<table class="report_table">' . PHP_EOL;
    $body .= '<tr class="header_row">' . PHP_EOL;
    $body .=
        '<th class="left">' . __('Hostname', 'monitor') . '</th>' .
        '<th class="left">' . __('Criticality', 'monitor') . '</th>' .
        '<th class="right">' . __(MONITOR_ALERT_PING_LABEL, 'monitor') . '</th>' .
        '<th class="right">' . __(MONITOR_CURRENT_PING_LABEL, 'monitor') . '</th>' . PHP_EOL;
    $body .= '</tr>' . PHP_EOL;

    $body_txt .=
        __('Hostname', 'monitor') . "\t" .
        __('Criticality', 'monitor') . "\t" .
        __(MONITOR_ALERT_PING_LABEL, 'monitor') . "\t" .
        __(MONITOR_CURRENT_PING_LABEL, 'monitor') . PHP_EOL;

    $hosts = db_fetch_assoc('SELECT *
        FROM host
        WHERE id IN(' . implode(',', $host_ids) . ')
        AND deleted = ""');

    if (cacti_sizeof($hosts)) {
        foreach ($hosts as $host) {
            $body .= '<tr>' . PHP_EOL;
            $body .= '<td class="left"><a class="hyperLink" href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '">' . $host['description'] . '</a></td>' . PHP_EOL;
            $body .= '<td class="left">' . $criticalities[$host['monitor_criticality']] . '</td>' . PHP_EOL;
            $body .= '<td class="right">' . number_format_i18n($host[$threshold_field],2) . ' ms</td>' . PHP_EOL;
            $body .= '<td class="right">' . number_format_i18n($host['cur_time'],2) . ' ms</td>' . PHP_EOL;
            $body .= '</tr>' . PHP_EOL;

            $body_txt .=
                $host['description'] . "\t" .
                $criticalities[$host['monitor_criticality']] . "\t" .
                number_format_i18n($host[$threshold_field],2) . " ms\t" .
                number_format_i18n($host['cur_time'],2) . ' ms' . PHP_EOL;
        }
    }

    $body .= '</table>' . PHP_EOL;
}

/**
 * Build delivery status summary for notification logging.
 */
function buildNotificationStatus($alert_hosts, $warn_hosts) {
    $status = '';

    if (cacti_sizeof($alert_hosts)) {
        $status = sizeof($alert_hosts) . ' Alert Notifications';
    }

    if (cacti_sizeof($warn_hosts)) {
        if ($status !== '') {
            $status .= ', and ';
        }

        $status .= sizeof($warn_hosts) . ' Warning Notifications';
    }

    return $status;
}

/**
 * Process and send ping threshold notification email.
 */
function processEmail($email, $lists, $global_list, $notify_list) {
    monitorDebug('Into Processing');

    $criticalities = [
        0 => __('Disabled', 'monitor'),
        1 => __('Low', 'monitor'),
        2 => __('Medium', 'monitor'),
        3 => __('High', 'monitor'),
        4 => __('Mission Critical', 'monitor')
    ];

    [$alert_hosts, $warn_hosts] = collectNotificationHosts($lists, $global_list, $notify_list);
    monitorDebug('Lists Processed');

    normalizeAndLogNotificationHosts($alert_hosts, $warn_hosts);
    monitorDebug('Found ' . sizeof($alert_hosts) . ' Alert Hosts, and ' . sizeof($warn_hosts) . ' Warn Hosts');

    if (!cacti_sizeof($alert_hosts) && !cacti_sizeof($warn_hosts)) {
        return;
    }

    monitorDebug('Formatting Email');

    $freq    = read_config_option('monitor_resend_frequency');
    $subject = __(MONITOR_PING_NOTIFICATION_SUBJECT, 'monitor');
    [$body, $body_txt] = buildPingNotificationIntro($freq);

    appendThresholdSection(
        $body,
        $body_txt,
        $alert_hosts,
        $criticalities,
        'The following Devices have breached their Alert Notification Threshold.',
        'monitor_alert'
    );

    appendThresholdSection(
        $body,
        $body_txt,
        $warn_hosts,
        $criticalities,
        'The following Devices have breached their Warning Notification Threshold.',
        'monitor_warn'
    );

    [$output, $toutput, $headers] = prepareReportOutput($body, $body_txt);
    $status = buildNotificationStatus($alert_hosts, $warn_hosts);

    processSendEmail($email, $subject, $output, $toutput, $headers, $status);
}

/**
 * Send notification email through Cacti mailer.
 */
function processSendEmail($email, $subject, $output, $toutput, $headers, $status) {
    $from_email = read_config_option('monitor_fromemail');

    if ($from_email == '') {
        $from_email = read_config_option('settings_from_email');

        if ($from_email == '') {
            $from_email = 'Cacti@cacti.net';
        }
    }

    $from_name = read_config_option('monitor_fromname');

    if ($from_name == '') {
        $from_name  = read_config_option('settings_from_name');

        if ($from_name == '') {
            $from_name = 'Cacti Reporting';
        }
    }

    $html = true;

    if (read_config_option('thold_send_text_only') == 'on') {
        $output = monitorText($toutput);
        $html   = false;
    }

    monitorDebug("Sending Email to '$email' for $status");

    $error = mailer(
        [$from_email, $from_name],
        $email,
        '',
        '',
        '',
        $subject,
        $output,
        monitorText($toutput),
        null,
        $headers,
        $html
    );

    monitorDebug("The return from the mailer was '$error'");

    if (strlen($error)) {
        cacti_log("WARNING: Monitor had problems sending to '$email' for $status.  The error was '$error'", false, 'MONITOR');
    } else {
        cacti_log("NOTICE: Email Notification Sent to '$email' for $status.", false, 'MONITOR');
    }
}

/**
 * Convert HTML output into plain text output.
 */
function monitorText($output) {
    $output = explode(PHP_EOL, $output);

    $new_output = '';

    if (cacti_sizeof($output)) {
        foreach ($output as $line) {
            $line = str_replace('<br>', PHP_EOL, $line);
            $line = str_replace('<br />', PHP_EOL, $line);
            $line = trim(strip_tags($line));
            $new_output .= $line . PHP_EOL;
        }
    }

    return $new_output;
}

/**
 * Log alert or warning notification events.
 */
function logMessages($type, $alert_hosts) {
    global $start_date;

    static $processed = [];

    if ($type == 'warn') {
        $type   = '0';
        $column = 'monitor_warn';
    } elseif ($type == 'alert') {
        $type   = '1';
        $column = 'monitor_alert';
    }

    foreach ($alert_hosts as $id) {
        if (!isset($processed[$id])) {
            db_execute_prepared("INSERT INTO plugin_monitor_notify_history
                (host_id, notify_type, ping_time, ping_threshold, notification_time)
                SELECT id, '$type' AS notify_type, cur_time, $column, '$start_date' AS notification_time
                FROM host
                WHERE deleted = ''
                AND monitor = 'on'
                AND id = ?",
                [$id]);
        }

        $processed[$id] = true;
    }
}

/**
 * Add one grouped notification entry to global/notification collections.
 */
function addGroupedNotificationEntry($type, $entry, &$global_list, &$notify_list, &$lists) {
    if ($entry['thold_send_email'] == '1' || $entry['thold_send_email'] == '3') {
        $global_list[$type][] = $entry;
    }

    if (($entry['thold_send_email'] == '2' || $entry['thold_send_email'] == '3') && $entry['thold_host_email'] > 0) {
        $notify_list[$type][$entry['thold_host_email']][] = $entry;
        $lists[$entry['thold_host_email']]                = $entry['thold_host_email'];
    }
}

/**
 * Collect threshold-breached hosts by severity and notification list type.
 */
function getHostsByListType($type, $criticality, &$global_list, &$notify_list, &$lists) {
    $last_time = date(MONITOR_DATE_TIME_FORMAT, time() - read_config_option('monitor_resend_frequency') * 60);

    $hosts = db_fetch_cell_prepared("SELECT COUNT(*)
        FROM host
        WHERE status = 3
        AND deleted = ''
        AND monitor = 'on'
        AND thold_send_email > 0
        AND monitor_criticality >= ?
        AND cur_time > monitor_$type",
        [$criticality]);

    if ($hosts <= 0) {
        return;
    }

    $htype = ($type == 'warn') ? 1 : 0;

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
        AND monitor = 'on'
        AND thold_send_email > 0
        AND monitor_criticality >= ?
        AND cur_time > monitor_$type " . ($type == 'warn' ? ' AND cur_time < monitor_alert' : '') . '
        AND (notification_time < ? OR notification_time IS NULL)
        AND host.total_polls > 1
        GROUP BY thold_host_email, thold_send_email
        ORDER BY thold_host_email, thold_send_email',
        [$htype, $criticality, $last_time]);

    if (!cacti_sizeof($groups)) {
        return;
    }

    foreach ($groups as $entry) {
        addGroupedNotificationEntry($type, $entry, $global_list, $notify_list, $lists);
    }
}

/**
 * Flatten grouped list ids for one severity.
 */
function flattenGroupSeverityList($list) {
    $flattened = '';

    foreach ($list as $item) {
        $flattened .= ($flattened !== '' ? ',' : '') . $item['id'];
    }

    return $flattened;
}

/**
 * Flatten grouped notification ids for each list id within a severity.
 */
function flattenNotifySeverityLists($lists) {
    $flattened = [];

    foreach ($lists as $id => $list) {
        $flattened[$id] = flattenGroupSeverityList($list);
    }

    return $flattened;
}

/**
 * Flatten grouped notification structures into comma-separated host id strings.
 */
function flattenLists(&$global_list, &$notify_list) {
    if (cacti_sizeof($global_list)) {
        $new_global = [];

        foreach ($global_list as $severity => $list) {
            $new_global[$severity] = flattenGroupSeverityList($list);
        }

        $global_list = $new_global;
    }

    if (cacti_sizeof($notify_list)) {
        $new_list = [];

        foreach ($notify_list as $severity => $lists) {
            $new_list[$severity] = flattenNotifySeverityLists($lists);
        }

        $notify_list = $new_list;
    }
}

/**
 * Add email addresses to notification map under a scope key.
 */
function addEmailsToNotificationMap(&$notification_emails, $emails, $scope_key) {
    foreach ($emails as $user) {
        $user = trim($user);

        if ($user !== '') {
            $notification_emails[$user][$scope_key] = true;
        }
    }
}

/**
 * Build recipient map for global and notification list subscriptions.
 */
function getEmailsAndLists($lists) {
    $notification_emails = [];

    $alert_email = read_config_option('alert_email');
    $global_emails = ($alert_email != '') ? explode(',', $alert_email) : [];

    if (cacti_sizeof($global_emails)) {
        addEmailsToNotificationMap($notification_emails, $global_emails, 'global');
    }

    if (!cacti_sizeof($lists)) {
        return $notification_emails;
    }

    $list_emails = db_fetch_assoc('SELECT id, emails
        FROM plugin_notification_lists
        WHERE id IN (' . implode(',', $lists) . ')');

    if (!cacti_sizeof($list_emails)) {
        return $notification_emails;
    }

    foreach ($list_emails as $email) {
        addEmailsToNotificationMap($notification_emails, explode(',', $email['emails']), $email['id']);
    }

    return $notification_emails;
}

/**
 * Purge old notification and reboot history rows.
 */
function purgeEventRecords() {
    // Purge old records
    $days = read_config_option('monitor_log_storage');

    if (empty($days)) {
        $days = 120;
    }

    db_execute_prepared('DELETE FROM plugin_monitor_notify_history
        WHERE notification_time < FROM_UNIXTIME(UNIX_TIMESTAMP() - (? * 86400))',
        [$days]);

    $purge_n = db_affected_rows();

    db_execute_prepared('DELETE FROM plugin_monitor_reboot_history
        WHERE log_time < FROM_UNIXTIME(UNIX_TIMESTAMP() - (? * 86400))',
        [$days]);

    $purge_r = db_affected_rows();

    return [$purge_n, $purge_r];
}

/**
 * Print debug message when debug mode is enabled.
 */
function monitorDebug($message) {
    global $debug;

    if ($debug) {
        print trim($message) . PHP_EOL;
    }
}

/**
 * Display poller version information.
 */
function displayVersion() {
    global $config;

    if (!function_exists('pluginMonitorVersion')) {
        include_once $config['base_path'] . '/plugins/monitor/setup.php';
    }

    $info = pluginMonitorVersion();
    print 'Cacti Monitor Poller, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/*
 * display_help
 * displays the usage of the function
 */
function displayHelp() {
    displayVersion();

    print PHP_EOL;
    print 'usage: poller_monitor.php [--debug]' . PHP_EOL . PHP_EOL;
    print '  --debug       - debug execution, e.g. for testing' . PHP_EOL . PHP_EOL;
}
