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
 * Plugin install hook: registers all of this plugin's Cacti hooks
 * (header tabs, navigation text, config form/settings/arrays,
 * poller_bottom, page_head, device save/action/remove integration, and
 * device list filter/SQL/table hooks), registers its viewer realm, sets
 * default settings, and creates its database tables. Called by Cacti's
 * plugin architecture when the plugin is installed.
 *
 * @return void
 */
function plugin_monitor_install() {
	// core plugin functionality
	api_plugin_register_hook('monitor', 'top_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_header_tabs', 'monitor_show_tab', 'setup.php');
	api_plugin_register_hook('monitor', 'top_graph_refresh', 'monitor_top_graph_refresh', 'setup.php');

	api_plugin_register_hook('monitor', 'draw_navigation_text', 'monitor_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('monitor', 'config_form', 'monitor_config_form', 'setup.php');
	api_plugin_register_hook('monitor', 'config_settings', 'monitor_config_settings', 'setup.php');
	api_plugin_register_hook('monitor', 'config_arrays', 'monitor_config_arrays', 'setup.php');
	api_plugin_register_hook('monitor', 'poller_bottom', 'monitor_poller_bottom', 'setup.php');
	api_plugin_register_hook('monitor', 'page_head', 'plugin_monitor_page_head', 'setup.php');

	// device actions and interaction
	api_plugin_register_hook('monitor', 'api_device_save', 'monitor_api_device_save', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_array', 'monitor_device_action_array', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_execute', 'monitor_device_action_execute', 'setup.php');
	api_plugin_register_hook('monitor', 'device_action_prepare', 'monitor_device_action_prepare', 'setup.php');
	api_plugin_register_hook('monitor', 'device_remove', 'monitor_device_remove', 'setup.php');

	// add new filter for device
	api_plugin_register_hook('monitor', 'device_filters', 'monitor_device_filters', 'setup.php');
	api_plugin_register_hook('monitor', 'device_sql_where', 'monitor_device_sql_where', 'setup.php');
	api_plugin_register_hook('monitor', 'device_table_bottom', 'monitor_device_table_bottom', 'setup.php');

	api_plugin_register_realm('monitor', 'monitor.php', 'View Monitoring Dashboard', 1);

	set_config_option('monitor_view', 'default');
	set_config_option('monitor_grouping', 'default');
	set_config_option('monitor_trim', '4000');
	set_config_option('monitor_rows', 100);

	monitor_setup_table();
}

/**
 * Device_filters hook: adds a 'Criticality' filter option to Cacti's
 * device list filter form. Called by Cacti's host list page via the
 * 'device_filters' hook.
 *
 * @param array $filters The device list's filter definitions array
 *                       being built up.
 *
 * @return array The $filters array with the criticality filter added.
 */
function monitor_device_filters($filters) {
	$criticalities = [
		'-1' => __('Any', 'monitor'),
		'0'  => __('None', 'monitor'),
		'1'  => __('Low', 'monitor'),
		'2'  => __('Medium', 'monitor'),
		'3'  => __('High', 'monitor'),
		'4'  => __('Mission Critical', 'monitor')
	];

	$filters['criticality'] = [
		'friendly_name' => __('Criticality', 'monitor'),
		'method'        => 'drop_array',
		'filter'        => FILTER_VALIDATE_INT,
		'pageset'       => true,
		'default'       => '-1',
		'array'         => $criticalities,
		'value'         => '-1'
	];

	return $filters;
}

/**
 * Device_sql_where hook: appends a criticality condition to the device
 * list's SQL WHERE clause when a specific criticality filter is
 * selected. Called by Cacti's host list page via the
 * 'device_sql_where' hook.
 *
 * @param string $sql_where The SQL WHERE clause being built up.
 *
 * @return string The $sql_where string with the criticality condition
 *               appended, if applicable.
 */
function monitor_device_sql_where($sql_where) {
	if (get_request_var('criticality') >= 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' monitor_criticality = ' . get_request_var('criticality');
	}

	return $sql_where;
}

/**
 * Device_table_bottom hook: on Cacti versions prior to 1.3.0 (which
 * lack native support for plugin-added filter fields), injects the
 * criticality filter's &lt;select&gt; element into the device list's
 * filter row via client-side JS and wires up its change handler to
 * reapply the page filter. On 1.3.0+, this is a no-op since the
 * 'device_filters' hook handles rendering natively. Called by Cacti's
 * host list page via the 'device_table_bottom' hook.
 *
 * @return void
 */
function monitor_device_table_bottom() {
	$criticalities = [
		'-1' => __('Any', 'monitor'),
		'0'  => __('None', 'monitor'),
		'1'  => __('Low', 'monitor'),
		'2'  => __('Medium', 'monitor'),
		'3'  => __('High', 'monitor'),
		'4'  => __('Mission Critical', 'monitor')
	];

	if (version_compare(CACTI_VERSION, '1.3.0', '<')) {
		$select = '<td>' . __('Criticality') . '</td><td><select id="criticality">';

		foreach ($criticalities as $index => $crit) {
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
			$('#rows').parent().after('<?php print $select; ?>');
			<?php if (get_selected_theme() != 'classic') {?>
			/* Cacti core may have already converted this select to select2 by
			 * the time this runs; don't also layer a selectmenu widget on it,
			 * but still wire up applyFilter() either way */
			if (!$('#criticality').hasClass('select2-hidden-accessible')) {
				$('#criticality').selectmenu({
					change: function() {
						applyFilter();
					}
				});
			} else {
				$('#criticality').on('change', function() {
					applyFilter();
				});
			}
			<?php } else { ?>
			$('#criticality').change(function() {
				applyFilter();
			});
			<?php } ?>
		});

		applyFilter = function() {
			strURL  = 'host.php';
			strURL += '?host_status=' + $('#host_status').val();

			if ($('#availability_method').length) {
				strURL += '&availability_method=' + $('#availability_method').val();
			}

			strURL += '&host_template_id=' + $('#host_template_id').val();
			strURL += '&site_id=' + $('#site_id').val();
			strURL += '&criticality=' + $('#criticality').val();
			strURL += '&poller_id=' + $('#poller_id').val();
			strURL += '&location=' + $('#location').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&header=false';

			if (typeof loadUrl == 'undefined') {
				loadPageNoHeader(strURL);
			} else {
				loadUrl({ url: strURL });
			}
		};

		</script>
		<?php
	}
}

