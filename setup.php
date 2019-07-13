<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2019 The Cacti Group                                 |
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

function plugin_monitor_install() {
	/* core plugin functionality */
	api_plugin_register_hook('monitor', 'top_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_refresh', 'monitor_top_graph_refresh', 'setup.php');

	api_plugin_register_hook('monitor', 'draw_navigation_text', 'monitor_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('monitor', 'config_form', 'monitor_config_form', 'setup.php');
	api_plugin_register_hook('monitor', 'config_settings', 'monitor_config_settings', 'setup.php');
	api_plugin_register_hook('monitor', 'poller_bottom', 'monitor_poller_bottom', 'setup.php');
	api_plugin_register_hook('monitor', 'page_head', 'plugin_monitor_page_head', 'setup.php');

	/* device actions and interaction */
	api_plugin_register_hook('monitor', 'api_device_save', 'monitor_api_device_save', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_array', 'monitor_device_action_array', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_execute', 'monitor_device_action_execute', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_prepare', 'monitor_device_action_prepare', 'setup.php');
	api_plugin_register_hook('monitor', 'device_remove', 'monitor_device_remove', 'setup.php');

	/* add new filter for device */
	api_plugin_register_hook('monitor', 'device_filters', 'monitor_device_filters', 'setup.php');
	api_plugin_register_hook('monitor', 'device_sql_where', 'monitor_device_sql_where', 'setup.php');
	api_plugin_register_hook('monitor', 'device_table_bottom', 'monitor_device_table_bottom', 'setup.php');

	api_plugin_register_realm('monitor', 'monitor.php', 'View Monitoring Dashboard', 1);

	monitor_setup_table();
}

function monitor_device_filters($filters) {

	$filters['criticality'] = array(
		'filter' => FILTER_VALIDATE_INT,
		'pageset' => true,
		'default' => '-1'
	);

	return $filters;
}

function monitor_device_sql_where($sql_where) {
	if (get_request_var('criticality') >= 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' monitor_criticality = ' . get_request_var('criticality');
	}

	return $sql_where;
}

function monitor_device_table_bottom() {
	$criticalities = array(
		'-1' => __('Any', 'monitor'),
		'0'  => __('None', 'monitor'),
		'1'  => __('Low', 'monitor'),
		'2'  => __('Medium', 'monitor'),
		'3'  => __('High', 'monitor'),
		'4'  => __('Mission Critical', 'monitor')
	);

	$select = '<td>' . __('Criticality') . '</td><td><select id="criticality">';
	foreach($criticalities as $index => $crit) {
		if ($index == get_request_var('criticality')) {
			$select .= '<option selected value="' . $index . '">' . $crit . '</option>';
		} else {
			$select .= '<option value="' . $index . '">' . $crit . '</option>';
		}
	}
	$select .= '</select></td>';

    ?>
    <script type='text/javascript'>
	$(function() {
		$('#rows').parent().after('<?php print $select;?>');
		<?php if (get_selected_theme() != 'classic') {?>
		$('#criticality').selectmenu({
			change: function() {
				applyFilter();
			}
		});
		<?php } else { ?>
		$('#criticality').change(function() {
			applyFilter();
		});
		<?php } ?>
	});

	applyFilter = function() {
		strURL  = 'host.php?host_status=' + $('#host_status').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&site_id=' + $('#site_id').val();
		strURL += '&criticality=' + $('#criticality').val();
		strURL += '&poller_id=' + $('#poller_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	};

	</script>
	<?php
}

function plugin_monitor_uninstall() {
	db_execute('DROP TABLE IF EXISTS plugin_monitor_notify_history');
	db_execute('DROP TABLE IF EXISTS plugin_monitor_reboot_history');
	db_execute('DROP TABLE IF EXISTS plugin_monitor_uptime');
}

function plugin_monitor_page_head() {
	global $config;

	print get_md5_include_css('plugins/monitor/monitor.css') . PHP_EOL;
	if (file_exists($config['base_path'] . '/plugins/monitor/themes/' . get_selected_theme() . '/monitor.css')) {
		print get_md5_include_css('plugins/monitor/themes/' . get_selected_theme() . '/monitor.css') . PHP_EOL;
	}
}

function plugin_monitor_check_config() {
	global $config;
	// Here we will check to ensure everything is configured
	monitor_check_upgrade();

	include_once($config['library_path'] . '/database.php');
	$r = read_config_option('monitor_refresh');
	$result = db_fetch_assoc("SELECT * FROM settings WHERE name='monitor_refresh'");
	if (!isset($result[0]['name'])) {
		$r = NULL;
	}

	if ($r == '' or $r < 1 or $r > 300) {
		if ($r == '') {
			$sql = "REPLACE INTO settings VALUES ('monitor_refresh','300')";
		} else if ($r == NULL) {
			$sql = "INSERT INTO settings VALUES ('monitor_refresh','300')";
		} else {
			$sql = "UPDATE settings SET value = '300' WHERE name = 'monitor_refresh'";
		}

		$result = db_execute($sql);
		kill_session_var('sess_config_array');
	}

	return true;
}

function plugin_monitor_upgrade() {
	// Here we will upgrade to the newest version
	monitor_check_upgrade();
	return false;
}

function monitor_check_upgrade() {
    $files = array('plugins.php', 'monitor.php');
    if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
        return;
    }

	$info    = plugin_monitor_version();
	$current = $info['version'];
	$old     = read_config_option('plugin_monitor_version');

	api_plugin_register_hook('monitor', 'page_head', 'plugin_monitor_page_head', 'setup.php', 1);

	if ($current != $old) {
		monitor_setup_table();

		// Set the new version
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='monitor'");

		db_execute("UPDATE plugin_config SET
			version='" . $info['version']  . "',
			name='"    . $info['longname'] . "',
			author='"  . $info['author']   . "',
			webpage='" . $info['homepage'] . "'
			WHERE directory='" . $info['name'] . "' ");
	}
}

function plugin_monitor_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/monitor/INFO', true);
	return $info['info'];
}

function monitor_device_action_execute($action) {
	global $config, $fields_host_edit;

	if ($action != 'monitor_enable' && $action != 'monitor_disable' && $action != 'monitor_settings') {
		return $action;
	}

	$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

	if ($selected_items != false) {
		if ($action == 'monitor_enable' || $action == 'monitor_disable') {
			for ($i = 0; ($i < count($selected_items)); $i++) {
				if ($action == 'monitor_enable') {
					db_execute("UPDATE host SET monitor='on' WHERE id='" . $selected_items[$i] . "'");
				} else if ($action == 'monitor_disable') {
					db_execute("UPDATE host SET monitor='' WHERE id='" . $selected_items[$i] . "'");
				}
			}
		} else {
			for ($i = 0; ($i < count($selected_items)); $i++) {
				reset($fields_host_edit);
				while (list($field_name, $field_array) = each($fields_host_edit)) {
					if (isset_request_var("t_$field_name")) {
						if ($field_name == 'monitor_alert_baseline') {
							$cur_time = db_fetch_cell_prepared('SELECT cur_time FROM host WHERE id = ?', array($selected_items[$i]));
							if ($cur_time > 0) {
								db_execute_prepared("UPDATE host SET monitor_alert = CEIL(avg_time*?) WHERE id = ?", array(get_nfilter_request_var($field_name), $selected_items[$i]));
							}
						} elseif ($field_name == 'monitor_warn_baseline') {
							$cur_time = db_fetch_cell_prepared('SELECT cur_time FROM host WHERE id = ?', array($selected_items[$i]));
							if ($cur_time > 0) {
								db_execute_prepared("UPDATE host SET monitor_warn = CEIL(avg_time*?) WHERE id = ?", array(get_nfilter_request_var($field_name), $selected_items[$i]));
							}
						} else {
							db_execute_prepared("UPDATE host SET $field_name = ? WHERE id = ?", array(get_nfilter_request_var($field_name), $selected_items[$i]));
						}
					}
				}
			}
		}
	}

	return $action;
}

function monitor_device_remove($devices) {
	db_execute('DELETE FROM plugin_monitor_notify_history WHERE host_id IN(' . implode(',', $devices) . ')');
	db_execute('DELETE FROM plugin_monitor_reboot_history WHERE host_id IN(' . implode(',', $devices) . ')');
	db_execute('DELETE FROM plugin_monitor_uptime WHERE host_id IN(' . implode(',', $devices) . ')');

	return $devices;
}

function monitor_device_action_prepare($save) {
	global $host_list, $fields_host_edit;

	$action = $save['drp_action'];

	if ($action != 'monitor_enable' && $action != 'monitor_disable' && $action != 'monitor_settings') {
		return $save;
	}

	if ($action == 'monitor_enable' || $action == 'monitor_disable') {
		if ($action == 'monitor_enable') {
			$action_description = 'enable';
		} else if ($action == 'monitor_disable') {
			$action_description = 'disable';
		}

		print "<tr>
			<td colspan='2' class='even'>
				<p>" . __('Click \'Continue\' to %s monitoring on these Device(s)', $action_description, 'monitor') . "</p>
				<p><div class='itemlist'><ul>" . $save['host_list'] . "</ul></div></p>
			</td>
		</tr>";
	} else {
		print "<tr>
			<td colspan='2' class='even'>
				<p>" . __('Click \'Continue\' to Change the Monitoring settings for the following Device(s). Remember to check \'Update this Field\' to indicate which columns to update.', 'monitor') . "</p>
				<p><div class='itemlist'><ul>" . $save['host_list'] . "</ul></div></p>
			</td>
		</tr>";

		$form_array = array();
		$fields = array(
			'monitor',
			'monitor_text',
			'monitor_criticality',
			'monitor_warn',
			'monitor_alert',
			'monitor_warn_baseline',
			'monitor_alert_baseline'
		);

		foreach($fields as $field) {
			$form_array += array($field => $fields_host_edit[$field]);

			$form_array[$field]['value'] = '';
			$form_array[$field]['form_id'] = 0;
			$form_array[$field]['sub_checkbox'] = array(
				'name' => 't_' . $field,
				'friendly_name' => __('Update this Field', 'monitor'),
				'value' => ''
			);
		}

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);
	}
}

function monitor_device_action_array($device_action_array) {
	$device_action_array['monitor_settings'] = __('Change Monitoring Options', 'monitor');
	$device_action_array['monitor_enable']   = __('Enable Monitoring', 'monitor');
	$device_action_array['monitor_disable']  = __('Disable Monitoring', 'monitor');

	return $device_action_array;
}

function monitor_scan_dir() {
	global $config;

	$ext   = array('.wav', '.mp3');
	$d     = dir($config['base_path'] . '/plugins/monitor/sounds/');
	$files = array();

	while (false !== ($entry = $d->read())) {
		if ($entry != '.' && $entry != '..' && in_array(strtolower(substr($entry,-4)),$ext)) {
			$files[$entry] = $entry;
		}
	}
	$d->close();
	asort($files); // sort the files
	array_unshift($files, 'None'); // prepend the None option

	return $files;
}

function monitor_config_settings() {
	global $tabs, $settings, $criticalities, $page_refresh_interval, $config, $settings_user, $tabs_graphs;

	include_once($config['base_path'] . '/lib/reports.php');

	$formats = reports_get_format_files();

	$criticalities = array(
		0 => __('Disabled', 'monitor'),
		1 => __('Low', 'monitor'),
		2 => __('Medium', 'monitor'),
		3 => __('High', 'monitor'),
		4 => __('Mission Critical', 'monitor')
	);

	$log_retentions = array(
		'-1'  => __('Indefinately', 'monitor'),
		'31'  => __('%d Month', 1, 'monitor'),
		'62'  => __('%d Months', 2, 'monitor'),
		'93'  => __('%d Months', 3, 'monitor'),
		'124' => __('%d Months', 4, 'monitor'),
		'186' => __('%d Months', 6, 'monitor'),
		'365' => __('%d Year', 1, 'monitor')
	);

	$tabs_graphs += array('monitor' => __('Monitor Settings', 'monitor'));

	$settings_user += array(
		'monitor' => array(
			'monitor_sound' => array(
				'friendly_name' => __('Alarm Sound', 'monitor'),
				'description' => __('This is the sound file that will be played when a Device goes down.', 'monitor'),
				'method' => 'drop_array',
				'array' => monitor_scan_dir(),
				'default' => 'attn-noc.wav',
			),
			'monitor_legend' => array(
				'friendly_name' => __('Show Icon Legend', 'monitor'),
				'description' => __('Check this to show an icon legend on the Monitor display', 'monitor'),
				'method' => 'checkbox',
			)
		)
	);

	if (get_current_page() != 'settings.php') {
		return;
	}

	$tabs['monitor'] = __('Monitor', 'monitor');

	$temp = array(
		'monitor_header' => array(
			'friendly_name' => __('Monitor Settings', 'monitor'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'monitor_new_enabled' => array(
			'friendly_name' => __('Enable on new devices', 'monitor'),
			'description' => __('Check this to automatically enable monitoring when creating new devices', 'monitor'),
			'method' => 'checkbox',
		),
		'monitor_log_storage' => array(
			'friendly_name' => __('Notification/Reboot Log Retention', 'monitor'),
			'description' => __('Keep Notification and Reboot Logs for this number of days.', 'monitor'),
			'method' => 'drop_array',
			'default' => '31',
			'array' => $log_retentions
		),
		'monitor_sound' => array(
			'friendly_name' => __('Alarm Sound', 'monitor'),
			'description' => __('This is the sound file that will be played when a Device goes down.', 'monitor'),
			'method' => 'drop_array',
			'array' => monitor_scan_dir(),
			'default' => 'attn-noc.wav',
		),
		'monitor_refresh' => array(
			'friendly_name' => __('Refresh Interval', 'monitor'),
			'description' => __('This is the time in seconds before the page refreshes.  (1 - 300)', 'monitor'),
			'method' => 'drop_array',
			'default' => '60',
			'array' => $page_refresh_interval
		),
		'monitor_legend' => array(
			'friendly_name' => __('Show Icon Legend', 'monitor'),
			'description' => __('Check this to show an icon legend on the Monitor display', 'monitor'),
			'method' => 'checkbox',
		),
		'monitor_grouping' => array(
			'friendly_name' => __('Grouping', 'monitor'),
			'description' => __('This is how monitor will Group Devices.', 'monitor'),
			'method' => 'drop_array',
			'default' => 'default',
			'array' => array(
				'default'                  => __('Default', 'monitor'),
				'default_by_permissions'   => __('Default with permissions', 'monitor'),
				'group_by_tree'            => __('Tree', 'monitor'),
				'group_by_device_template' => __('Device Template', 'monitor'),
			)
		),
		'monitor_view' => array(
			'friendly_name' => __('View', 'monitor'),
			'description' => __('This is how monitor will render Devices.', 'monitor'),
			'method' => 'drop_array',
			'default' => 'default',
			'array' => array(
				'default'  => __('Default', 'monitor'),
				'list'     => __('List', 'monitor'),
				'tiles'    => __('Tiles', 'monitor'),
				'tilesadt' => __('Tiles & Downtime', 'monitor')
			)
		),
		'monitor_format_header' => array(
			'friendly_name' => __('Notification Report Format', 'monitor'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'monitor_format_file' => array(
			'friendly_name' => __('Format File to Use', 'monitor'),
			'method' => 'drop_array',
			'default' => 'default.format',
			'description' => __('Choose the custom html wrapper and CSS file to use.  This file contains both html and CSS to wrap around your report.  If it contains more than simply CSS, you need to place a special <REPORT> tag inside of the file.  This format tag will be replaced by the report content.  These files are located in the \'formats\' directory.', 'monitor'),
			'array' => $formats
		),
		'monitor_threshold' => array(
			'friendly_name' => __('Ping Threshold Notifications', 'monitor'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'monitor_warn_criticality' => array(
			'friendly_name' => __('Warning Latency Notification', 'monitor'),
			'description' => __('If a Device has a Round Trip Ping Latency above the Warning Threshold and above the Criticality below, subscribing emails to the Device will receive an email notification.  Select \'Disabled\' to Disable.  The Thold Plugin is required to enable this feature.', 'monitor'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $criticalities
		),
		'monitor_alert_criticality' => array(
			'friendly_name' => __('Alert Latency Notification', 'monitor'),
			'description' => __('If a Device has a Round Trip Ping Latency above the Alert Threshold and above the Criticality below, subscribing emails to the Device will receive an email notification.  Select \'Disabled\' to Disable.  The Thold Plugin is required to enable this feature.', 'monitor'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $criticalities
		),
		'monitor_resend_frequency' => array(
			'friendly_name' => __('How Often to Resend Emails', 'monitor'),
			'description' => __('How often should emails notifications be sent to subscribers for these Devices if they are exceeding their latency thresholds', 'monitor'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'   => __('Every Occurrence', 'monitor'),
				'20'  => __('Every %d Minutes', 20, 'monitor'),
				'30'  => __('Every %d Minutes', 30, 'monitor'),
				'60'  => __('Every Hour', 'monitor'),
				'120' => __('Every %d Hours', 2, 'monitor'),
				'240' => __('Every %d Hours', 4, 'monitor')
			)
		),
		'monitor_reboot' => array(
			'friendly_name' => __('Reboot Notifications', 'monitor'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'monitor_reboot_notify' => array(
			'friendly_name' => __('Send Reboot Notifications', 'monitor'),
			'method' => 'checkbox',
			'description' => __('Should Device Reboot Notifications be sent to users?', 'monitor'),
			'default' => 'on',
		),
		'monitor_reboot_thold' => array(
			'friendly_name' => __('Include Threshold Alert Lists', 'monitor'),
			'method' => 'checkbox',
			'description' => __('Should Threshold Alert Lists also receive notification', 'monitor'),
			'default' => 'on',
		),
		'monitor_subject' => array(
			'friendly_name' => __('Subject', 'monitor'),
			'description' => __('Enter a Reboot message subject for the Reboot Nofication.', 'monitor'),
			'method' => 'textbox',
			'default' => __('Cacti Device Reboot Nofication', 'monitor'),
			'size' => 60,
			'max_length' => 60
		),
		'monitor_body' => array(
			'friendly_name' => __('Email Body', 'monitor'),
			'description' => __('Enter an Email body to include in the Reboot Notification message.  Currently, the only supported replacement tag accepted is &#060;DETAILS&#062;', 'monitor'),
			'method' => 'textarea',
			'textarea_rows' => 4,
			'textarea_cols' => 80,
			'default' => __('<h1>Monitor Reboot Notification</h1><p>The following Device\'s were Rebooted.  See details below for additional information.</p><br><DETAILS>', 'monitor')
		),
		'monitor_email_header' => array(
			'friendly_name' => __('Notification Email Addresses', 'monitor'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'monitor_fromname' => array(
			'friendly_name' => __('From Name', 'monitor'),
			'description' => __('Enter the Email Name to send the notifications form', 'monitor'),
			'method' => 'textbox',
			'size' => '60',
			'max_length' => '255'
		),
		'monitor_fromemail' => array(
			'friendly_name' => __('From Address', 'monitor'),
			'description' => __('Enter the Email Address to send the notification from', 'monitor'),
			'method' => 'textbox',
			'size' => '60',
			'max_length' => '255'
		),
		'monitor_list' => array(
			'friendly_name' => __('Notification List', 'thold'),
			'description' => __('Select a Notification List below.  All Emails subscribed to the notification list will be notified.', 'thold'),
			'method' => 'drop_sql',
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name',
			'default' => '',
			'none_value' => __('None', 'monitor')
		),
		'monitor_emails' => array(
			'friendly_name' => __('Email Addresses', 'monitor'),
			'description' => __('Enter a comma delimited list of Email addresses to inform of a reboot event.', 'monitor'),
			'method' => 'textarea',
			'textarea_rows' => 2,
			'textarea_cols' => 80,
			'default' => ''
		)
	);

	if (isset($settings['monitor'])) {
		$settings['monitor'] = array_merge($settings['monitor'], $temp);
	} else {
		$settings['monitor'] = $temp;
	}
}

function monitor_top_graph_refresh($refresh) {
	if (get_current_page() != 'monitor.php') {
		return $refresh;
	}

	$r = read_config_option('monitor_refresh');

	if ($r == '' or $r < 1) {
		return $refresh;
	}

	return $r;
}

function monitor_show_tab() {
	global $config;

	monitor_check_upgrade();

	if (api_user_realm_auth('monitor.php')) {
		if (substr_count($_SERVER['REQUEST_URI'], 'monitor.php')) {
			print '<a href="' . $config['url_path'] . 'plugins/monitor/monitor.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor_down.gif" alt="' . __('Monitor', 'monitor') . '"></a>';
		} else {
			print '<a href="' . $config['url_path'] . 'plugins/monitor/monitor.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor.gif" alt="' . __('Monitor', 'monitor') . '"></a>';
		}
	}
}

function monitor_config_form () {
	global $fields_host_edit, $criticalities;

	$baselines = array(
		'0'   => __('Do not Change', 'monitor'),
		'1.20'  => __('%d Percent Above Average', 20, 'monitor'),
		'1.30'  => __('%d Percent Above Average', 30, 'monitor'),
		'1.40'  => __('%d Percent Above Average', 40, 'monitor'),
		'1.50'  => __('%d Percent Above Average', 50, 'monitor'),
		'1.60'  => __('%d Percent Above Average', 60, 'monitor'),
		'1.70'  => __('%d Percent Above Average', 70, 'monitor'),
		'1.80'  => __('%d Percent Above Average', 80, 'monitor'),
		'1.90'  => __('%d Percent Above Average', 90, 'monitor'),
		'2.00'  => __('%d Percent Above Average', 100, 'monitor'),
		'2.20'  => __('%d Percent Above Average', 120, 'monitor'),
		'2.40'  => __('%d Percent Above Average', 140, 'monitor'),
		'2.50'  => __('%d Percent Above Average', 150, 'monitor'),
		'3.00'  => __('%d Percent Above Average', 200, 'monitor'),
		'4.00'  => __('%d Percent Above Average', 300, 'monitor'),
		'5.00'  => __('%d Percent Above Average', 400, 'monitor'),
		'6.00'  => __('%d Percent Above Average', 500, 'monitor')
	);

	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = array();
	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;
		if ($f == 'disabled') {
			$fields_host_edit3['monitor_header'] = array(
				'friendly_name' => __('Device Monitoring Settings', 'monitor'),
				'method' => 'spacer',
				'collapsible' => 'true'
			);
			$fields_host_edit3['monitor'] = array(
				'method' => 'checkbox',
				'friendly_name' => __('Monitor Device', 'monitor'),
				'description' => __('Check this box to monitor this Device on the Monitor Tab.', 'monitor'),
				'value' => '|arg1:monitor|',
				'form_id' => false
			);

			$host_id = form_input_validate(get_nfilter_request_var('id'), 'id', 0, true, 3);
			if (!($host_id > 0)) {
				$fields_host_edit3['monitor']['default'] = monitor_get_default($host_id);
			}

			$fields_host_edit3['monitor_criticality'] = array(
				'friendly_name' => __('Device Criticality', 'monitor'),
				'description' => __('What is the Criticality of this Device.', 'monitor'),
				'method' => 'drop_array',
				'array' => $criticalities,
				'value' => '|arg1:monitor_criticality|',
				'default' => '0',
			);
			$fields_host_edit3['monitor_warn'] = array(
				'friendly_name' => __('Ping Warning Threshold', 'monitor'),
				'description' => __('If the round-trip latency via any of the predefined Cacti ping methods raises above this threshold, log a warning or send email based upon the Devices Criticality and Monitor setting.  The unit is in milliseconds.  Setting to 0 disables. The Thold Plugin is required to leverage this functionality.', 'monitor'),
				'method' => 'textbox',
				'size' => '10',
				'max_length' => '5',
				'placeholder' => __('milliseconds', 'monitor'),
				'value' => '|arg1:monitor_warn|',
				'default' => '',
			);
			$fields_host_edit3['monitor_alert'] = array(
				'friendly_name' => __('Ping Alert Threshold', 'monitor'),
				'description' => __('If the round-trip latency via any of the predefined Cacti ping methods raises above this threshold, log an alert or send an email based upon the Devices Criticality and Monitor setting.  The unit is in milliseconds.  Setting to 0 disables. The Thold Plugin is required to leverage this functionality.', 'monitor'),
				'method' => 'textbox',
				'size' => '10',
				'max_length' => '5',
				'placeholder' => __('milliseconds', 'monitor'),
				'value' => '|arg1:monitor_alert|',
				'default' => '',
			);
			$fields_host_edit3['monitor_warn_baseline'] = array(
				'friendly_name' => __('Re-Baseline Warning', 'monitor'),
				'description' => __('The percentage above the current average ping time to consider a Warning Threshold.  If updated, this will automatically adjust the Ping Warning Threshold.', 'monitor'),
				'method' => 'drop_array',
				'default' => '0',
				'value' => '0',
				'array' => $baselines
			);
			$fields_host_edit3['monitor_alert_baseline'] = array(
				'friendly_name' => __('Re-Baseline Alert', 'monitor'),
				'description' => __('The percentage above the current average ping time to consider a Alert Threshold.  If updated, this will automatically adjust the Ping Alert Threshold.', 'monitor'),
				'method' => 'drop_array',
				'default' => '0',
				'value' => '0',
				'array' => $baselines
			);
			$fields_host_edit3['monitor_text'] = array(
				'friendly_name' => __('Down Device Message', 'monitor'),
				'description' => __('This is the message that will be displayed when this Device is reported as down.', 'monitor'),
				'method' => 'textarea',
				'max_length' => 1000,
				'textarea_rows' => 2,
				'textarea_cols' => 80,
				'value' => '|arg1:monitor_text|',
				'default' => '',
			);
		}
	}
	$fields_host_edit = $fields_host_edit3;
}

function monitor_get_default($host_id) {
	$monitor_new_device = '';
	if ($host_id <= 0) {
		$monitor_new_device = db_fetch_cell('SELECT value
						     FROM settings
						     WHERE name = \'monitor_new_enabled\'');
	}
	//file_put_contents('/tmp/monitor.log',"monitor_get_default($host_id) retured ".var_export($monitor_new_device,true)."\n",FILE_APPEND);
	return $monitor_new_device;
}

function monitor_api_device_save($save) {
	$monitor_default = monitor_get_default($save['id']);
	if (isset_request_var('monitor')) {
		//file_put_contents('/tmp/monitor.log',"monitor_api_device_save_var(".$save['id'].") retured ".var_export($monitor_default,true)."\n",FILE_APPEND);
		$save['monitor'] = form_input_validate(get_nfilter_request_var('monitor'), 'monitor', $monitor_default, true, 3);
	} else {
		//file_put_contents('/tmp/monitor.log',"monitor_api_device_save(".$save['id'].") retured ".var_export($monitor_default,true)."\n",FILE_APPEND);
		$save['monitor'] = form_input_validate($monitor_default, 'monitor', '', true, 3);
	}

	if (isset_request_var('monitor_text')) {
		$save['monitor_text'] = form_input_validate(get_nfilter_request_var('monitor_text'), 'monitor_text', '', true, 3);
	} else {
		$save['monitor_text'] = form_input_validate('', 'monitor_text', '', true, 3);
	}

	if (isset_request_var('monitor_criticality')) {
		$save['monitor_criticality'] = form_input_validate(get_nfilter_request_var('monitor_criticality'), 'monitor_criticality', '^[0-9]+$', true, 3);
	} else {
		$save['monitor_criticality'] = form_input_validate('', 'monitor_criticality', '', true, 3);
	}

	if (isset_request_var('monitor_warn')) {
		$save['monitor_warn'] = form_input_validate(get_nfilter_request_var('monitor_warn'), 'monitor_warn', '^[0-9]+$', true, 3);
	} else {
		$save['monitor_warn'] = form_input_validate('', 'monitor_warn', '', true, 3);
	}

	if (isset_request_var('monitor_alert')) {
		$save['monitor_alert'] = form_input_validate(get_nfilter_request_var('monitor_alert'), 'monitor_alert', '^[0-9]+$', true, 3);
	} else {
		$save['monitor_alert'] = form_input_validate('', 'monitor_alert', '', true, 3);
	}

	if (!isempty_request_var('monitor_alert_baseline') && !empty($save['id'])) {
		$cur_time = db_fetch_cell_prepared('SELECT cur_time
			FROM host
			WHERE id = ?',
			array($save['id']));

		if ($cur_time > 0) {
			$save['monitor_alert'] = ceil($cur_time * get_nfilter_request_var('monitor_alert_baseline'));
		}
	}

	if (!isempty_request_var('monitor_warn_baseline') && !empty($save['id'])) {
		$cur_time = db_fetch_cell_prepared('SELECT cur_time
			FROM host
			WHERE id = ?',
			array($save['id']));

		if ($cur_time > 0) {
			$save['monitor_warn'] = ceil($cur_time * get_nfilter_request_var('monitor_alert_baseline'));
		}
	}

	return $save;
}

function monitor_draw_navigation_text ($nav) {
   $nav['monitor.php:'] = array('title' => __('Monitoring', 'monitor'), 'mapping' => '', 'url' => 'monitor.php', 'level' => '0');

   return $nav;
}

function monitor_setup_table() {
	if (!db_table_exists('plugin_monitor_notify_history')) {
		db_execute("CREATE TABLE IF NOT EXISTS plugin_monitor_notify_history (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			host_id int(10) unsigned DEFAULT NULL,
			notify_type tinyint(3) unsigned DEFAULT NULL,
			ping_time double DEFAULT NULL,
			ping_threshold int(10) unsigned DEFAULT NULL,
			notification_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
			notes varchar(255) DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_key (host_id,notify_type,notification_time))
			ENGINE=InnoDB
			COMMENT='Stores Notification Event History'");
	}

	if (!db_table_exists('plugin_monitor_reboot_history')) {
		db_execute("CREATE TABLE IF NOT EXISTS plugin_monitor_reboot_history (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			host_id int(10) unsigned DEFAULT NULL,
			reboot_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
			log_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY host_id (host_id),
			KEY log_time (log_time),
			KEY reboot_time (reboot_time))
			ENGINE=InnoDB
			COMMENT='Keeps Track of Device Reboot Times'");
	}

	if (!db_table_exists('plugin_monitor_uptime')) {
		db_execute("CREATE TABLE IF NOT EXISTS plugin_monitor_uptime (
			host_id int(10) unsigned DEFAULT '0',
			uptime int(10) unsigned DEFAULT '0',
			PRIMARY KEY (host_id),
			KEY uptime (uptime))
			ENGINE=InnoDB
			COMMENT='Keeps Track of the Devices last uptime to track agent restarts and reboots'");
	}

	if (!db_table_exists('plugin_monitor_dashboards')) {
		db_execute("CREATE TABLE IF NOT EXISTS plugin_monitor_dashboards (
			id int(10) unsigned auto_increment,
			user_id int(10) unsigned DEFAULT '0',
			name varchar(128) DEFAULT '',
			url varchar(1024) DEFAULT '',
			PRIMARY KEY (id),
			KEY user_id (user_id))
			ENGINE=InnoDB
			COMMENT='Stores predefined dashboard information for a user or users'");
	}

	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor', 'type' => 'char(3)', 'NULL' => false, 'default' => 'on', 'after' => 'disabled'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_text', 'type' => 'varchar(1024)', 'default' => '', 'NULL' => false, 'after' => 'monitor'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_criticality', 'type' => 'tinyint', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'monitor_text'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_warn', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'monitor_criticality'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_alert', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'monitor_warn'));
}

function monitor_poller_bottom() {
	global $config;

	include_once($config['library_path'] . '/poller.php');

    $command_string = trim(read_config_option('path_php_binary'));

    if (trim($command_string) == '') {
        $command_string = 'php';
	}

    $extra_args = ' -q ' . $config['base_path'] . '/plugins/monitor/poller_monitor.php';

    exec_background($command_string, $extra_args);
}
