<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2016 The Cacti Group                                 |
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

function plugin_monitor_install () {
	api_plugin_register_hook('monitor', 'top_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'draw_navigation_text', 'monitor_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('monitor', 'config_form', 'monitor_config_form', 'setup.php');
	api_plugin_register_hook('monitor', 'api_device_save', 'monitor_api_device_save', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_refresh', 'monitor_top_graph_refresh', 'setup.php');
	api_plugin_register_hook('monitor', 'config_settings', 'monitor_config_settings', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_array', 'monitor_device_action_array', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_execute', 'monitor_device_action_execute', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_prepare', 'monitor_device_action_prepare', 'setup.php');

	api_plugin_register_realm('monitor', 'monitor.php', 'View Monitoring', 1);
	monitor_setup_table();
}

function plugin_monitor_uninstall () {
	db_remove_column('monitor');
	db_remove_column('monitor_text');
	db_remove_column('monitor_criticality');
	db_remove_column('monitor_warn');
	db_remove_column('monitor_alert');
	// Do any extra Uninstall stuff here
}

function plugin_monitor_check_config () {
	global $config;
	// Here we will check to ensure everything is configured
	monitor_check_upgrade ();

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

function plugin_monitor_upgrade () {
	// Here we will upgrade to the newest version
	monitor_check_upgrade ();
	return false;
}

function monitor_version () {
	return plugin_monitor_version();
}

function monitor_check_upgrade () {
	$version = plugin_monitor_version ();
	$current = $version['version'];
	$old     = read_config_option('plugin_monitor_version');
	if ($current != $old) {
		monitor_setup_table ();

		// Set the new version
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='monitor'");
		db_execute("UPDATE plugin_config SET 
			version='" . $version['version'] . "', 
			name='"    . $version['longname'] . "', 
			author='"  . $version['author'] . "', 
			webpage='" . $version['url'] . "' 
			WHERE directory='" . $version['name'] . "' ");
	}
}

function plugin_monitor_version () {
	return array(
		'name'     => 'monitor',
		'version'  => '2.0',
		'longname' => 'Device Monitoring',
		'author'   => 'Jimmy Conner',
		'homepage' => 'http://cactiusers.org',
		'email'    => 'jimmy@sqmail.org',
		'url'      => 'http://versions.cactiusers.org/'
	);
}

function monitor_device_action_execute($action) {
	global $config;

	if ($action != 'monitor_enable' && $action != 'monitor_disable' && $action != 'monitor_settings') {
		return $action;
	}

	$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

	if ($selected_items != false) {
		if ($action == 'monitor_enable' || $action == 'monitor_disable') {
			for ($i = 0; ($i < count($selected_items)); $i++) {
				if ($action == 'monitor_enable') {
					db_execute("UPDATE host SET monitor='on' WHERE id='" . $selected_items[$i] . "'");
				}else if ($action == 'monitor_disable') {
					db_execute("UPDATE host SET monitor='' WHERE id='" . $selected_items[$i] . "'");
				}
			}
		}else{
			for ($i = 0; ($i < count($selected_items)); $i++) {
				reset($fields_host_edit);
				while (list($field_name, $field_array) = each($fields_host_edit)) {
					if (isset_request_var("t_$field_name")) {
						db_execute_prepared("UPDATE host SET $field_name = ? WHERE id = ?", array(get_nfilter_request_var($field_name), $selected_items[$i]));
						cacti_log(sprintf("UPDATE host SET $field_name = %s WHERE id = %s", get_nfilter_request_var($field_name), $selected_items[$i]));
					}
				}
			}
		}
	}

	return $action;
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
				<p>" . __('Click \'Continue\' to %s monitoring on these Device(s)', $action_description) . "</p>
				<p><ul>" . $save['host_list'] . "</ul></p>
			</td>
		</tr>";
	} else {
		print "<tr>
			<td colspan='2' class='even'>
				<p>" . __('Click \'Continue\' to Change the Monitoring settings for the following Device(s)') . "</p>
				<p><ul>" . $save['host_list'] . "</ul></p>
			</td>
		</tr>";

		$form_array = array();
		$fields = array('monitor', 'monitor_text', 'monitor_criticality', 'monitor_warn', 'monitor_alert');
		foreach($fields as $field) {
			$form_array += array($field => $fields_host_edit[$field]);

			$form_array[$field]['value'] = '';
			$form_array[$field]['form_id'] = 0;
			$form_array[$field]['sub_checkbox'] = array(
				'name' => 't_' . $field,
				'friendly_name' => __('Update this Field'),
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
	$device_action_array['monitor_settings'] = __('Change Monitoring Options');
	$device_action_array['monitor_enable']   = __('Enable Monitoring');
	$device_action_array['monitor_disable']  = __('Disable Monitoring');

	return $device_action_array;
}

function monitor_scan_dir() {
	global $config;

	$ext   = array('.wav', '.mp3');
	$d     = dir($config['base_path'] . '/plugins/monitor/sounds/');
	$files = array('None' => 'None');

	while (false !== ($entry = $d->read())) {
		if ($entry != '.' && $entry != '..' && in_array(strtolower(substr($entry,-4)),$ext)) {
			$files[$entry] = $entry;
		}
	}
	$d->close();

	return $files;
}

function monitor_config_settings() {
	global $tabs, $settings, $criticalities, $page_refresh_interval;

	$criticalities = array(
		0 => __('Disabled'),
		1 => __('Low'),
		2 => __('Medium'),
		3 => __('High'),
		4 => __('Mission Critical')
	);

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php') {
		return;
	}

	$tabs['misc'] = 'Misc';

	$temp = array(
		'monitor_header' => array(
			'friendly_name' => __('Monitor'),
			'method' => 'spacer',
		),
		'monitor_sound' => array(
			'friendly_name' => __('Alarm Sound'),
			'description' => __('This is the sound file that will be played when a Device goes down.'),
			'method' => 'drop_array',
			'array' => monitor_scan_dir(),
			'default' => 'attn-noc.wav',
		),
		'monitor_warn_criticality' => array(
			'friendly_name' => __('Warning Latency Notification'),
			'description' => __('If a Device has a Round Trip Ping Latency above the Warning Threshold and above the Criticality below, subscribing emails to the Device will receive an email notification.  Select \'Disabled\' to Disable.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $criticalities
		),
		'monitor_alert_criticality' => array(
			'friendly_name' => __('Alert Latency Notification'),
			'description' => __('If a Device has a Round Trip Ping Latency above the Alert Threshold and above the Criticality below, subscribing emails to the Device will receive an email notification.  Select \'Disabled\' to Disable.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $criticalities
		),
		'monitor_refresh' => array(
			'friendly_name' => __('Refresh Interval'),
			'description' => __('This is the time in seconds before the page refreshes.  (1 - 300)'),
			'method' => 'drop_array',
			'default' => '60',
			'array' => $page_refresh_interval
		),
		'monitor_legend' => array(
			'friendly_name' => __('Show Icon Legend'),
			'description' => __('Check this to show an icon legend on the Monitor display'),
			'method' => 'checkbox',
		),
		'monitor_grouping' => array(
			'friendly_name' => __('Grouping'),
			'description' => __('This is how monitor will Group Devices.'),
			'method' => 'drop_array',
			'default' => __('Default'),
			'array' => array(
				'default'                  => __('Default'),
				'default_by_permissions'   => __('Default with permissions'),
				'group_by_tree'            => __('Tree'),
				'group_by_device_template' => __('Device Template'),
			)
		),
		'monitor_view' => array(
			'friendly_name' => __('View'),
			'description' => __('This is how monitor will render Devices.'),
			'method' => 'drop_array',
			'default' => __('Default'),
			'array' => array(
				'default'  => __('Default'),
				'tiles'    => __('Tiles'),
				'tilesadt' => __('Tiles & Downtime')
			)
		)
	);

	if (isset($settings['misc'])) {
		$settings['misc'] = array_merge($settings['misc'], $temp);
	} else {
		$settings['misc']=$temp;
	}
}

function monitor_top_graph_refresh($refresh) {
	if (basename($_SERVER['PHP_SELF']) != 'monitor.php') {
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

	monitor_check_upgrade ();

	if (api_user_realm_auth('monitor.php')) {
		if (substr_count($_SERVER['REQUEST_URI'], 'monitor.php')) {
			print '<a href="' . $config['url_path'] . 'plugins/monitor/monitor.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor_down.gif" alt="' . __('Monitor') . '" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/monitor/monitor.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor.gif" alt="' . __('Monitor') . '" align="absmiddle" border="0"></a>';
		}
	}
}

function monitor_config_form () {
	global $fields_host_edit, $criticalities;

	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = array();
	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;
		if ($f == 'disabled') {
			$fields_host_edit3['monitor'] = array(
				'method' => 'checkbox',
				'friendly_name' => __('Monitor Device'),
				'description' => __('Check this box to monitor this Device on the Monitor Tab.'),
				'value' => '|arg1:monitor|',
				'default' => '',
				'form_id' => false
			);
			$fields_host_edit3['monitor_criticality'] = array(
				'friendly_name' => __('Device Criticality'),
				'description' => __('What is the Criticality of this Device.'),
				'method' => 'drop_array',
				'array' => $criticalities,
				'value' => '|arg1:monitor_criticalities|',
				'default' => '0',
			);
			$fields_host_edit3['monitor_warn'] = array(
				'friendly_name' => __('Ping Warning Threshold'),
				'description' => __('If the round trip latency via any of the predefined Cacti ping methods raises above this threshold, log a warning or send email based upon the Devices Criticality and Monitor setting.  The unit is in milliseconds.  Setting to 0 disables.'),
				'method' => 'textbox',
				'size' => '10',
				'max_length' => '5',
				'placeholder' => 'milliseconds',
				'value' => '|arg1:monitor_criticalities|',
				'default' => '',
			);
			$fields_host_edit3['monitor_alert'] = array(
				'friendly_name' => __('Ping Alert Threshold'),
				'description' => __('If the round trip latency via any of the predefined Cacti ping methods raises above this threshold, log an alert or send an email based upon the Devices Criticality and Monitor setting.  The unit is in milliseconds.  Setting to 0 disables.'),
				'method' => 'textbox',
				'size' => '10',
				'max_length' => '5',
				'placeholder' => 'milliseconds',
				'value' => '|arg1:monitor_criticalities|',
				'default' => '',
			);
			$fields_host_edit3['monitor_text'] = array(
				'friendly_name' => __('Down Device Message'),
				'description' => __('This is the message that will be displayed when this Device is reported as down.'),
				'method' => 'textarea',
				'max_length' => 1000,
				'textarea_rows' => 3,
				'textarea_cols' => 30,
				'value' => '|arg1:monitor_text|',
				'default' => '',
			);
		}
	}
	$fields_host_edit = $fields_host_edit3;
}

function monitor_api_device_save ($save) {
	if (isset_request_var('monitor')) {
		$save['monitor'] = form_input_validate(get_nfilter_request_var('monitor'), 'monitor', '', true, 3);
	} else {
		$save['monitor'] = form_input_validate('', 'monitor', '', true, 3);
	}

	if (isset_request_var('monitor_text')) {
		$save['monitor_text'] = form_input_validate(get_nfilter_request_var('monitor_text'), 'monitor_text', '', true, 3);
	} else {
		$save['monitor_text'] = form_input_validate('', 'monitor_text', '', true, 3);
	}

	if (isset_request_var('monitor_criticality')) {
		$save['monitor_criticality'] = form_input_validate(get_nfilter_request_var('monitor_criticality'), 'monitor_criticality', '', true, 3);
	} else {
		$save['monitor_criticality'] = form_input_validate('', 'monitor_criticality', '', true, 3);
	}

	if (isset_request_var('monitor_warn')) {
		$save['monitor_warn'] = form_input_validate(get_nfilter_request_var('monitor_warn'), 'monitor_warn', '', true, 3);
	} else {
		$save['monitor_warn'] = form_input_validate('', 'monitor_warn', '', true, 3);
	}

	if (isset_request_var('monitor_alert')) {
		$save['monitor_alert'] = form_input_validate(get_nfilter_request_var('monitor_alert'), 'monitor_alert', '', true, 3);
	} else {
		$save['monitor_alert'] = form_input_validate('', 'monitor_alert', '', true, 3);
	}

	return $save;
}

function monitor_draw_navigation_text ($nav) {
   $nav['monitor.php:'] = array('title' => __('Monitoring'), 'mapping' => '', 'url' => 'monitor.php', 'level' => '1');

   return $nav;
}

function monitor_setup_table() {
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor', 'type' => 'char(3)', 'NULL' => false, 'default' => 'on', 'after' => 'disabled'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_text', 'type' => 'text', 'NULL' => false, 'after' => 'monitor'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_criticality', 'type' => 'tinyint', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'monitor_text'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_warn', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'monitor_criticality'));
	api_plugin_db_add_column ('monitor', 'host', array('name' => 'monitor_alert', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'monitor_warn'));
}