/**
 * Plugin uninstall hook: drops this plugin's notify history, reboot
 * history, and uptime tables. Called by Cacti's plugin architecture
 * when the plugin is uninstalled.
 *
 * @return void
 */
function plugin_monitor_uninstall() {
	db_execute('DROP TABLE IF EXISTS plugin_monitor_notify_history');
	db_execute('DROP TABLE IF EXISTS plugin_monitor_reboot_history');
	db_execute('DROP TABLE IF EXISTS plugin_monitor_uptime');
}

/**
 * Page_head hook: emits the &lt;style&gt; tags for the plugin's common
 * stylesheet and, if present, the currently selected theme's monitor
 * stylesheet. Called by Cacti's page rendering via the 'page_head'
 * hook.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       check for the theme stylesheet's existence.
 */
function plugin_monitor_page_head() {
	global $config;

	print get_md5_include_css('plugins/monitor/css/monitor.css') . PHP_EOL;

	if (file_exists($config['base_path'] . '/plugins/monitor/themes/' . get_selected_theme() . '/monitor.css')) {
		print get_md5_include_css('plugins/monitor/themes/' . get_selected_theme() . '/monitor.css') . PHP_EOL;
	}
}

/**
 * Plugin config-check hook: ensures the plugin's schema is up to date
 * by delegating to monitor_check_upgrade(), and normalizes the
 * configured refresh interval to a sane 1-300 second range. Called by
 * Cacti's plugin architecture on relevant page loads.
 *
 * @return bool Always true.
 *
 * @global array $config Cacti global configuration array; used to
 *                       include the database library.
 */
function plugin_monitor_check_config() {
	global $config;
	// Here we will check to ensure everything is configured
	monitor_check_upgrade();

	include_once($config['library_path'] . '/database.php');
	$r = read_config_option('monitor_refresh');

	if ($r == '' || $r < 1 || $r > 300) {
		set_config_option('monitor_refresh', '300');
	}

	return true;
}

/**
 * Plugin upgrade hook: brings the plugin's schema up to date by
 * delegating to monitor_check_upgrade(). Called by Cacti's plugin
 * architecture when the plugin is upgraded to a new version.
 *
 * @return bool Always false.
 */
function plugin_monitor_upgrade() {
	// Here we will upgrade to the newest version
	monitor_check_upgrade();

	return false;
}

/**
 * Checks whether the plugin's recorded database version differs from
 * its actual (INFO file) version and, if so, re-creates/updates its
 * database tables and columns, re-registers its page_head hook, and
 * updates the plugin_config record. Only runs on plugins.php/
 * monitor.php page loads. Called from plugin_monitor_check_config()
 * and plugin_monitor_upgrade().
 *
 * @return void
 */
function monitor_check_upgrade() {
	$files = ['plugins.php', 'monitor.php'];

	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files, true)) {
		return;
	}

	$info    = plugin_monitor_version();
	$current = $info['version'];
	$old     = db_fetch_cell('SELECT version FROM plugin_config WHERE directory = "monitor"');

	if ($current != $old) {
		monitor_setup_table();

		api_plugin_register_hook('monitor', 'page_head', 'plugin_monitor_page_head', 'setup.php', 1);

		db_execute('ALTER TABLE host MODIFY COLUMN monitor char(3) DEFAULT "on"');

		db_execute('ALTER TABLE plugin_monitor_uptime
			MODIFY COLUMN uptime BIGINT unsigned NOT NULL default "0"');

		api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_icon', 'type' => 'varchar(30)', 'NULL' => false, 'default' => '']);

		if (function_exists('api_plugin_upgrade_register')) {
			api_plugin_upgrade_register('monitor');
		} else {
			db_execute_prepared('UPDATE plugin_config
				SET version = ?, name = ?, author = ?, webpage = ?
				WHERE directory = ?',
				[
					$info['version'],
					$info['longname'],
					$info['author'],
					$info['homepage'],
					$info['name']
				]
			);
		}
	}
}

/**
 * Reads and returns this plugin's version/author/metadata info from its
 * INFO file. Called wherever plugin metadata is needed (e.g.
 * monitor_check_upgrade()).
 *
 * @return array The plugin's info array, as parsed from the INFO
 *              file's '[info]' section.
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the plugin's INFO file.
 */
function plugin_monitor_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/monitor/INFO', true);

	return $info['info'];
}

/**
 * Device_action_execute hook: performs the selected bulk device action
 * (enabling/disabling monitoring, or applying monitoring settings field
 * updates, including computing baseline-relative warn/alert thresholds)
 * against each selected device. Called by Cacti's host list bulk-action
 * handling via the 'device_action_execute' hook.
 *
 * @param string $action The bulk action name being executed.
 *
 * @return string The unmodified $action value.
 *
 * @global array $config           Reserved/declared for parity with
 *                                other functions in this file; not
 *                                used directly here.
 * @global array $fields_host_edit The host edit form's field
 *                                definitions, used to determine which
 *                                monitoring fields were submitted for
 *                                update.
 */
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
					db_execute_prepared('UPDATE host
						SET monitor = "on"
						WHERE deleted = ""
						AND id = ?',
						[$selected_items[$i]]);
				} elseif ($action == 'monitor_disable') {
					db_execute_prepared('UPDATE host
						SET monitor = ""
						WHERE deleted = ""
						AND id = ?',
						[$selected_items[$i]]);
				}
			}
		} else {
			for ($i = 0; ($i < count($selected_items)); $i++) {
				reset($fields_host_edit);

				foreach ($fields_host_edit as $field_name => $field_array) {
					if (isset_request_var("t_$field_name")) {
						if ($field_name == 'monitor_alert_baseline') {
							$cur_time = db_fetch_cell_prepared('SELECT cur_time
								FROM host
								WHERE deleted = ""
								AND id = ?',
								[$selected_items[$i]]);

							if ($cur_time > 0) {
								db_execute_prepared('UPDATE host
									SET monitor_alert = CEIL(avg_time*?)
									WHERE deleted = ""
									AND id = ?',
									[get_nfilter_request_var($field_name), $selected_items[$i]]);
							}
						} elseif ($field_name == 'monitor_warn_baseline') {
							$cur_time = db_fetch_cell_prepared('SELECT cur_time
								FROM host
								WHERE deleted = ""
								AND id = ?',
								[$selected_items[$i]]);

							if ($cur_time > 0) {
								db_execute_prepared('UPDATE host
									SET monitor_warn = CEIL(avg_time*?)
									WHERE deleted = ""
									AND id = ?',
									[get_nfilter_request_var($field_name), $selected_items[$i]]);
							}
						} else {
							db_execute_prepared("UPDATE host
								SET $field_name = ?
								WHERE deleted=''
								AND id = ?",
								[get_nfilter_request_var($field_name), $selected_items[$i]]);
						}
					}
				}
			}
		}
	}

	return $action;
}

/**
 * Device_remove hook: cleans up all of this plugin's per-device data
 * (notify/reboot history, uptime) for one or more deleted devices.
 * Called by Cacti's host admin via the 'device_remove' hook.
 *
 * @param array $devices The list of deleted device ids.
 *
 * @return array The unmodified $devices array.
 */
function monitor_device_remove($devices) {
	db_execute('DELETE FROM plugin_monitor_notify_history WHERE host_id IN(' . implode(',', $devices) . ')');
	db_execute('DELETE FROM plugin_monitor_reboot_history WHERE host_id IN(' . implode(',', $devices) . ')');
	db_execute('DELETE FROM plugin_monitor_uptime WHERE host_id IN(' . implode(',', $devices) . ')');

	return $devices;
}

/**
 * Device_action_prepare hook: renders the bulk-action confirmation page
 * content for this plugin's device actions - a simple confirmation
 * listing for enable/disable, or a settings-update form (with
 * per-field 'update this field' checkboxes) for the monitoring settings
 * action. Called by Cacti's host list bulk-action confirmation page via
 * the 'device_action_prepare' hook.
 *
 * @param array $save The bulk-action save context, including
 *                    'drp_action' and 'host_list'.
 *
 * @return array The unmodified $save array.
 *
 * @global array $host_list        Reserved/declared for parity with
 *                                other functions in this file; not
 *                                used directly here.
 * @global array $fields_host_edit The host edit form's field
 *                                definitions, used as the basis for
 *                                the settings-update form fields.
 */
function monitor_device_action_prepare($save) {
	global $host_list, $fields_host_edit;

	if (!isset($save['drp_action'])) {
		return $save;
	} else {
		$action = $save['drp_action'];

		if ($action != 'monitor_enable' && $action != 'monitor_disable' && $action != 'monitor_settings') {
			return $save;
		}

		if ($action == 'monitor_enable' || $action == 'monitor_disable') {
			if ($action == 'monitor_enable') {
				$action_description = 'enable';
			} elseif ($action == 'monitor_disable') {
				$action_description = 'disable';
			}

			print "<tr>
				<td colspan='2' class='even'>
					<p>" . __('Click \'Continue\' to %s monitoring on these Device(s)', $action_description, 'monitor') . "</p>
					<p><div class='itemlist'><ul>" . $save['host_list'] . '</ul></div></p>
				</td>
			</tr>';
		} else {
			print "<tr>
				<td colspan='2' class='even'>
					<p>" . __('Click \'Continue\' to Change the Monitoring settings for the following Device(s). Remember to check \'Update this Field\' to indicate which columns to update.', 'monitor') . "</p>
					<p><div class='itemlist'><ul>" . $save['host_list'] . '</ul></div></p>
				</td>
			</tr>';

			$form_array = [];
			$fields     = [
				'monitor',
				'monitor_text',
				'monitor_criticality',
				'monitor_warn',
				'monitor_alert',
				'monitor_warn_baseline',
				'monitor_alert_baseline',
				'monitor_icon'
			];

			foreach ($fields as $field) {
				$form_array += [$field => $fields_host_edit[$field]];

				$form_array[$field]['value']        = '';
				$form_array[$field]['form_id']      = 0;
				$form_array[$field]['sub_checkbox'] = [
					'name'          => 't_' . $field,
					'friendly_name' => __('Update this Field', 'monitor'),
					'value'         => ''
				];
			}

			draw_edit_form(
				[
					'config' => ['no_form_tag' => true],
					'fields' => $form_array
				]
			);
		}

		return $save;
	}
}

/**
 * Device_action_array hook: registers this plugin's bulk device actions
 * (change monitoring options, enable/disable monitoring) in the host
 * list's bulk-actions dropdown. Called by Cacti's host list via the
 * 'device_action_array' hook.
 *
 * @param array $device_action_array Map of action value => label being
 *                                   built up.
 *
 * @return array The $device_action_array with this plugin's actions
 *              added.
 */
function monitor_device_action_array($device_action_array) {
	$device_action_array['monitor_settings'] = __('Change Monitoring Options', 'monitor');
	$device_action_array['monitor_enable']   = __('Enable Monitoring', 'monitor');
	$device_action_array['monitor_disable']  = __('Disable Monitoring', 'monitor');

	return $device_action_array;
}

/**
 * Scans the plugin's sounds/ directory for available alert sound files
 * (.wav/.mp3), returning them as a settings option list with a 'None'
 * option prepended. Called from monitor_config_settings() to populate
 * the alert-sound selection dropdowns.
 *
 * @return array Map of filename => filename for each available sound
 *              file, plus a leading 'None' entry.
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the sounds directory.
 */
function monitor_scan_dir() {
	global $config;

	$ext   = ['.wav', '.mp3'];
	$d     = dir($config['base_path'] . '/plugins/monitor/sounds/');
	$files = [];

	while (false !== ($entry = $d->read())) {
		if ($entry != '.' && $entry != '..' && in_array(strtolower(substr($entry,-4)),$ext, true)) {
			$files[$entry] = $entry;
		}
	}
	$d->close();
	asort($files); // sort the files
	array_unshift($files, 'None'); // prepend the None option

	return $files;
}

/**
 * Config_settings hook: registers this plugin's 'Monitor' settings tab
 * and all of its configuration fields (criticality levels, refresh
 * interval, alert sounds, report format, and related monitoring
 * options). Called by Cacti's settings framework via the
 * 'config_settings' hook.
 *
 * @return void
 *
 * @global array $tabs                   Cacti's settings tabs
 *                                       registry; appended with this
 *                                       plugin's tab.
 * @global array $formats                Populated here with the
 *                                       available report format files
 *                                       when on this plugin's settings
 *                                       tab.
 * @global array $settings               Cacti's settings fields
 *                                       registry; appended with this
 *                                       plugin's fields.
 * @global array $criticalities          Populated here with the map of
 *                                       criticality level => display
 *                                       label.
 * @global array $page_refresh_interval  Cacti's page-refresh-interval
 *                                       option list (value => label);
 *                                       used directly as the
 *                                       'monitor_refresh' setting
 *                                       field's dropdown options.
 * @global array $config                 Cacti global configuration
 *                                       array; used to include the
 *                                       reports library.
 * @global array $settings_user          Reserved/declared for parity
 *                                       with other functions in this
 *                                       file; not used directly here.
 * @global array $tabs_graphs            Reserved/declared for parity
 *                                       with other functions in this
 *                                       file; not used directly here.
 */
function monitor_config_settings() {
	global $tabs, $formats, $settings, $criticalities, $page_refresh_interval, $config, $settings_user, $tabs_graphs;

	include_once($config['base_path'] . '/lib/reports.php');

	if (get_nfilter_request_var('tab') == 'monitor') {
		$formats = reports_get_format_files();
	} elseif (empty($formats)) {
		$formats = [];
	}

	$criticalities = [
		0 => __('Disabled', 'monitor'),
		1 => __('Low', 'monitor'),
		2 => __('Medium', 'monitor'),
		3 => __('High', 'monitor'),
		4 => __('Mission Critical', 'monitor')
	];

	$log_retentions = [
		'-1'  => __('Indefinitely', 'monitor'),
		'31'  => __('%d Month', 1, 'monitor'),
		'62'  => __('%d Months', 2, 'monitor'),
		'93'  => __('%d Months', 3, 'monitor'),
		'124' => __('%d Months', 4, 'monitor'),
		'186' => __('%d Months', 6, 'monitor'),
		'365' => __('%d Year', 1, 'monitor')
	];

	$font_sizes = [
		'20' => '20px',
		'30' => '30px',
		'40' => '40px',
		'50' => '50px',
		'60' => '60px',
		'70' => '70px'
	];

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles(__('Normal User'), ['monitor.php']);
	}

	$tabs_graphs += ['monitor' => __('Monitor Settings', 'monitor')];

	$settings_user += [
		'monitor' => [
			'monitor_sound' => [
				'friendly_name' => __('Alarm Sound', 'monitor'),
				'description'   => __('This is the sound file that will be played when a Device goes down.', 'monitor'),
				'method'        => 'drop_array',
				'array'         => monitor_scan_dir(),
				'default'       => 'attn-noc.wav',
			],
			'monitor_sound_loop' => [
				'friendly_name' => __('Loop Alarm Sound', 'monitor'),
				'description'   => __('Play the above sound on a loop when a Device goes down.', 'monitor'),
				'method'        => 'checkbox',
			],
			'monitor_legend' => [
				'friendly_name' => __('Show Icon Legend', 'monitor'),
				'description'   => __('Check this to show an icon legend on the Monitor display', 'monitor'),
				'method'        => 'checkbox',
			],
			'monitor_uptime' => [
				'friendly_name' => __('Show Uptime', 'monitor'),
				'description'   => __('Check this to show Uptime on the Monitor display', 'monitor'),
				'method'        => 'checkbox',
			],
			'monitor_error_zoom' => [
				'friendly_name' => __('Zoom to Errors', 'monitor'),
				'description'   => __('Check this to zoom to errored items on the Monitor display', 'monitor'),
				'method'        => 'checkbox',
			],
			'monitor_error_background' => [
				'friendly_name' => __('Zoom Background', 'monitor'),
				'description'   => __('Background Color for Zoomed Errors on the Monitor display', 'monitor'),
				'method'        => 'drop_color',
			],
			'monitor_error_fontsize' => [
				'friendly_name' => __('Zoom Fontsize', 'monitor'),
				'description'   => __('Check this to zoom to errored items on the Monitor display', 'monitor'),
				'method'        => 'drop_array',
				'default'       => '50',
				'array'         => $font_sizes
			]
		]
	];

	if (get_current_page() != 'settings.php') {
		return;
	}

	$tabs['monitor'] = __('Monitor', 'monitor');

	$temp = [
		'monitor_header' => [
			'friendly_name' => __('Monitor Settings', 'monitor'),
			'method'        => 'spacer',
			'collapsible'   => 'true'
		],
		'monitor_new_enabled' => [
			'friendly_name' => __('Enable on new devices', 'monitor'),
			'description'   => __('Check this to automatically enable monitoring when creating new devices', 'monitor'),
			'method'        => 'checkbox',
		],
		'monitor_log_storage' => [
			'friendly_name' => __('Notification/Reboot Log Retention', 'monitor'),
			'description'   => __('Keep Notification and Reboot Logs for this number of days.', 'monitor'),
			'method'        => 'drop_array',
			'default'       => '31',
			'array'         => $log_retentions
		],
		'monitor_sound' => [
			'friendly_name' => __('Alarm Sound', 'monitor'),
			'description'   => __('This is the sound file that will be played when a Device goes down.', 'monitor'),
			'method'        => 'drop_array',
			'array'         => monitor_scan_dir(),
			'default'       => 'attn-noc.wav',
		],
		'monitor_sound_loop' => [
			'friendly_name' => __('Loop Alarm Sound', 'monitor'),
			'description'   => __('Play the above sound on a loop when a Device goes down.', 'monitor'),
			'method'        => 'checkbox',
		],
		'monitor_refresh' => [
			'friendly_name' => __('Refresh Interval', 'monitor'),
			'description'   => __('This is the time in seconds before the page refreshes.  (1 - 300)', 'monitor'),
			'method'        => 'drop_array',
			'default'       => '60',
			'array'         => $page_refresh_interval
		],
		'monitor_legend' => [
			'friendly_name' => __('Show Icon Legend', 'monitor'),
			'description'   => __('Check this to show an icon legend on the Monitor display', 'monitor'),
			'method'        => 'checkbox',
		],
		'monitor_grouping' => [
			'friendly_name' => __('Grouping', 'monitor'),
			'description'   => __('This is how monitor will Group Devices.', 'monitor'),
			'method'        => 'drop_array',
			'default'       => 'default',
			'array'         => [
				'default'                  => __('Default', 'monitor'),
				'default_by_permissions'   => __('Default with permissions', 'monitor'),
				'group_by_tree'            => __('Tree', 'monitor'),
				'group_by_device_template' => __('Device Template', 'monitor'),
			]
		],
		'monitor_view' => [
			'friendly_name' => __('View', 'monitor'),
			'description'   => __('This is how monitor will render Devices.', 'monitor'),
			'method'        => 'drop_array',
			'default'       => 'default',
			'array'         => [
				'default'  => __('Default', 'monitor'),
				'list'     => __('List', 'monitor'),
				'names'    => __('Names only', 'monitor'),
				'tiles'    => __('Tiles', 'monitor'),
				'tilesadt' => __('Tiles & Downtime', 'monitor')
			]
		],
		'monitor_format_header' => [
			'friendly_name' => __('Notification Report Format', 'monitor'),
			'method'        => 'spacer',
			'collapsible'   => 'true'
		],
		'monitor_format_file' => [
			'friendly_name' => __('Format File to Use', 'monitor'),
			'method'        => 'drop_array',
			'default'       => 'default.format',
			'description'   => __('Choose the custom html wrapper and CSS file to use.  This file contains both html and CSS to wrap around your report.  If it contains more than simply CSS, you need to place a special <REPORT> tag inside of the file.  This format tag will be replaced by the report content.  These files are located in the \'formats\' directory.', 'monitor'),
			'array'         => $formats
		],
		'monitor_threshold' => [
			'friendly_name' => __('Ping Threshold Notifications', 'monitor'),
			'method'        => 'spacer',
			'collapsible'   => 'true'
		],
		'monitor_warn_criticality' => [
			'friendly_name' => __('Warning Latency Notification', 'monitor'),
			'description'   => __('If a Device has a Round Trip Ping Latency above the Warning Threshold and above the Criticality below, subscribing emails to the Device will receive an email notification.  Select \'Disabled\' to Disable.  The Thold Plugin is required to enable this feature.', 'monitor'),
			'method'        => 'drop_array',
			'default'       => '0',
			'array'         => $criticalities
		],
		'monitor_alert_criticality' => [
			'friendly_name' => __('Alert Latency Notification', 'monitor'),
			'description'   => __('If a Device has a Round Trip Ping Latency above the Alert Threshold and above the Criticality below, subscribing emails to the Device will receive an email notification.  Select \'Disabled\' to Disable.  The Thold Plugin is required to enable this feature.', 'monitor'),
			'method'        => 'drop_array',
			'default'       => '0',
			'array'         => $criticalities
		],
		'monitor_resend_frequency' => [
			'friendly_name' => __('How Often to Resend Emails', 'monitor'),
			'description'   => __('How often should emails notifications be sent to subscribers for these Devices if they are exceeding their latency thresholds', 'monitor'),
			'method'        => 'drop_array',
			'default'       => '0',
			'array'         => [
				'0'   => __('Every Occurrence', 'monitor'),
				'20'  => __('Every %d Minutes', 20, 'monitor'),
				'30'  => __('Every %d Minutes', 30, 'monitor'),
				'60'  => __('Every Hour', 'monitor'),
				'120' => __('Every %d Hours', 2, 'monitor'),
				'240' => __('Every %d Hours', 4, 'monitor')
			]
		],
		'monitor_reboot' => [
			'friendly_name' => __('Reboot Notifications', 'monitor'),
			'method'        => 'spacer',
			'collapsible'   => 'true'
		],
		'monitor_reboot_notify' => [
			'friendly_name' => __('Send Reboot Notifications', 'monitor'),
			'method'        => 'checkbox',
			'description'   => __('Should Device Reboot Notifications be sent to users?', 'monitor'),
			'default'       => 'on',
		],
		'monitor_send_one_email' => [
			'friendly_name' => __('Send one Email to all addresses', 'monitor'),
			'description'   => __('If checked, the system will send one Email only to all addresses.', 'monitor'),
			'method'        => 'checkbox',
			'default'       => 'on'
		],
		'monitor_reboot_thold' => [
			'friendly_name' => __('Include Threshold Alert Lists', 'monitor'),
			'method'        => 'checkbox',
			'description'   => __('Should Threshold Alert Lists also receive Notification', 'monitor'),
			'default'       => 'on',
		],
		'monitor_subject' => [
			'friendly_name' => __('Subject', 'monitor'),
			'description'   => __('Enter a Reboot message subject for the Reboot Notification.', 'monitor'),
			'method'        => 'textbox',
			'default'       => __('Cacti Device Reboot Notification', 'monitor'),
			'size'          => 60,
			'max_length'    => 60
		],
		'monitor_body' => [
			'friendly_name' => __('Email Body', 'monitor'),
			'description'   => __('Enter an Email body to include in the Reboot Notification message.  Currently, the only supported replacement tag accepted is &#060;DETAILS&#062;', 'monitor'),
			'method'        => 'textarea',
			'textarea_rows' => 4,
			'textarea_cols' => 80,
			'default'       => __('<h1>Monitor Reboot Notification</h1><p>The following Device\'s were Rebooted.  See details below for additional information.</p><br><DETAILS>', 'monitor')
		],
		'monitor_email_header' => [
			'friendly_name' => __('Notification Email Addresses', 'monitor'),
			'method'        => 'spacer',
			'collapsible'   => 'true'
		],
		'monitor_fromname' => [
			'friendly_name' => __('From Name', 'monitor'),
			'description'   => __('Enter the Email Name to send the notifications from', 'monitor'),
			'method'        => 'textbox',
			'size'          => '60',
			'max_length'    => '255'
		],
		'monitor_fromemail' => [
			'friendly_name' => __('From Address', 'monitor'),
			'description'   => __('Enter the Email Address to send the notification from', 'monitor'),
			'method'        => 'textbox',
			'size'          => '60',
			'max_length'    => '255'
		],
		'monitor_list' => [
			'friendly_name' => __('Notification List', 'thold'),
			'description'   => __('Select a Notification List below.  All Emails subscribed to the notification list will be notified.', 'thold'),
			'method'        => 'drop_sql',
			'sql'           => 'SELECT id, name FROM plugin_notification_lists ORDER BY name',
			'default'       => '',
			'none_value'    => __('None', 'monitor')
		],
		'monitor_emails' => [
			'friendly_name' => __('Email Addresses', 'monitor'),
			'description'   => __('Enter a comma delimited list of Email addresses to inform of a reboot event.', 'monitor'),
			'method'        => 'textarea',
			'textarea_rows' => 2,
			'textarea_cols' => 80,
			'default'       => ''
		]
	];

	if (isset($settings['monitor'])) {
		$settings['monitor'] = array_merge($settings['monitor'], $temp);
	} else {
		$settings['monitor'] = $temp;
	}
}

/**
 * Config_arrays hook: initializes the available Font Awesome device-
 * status icon option list (falling back to a plain display-label map on
 * older Cacti versions lacking form_dropicon()) and triggers a schema
 * upgrade check. Called by Cacti's plugin framework via the
 * 'config_arrays' hook on every page load.
 *
 * @return void
 *
 * @global array $fa_icons Populated here with the map of icon key =>
 *                         icon definition (or, on older Cacti, icon
 *                         key => display label).
 */
function monitor_config_arrays() {
	global $fa_icons;

	$fa_icons = [
		'server' => [
			'display' => __('Server', 'monitor'),
			'class'   => 'fa fa-server deviceUp',
			'style'   => ''
		],
		'print' => [
			'display' => __('Printer', 'monitor'),
			'class'   => 'fa fa-print deviceUp',
			'style'   => ''
		],
		'desktop' => [
			'display' => __('Desktop PC', 'monitor'),
			'class'   => 'fa fa-desktop deviceUp',
			'style'   => ''
		],
		'laptop' => [
			'display' => __('Laptop/notebook', 'monitor'),
			'class'   => 'fa fa-laptop deviceUp',
			'style'   => ''
		],
		'wifi' => [
			'display' => __('Wifi', 'monitor'),
			'class'   => 'fa fa-wifi deviceUp',
			'style'   => ''
		],
		'network-wired' => [
			'display' => __('Wired network', 'monitor'),
			'class'   => 'fa fa-network-wired deviceUp',
			'style'   => ''
		],
		'database' => [
			'display' => __('Database', 'monitor'),
			'class'   => 'fa fa-database deviceUp',
			'style'   => ''
		],
		'clock' => [
			'display' => __('Clock', 'monitor'),
			'class'   => 'fa fa-clock deviceUp',
			'style'   => ''
		],
		'asterisk' => [
			'display' => __('Asterisk', 'monitor'),
			'class'   => 'fas fa-asterisk deviceUp',
			'style'   => ''
		],
		'hdd' => [
			'display' => __('Harddisk', 'monitor'),
			'class'   => 'fa fa-hdd deviceUp',
			'style'   => ''
		],
		'boxes' => [
			'display' => __('Boxes', 'monitor'),
			'class'   => 'fa fa-boxes deviceUp',
			'style'   => ''
		],
		'phone' => [
			'display' => __('Phone', 'monitor'),
			'class'   => 'fa fa-phone deviceUp',
			'style'   => ''
		],
		'cloud' => [
			'display' => __('Cloud', 'monitor'),
			'class'   => 'fa fa-cloud deviceUp',
			'style'   => ''
		]
	];

	if (!function_exists('form_dropicon')) {
		foreach ($fa_icons as $key => $data) {
			$nfa_icons[$key] = $data['display'];
		}

		$fa_icons = $nfa_icons;
	}

	monitor_check_upgrade();
}

/**
 * Top_graph_refresh hook: overrides Cacti's page auto-refresh interval
 * with this plugin's configured monitor_refresh setting while viewing
 * monitor.php. Called by Cacti's page rendering via the
 * 'top_graph_refresh' hook.
 *
 * @param int $refresh The default refresh interval being overridden.
 *
 * @return int|string The plugin's configured monitor_refresh value
 *                    (as stored, typically a numeric string) when on
 *                    monitor.php and validly configured, otherwise the
 *                    unmodified int $refresh value.
 */
function monitor_top_graph_refresh($refresh) {
	if (get_current_page() != 'monitor.php') {
		return $refresh;
	}

	$r = read_config_option('monitor_refresh');

	if ($r == '' || $r < 1) {
		return $refresh;
	}

	return $r;
}

/**
 * Top_header_tabs/top_graph_header_tabs hook: triggers a schema upgrade
 * check, then prints the Monitor tab icon/link in Cacti's page header,
 * using the 'down' (active) icon when currently viewing monitor.php.
 * Called by Cacti's header rendering via the
 * 'top_header_tabs'/'top_graph_header_tabs' hooks.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       build the tab's URL and image paths.
 */
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

/**
 * Config_form hook: injects this plugin's monitoring settings fields
 * (enable checkbox, criticality, ping warn/alert thresholds and their
 * baseline-rebase drop-downs, down-device message, device icon) into
 * the host edit form, right after the bulk-walk-size field (or
 * 'disabled' as a fallback anchor point). Called by Cacti's host edit
 * page via the 'config_form' hook.
 *
 * @return void
 *
 * @global array $config           Reserved/declared for parity with
 *                                other functions in this file; not
 *                                used directly here.
 * @global array $fields_host_edit The host edit form's field
 *                                definitions array; replaced here with
 *                                a version containing this plugin's
 *                                injected fields.
 * @global array $criticalities    Map of criticality level => display
 *                                label, used for the criticality
 *                                drop-down.
 * @global array $fa_icons         Map of icon key => icon definition,
 *                                used for the device icon selector.
 */
function monitor_config_form() {
	global $config, $fields_host_edit, $criticalities, $fa_icons;

	$baselines = [
		'0'     => __('Do not Change', 'monitor'),
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
	];

	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = [];

	if (array_key_exists('bulk_walk_size', $fields_host_edit2)) {
		$insert_field = 'bulk_walk_size';
	} else {
		$insert_field = 'disabled';
	}

	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;

		if ($f == $insert_field) {
			$fields_host_edit3['monitor_header'] = [
				'friendly_name' => __('Device Monitoring Settings', 'monitor'),
				'method'        => 'spacer',
				'collapsible'   => 'true'
			];

			$fields_host_edit3['monitor'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('Monitor Device', 'monitor'),
				'description'   => __('Check this box to monitor this Device on the Monitor Tab.', 'monitor'),
				'value'         => '|arg1:monitor|',
				'form_id'       => false
			];

			$host_id = get_nfilter_request_var('id');

			if (empty($host_id) || !is_numeric($host_id)) {
				$fields_host_edit3['monitor']['default'] = monitor_get_default($host_id);
			}

			$fields_host_edit3['monitor_criticality'] = [
				'friendly_name' => __('Device Criticality', 'monitor'),
				'description'   => __('What is the Criticality of this Device.', 'monitor'),
				'method'        => 'drop_array',
				'array'         => $criticalities,
				'value'         => '|arg1:monitor_criticality|',
				'default'       => '0',
			];

			$fields_host_edit3['monitor_warn'] = [
				'friendly_name' => __('Ping Warning Threshold', 'monitor'),
				'description'   => __('If the round-trip latency via any of the predefined Cacti ping methods raises above this threshold, log a warning or send email based upon the Devices Criticality and Monitor setting.  The unit is in milliseconds.  Setting to 0 disables. The Thold Plugin is required to leverage this functionality.', 'monitor'),
				'method'        => 'textbox',
				'size'          => '10',
				'max_length'    => '5',
				'placeholder'   => __('milliseconds', 'monitor'),
				'value'         => '|arg1:monitor_warn|',
				'default'       => '',
			];

			$fields_host_edit3['monitor_alert'] = [
				'friendly_name' => __('Ping Alert Threshold', 'monitor'),
				'description'   => __('If the round-trip latency via any of the predefined Cacti ping methods raises above this threshold, log an alert or send an email based upon the Devices Criticality and Monitor setting.  The unit is in milliseconds.  Setting to 0 disables. The Thold Plugin is required to leverage this functionality.', 'monitor'),
				'method'        => 'textbox',
				'size'          => '10',
				'max_length'    => '5',
				'placeholder'   => __('milliseconds', 'monitor'),
				'value'         => '|arg1:monitor_alert|',
				'default'       => '',
			];

			$fields_host_edit3['monitor_warn_baseline'] = [
				'friendly_name' => __('Re-Baseline Warning', 'monitor'),
				'description'   => __('The percentage above the current average ping time to consider a Warning Threshold.  If updated, this will automatically adjust the Ping Warning Threshold.', 'monitor'),
				'method'        => 'drop_array',
				'default'       => '0',
				'value'         => '0',
				'array'         => $baselines
			];

			$fields_host_edit3['monitor_alert_baseline'] = [
				'friendly_name' => __('Re-Baseline Alert', 'monitor'),
				'description'   => __('The percentage above the current average ping time to consider a Alert Threshold.  If updated, this will automatically adjust the Ping Alert Threshold.', 'monitor'),
				'method'        => 'drop_array',
				'default'       => '0',
				'value'         => '0',
				'array'         => $baselines
			];

			$fields_host_edit3['monitor_text'] = [
				'friendly_name' => __('Down Device Message', 'monitor'),
				'description'   => __('This is the message that will be displayed when this Device is reported as down.', 'monitor'),
				'method'        => 'textarea',
				'max_length'    => 1000,
				'textarea_rows' => 2,
				'textarea_cols' => 80,
				'value'         => '|arg1:monitor_text|',
				'default'       => '',
			];

			if (function_exists('form_dropicon')) {
				$method = 'drop_icon';
			} else {
				$method = 'drop_array';
			}

			$fields_host_edit3['monitor_icon'] = [
				'friendly_name' => __('Device icon', 'monitor'),
				'description'   => __('You can select device icon.', 'monitor'),
				'method'        => $method,
				'default'       => '0',
				'value'         => '|arg1:monitor_icon|',
				'array'         => $fa_icons,
			];
		}
	}

	$fields_host_edit = $fields_host_edit3;
}

/**
 * Determines the default 'Monitor Device' checkbox state for a new
 * (not-yet-saved) host, based on the globally configured
 * 'monitor_new_enabled' setting. Called from monitor_config_form() when
 * building the host edit form for a new device.
 *
 * @param int|string $host_id The host id being edited; only new (empty
 *                           or non-numeric) hosts get the configured
 *                           default.
 *
 * @return string The default value ('' or 'on') for the monitor
 *               checkbox.
 */
function monitor_get_default($host_id) {
	$monitor_new_device = '';

	if ($host_id <= 0) {
		$monitor_new_device = read_config_option('monitor_new_enabled');
	}

	return $monitor_new_device;
}

/**
 * Api_device_save hook: validates and persists this plugin's submitted
 * monitoring fields (enable flag, down-device message, criticality,
 * ping warn/alert thresholds, icon) onto the host save data,
 * recomputing the warn/alert thresholds from the device's current ping
 * time when a baseline-rebase percentage was submitted. Called by
 * Cacti's host save flow via the 'api_device_save' hook.
 *
 * @param array $save The host's save data array being built up.
 *
 * @return array The $save array with this plugin's monitoring fields
 *              added/validated.
 *
 * @global array $fa_icons Map of valid icon key => icon definition,
 *                        used to validate the submitted icon
 *                        selection.
 */
function monitor_api_device_save($save) {
	global $fa_icons;

	$monitor_default = monitor_get_default($save['id']);

	if (isset_request_var('monitor')) {
		$save['monitor'] = form_input_validate(get_nfilter_request_var('monitor'), 'monitor', $monitor_default, true, 3);
	} else {
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

	if (isset_request_var('monitor_icon') && array_key_exists(get_nfilter_request_var('monitor_icon'), $fa_icons)) {
		$save['monitor_icon'] = get_nfilter_request_var('monitor_icon');
	} else {
		$save['monitor_icon'] = '';
	}

	if (!isempty_request_var('monitor_alert_baseline') && !empty($save['id'])) {
		$cur_time = db_fetch_cell_prepared('SELECT cur_time
			FROM host
			WHERE id = ?',
			[$save['id']]);

		if ($cur_time > 0) {
			$save['monitor_alert'] = ceil($cur_time * get_nfilter_request_var('monitor_alert_baseline'));
		}
	}

	if (!isempty_request_var('monitor_warn_baseline') && !empty($save['id'])) {
		$cur_time = db_fetch_cell_prepared('SELECT cur_time
			FROM host
			WHERE id = ?',
			[$save['id']]);

		if ($cur_time > 0) {
			$save['monitor_warn'] = ceil($cur_time * get_nfilter_request_var('monitor_alert_baseline'));
		}
	}

	return $save;
}

/**
 * Draw_navigation_text hook: registers the breadcrumb/navigation title
 * entry for this plugin's monitor.php page. Called by Cacti's
 * navigation framework via the 'draw_navigation_text' hook.
 *
 * @param array $nav The navigation entries array being built up.
 *
 * @return array The $nav array with this plugin's entry added.
 */
function monitor_draw_navigation_text($nav) {
	$nav['monitor.php:'] = ['title' => __('Monitoring', 'monitor'), 'mapping' => '', 'url' => 'monitor.php', 'level' => '0'];

	return $nav;
}

/**
 * Creates (if not already present) all of this plugin's database
 * tables (notify/reboot history, uptime, dashboards), ensures the host
 * table uses DYNAMIC row format (needed for its added TEXT column),
 * and adds this plugin's monitoring columns to the host table. Called
 * from plugin_monitor_install() and monitor_check_upgrade().
 *
 * @return void
 */
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
			uptime bigint(20) unsigned DEFAULT '0',
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

	if (db_table_exists('host')) {
		$row_format = db_fetch_cell("SELECT ROW_FORMAT
			FROM information_schema.tables
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'host'");

		if (strtoupper((string) $row_format) !== 'DYNAMIC') {
			db_execute('ALTER TABLE host ROW_FORMAT=DYNAMIC');
		}
	}

	db_execute('SET SESSION innodb_strict_mode=0');

	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor', 'type' => 'char(3)', 'NULL' => true, 'default' => 'on']);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_text', 'type' => 'text', 'NULL' => false]);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_criticality', 'type' => 'tinyint', 'unsigned' => true, 'NULL' => false, 'default' => '0']);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_warn', 'type' => 'double', 'NULL' => false, 'default' => '0']);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_alert', 'type' => 'double', 'NULL' => false, 'default' => '0']);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_icon', 'type' => 'varchar(30)', 'NULL' => false, 'default' => '']);

	db_execute('SET SESSION innodb_strict_mode=1');
}

/**
 * Poller_bottom hook: on the main poller (poller_id 1), launches this
 * plugin's poller_monitor.php script as a background process at the
 * end of each Cacti polling cycle. Called by Cacti's poller via the
 * 'poller_bottom' hook.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       check the poller id and locate the PHP binary
 *                       and this plugin's poller script.
 */
function monitor_poller_bottom() {
	global $config;

	if ($config['poller_id'] == 1) {
		include_once($config['library_path'] . '/poller.php');

		$command_string = trim(read_config_option('path_php_binary'));

		if (trim($command_string) == '') {
			$command_string = 'php';
		}

		$extra_args = ' -q ' . $config['base_path'] . '/plugins/monitor/poller_monitor.php';

		exec_background($command_string, $extra_args);
	}
}
