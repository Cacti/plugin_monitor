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
 * Decode a dashboard's stored properties JSON into an array.
 *
 * @param int $dashboard Dashboard id.
 *
 * @return array Decoded properties, or an empty array when none/invalid.
 */
function monitorGetDashboardProperties(int $dashboard): array {
	if ($dashboard <= 0) {
		return [];
	}

	$json = db_fetch_cell_prepared(
		'SELECT properties
		FROM plugin_monitor_dashboards
		WHERE id = ?',
		[$dashboard]
	);

	if ($json == '') {
		return [];
	}

	$props = json_decode($json, true);

	return is_array($props) ? $props : [];
}

/**
 * Load saved dashboard filter variables into request scope.
 *
 * @return void
 */
function loadDashboardSettings(): void {
	$dashboard = get_filter_request_var('dashboard');

	if ($dashboard > 0) {
		$props = monitorGetDashboardProperties($dashboard);

		if (isset($props['vars']) && is_array($props['vars'])) {
			foreach ($props['vars'] as $name => $value) {
				set_request_var($name, $value);
			}
		}
	}
}

/**
 * Persist a grouping's card ordering after a drag/drop reorder.
 *
 * Stores the ordered group id list in the active dashboard's properties when a
 * dashboard is selected, otherwise in the per-user monitor_cardorder setting.
 *
 * @return void
 */
function saveCardOrder(): void {
	$grouping = get_nfilter_request_var('grouping');

	if (!in_array($grouping, ['site', 'template'], true)) {
		return;
	}

	$ids = [];

	if (isset($_POST['order']) && is_array($_POST['order'])) {
		foreach ($_POST['order'] as $gid) {
			$ids[] = (string) (int) $gid;
		}
	}

	$dashboard = isset_request_var('dashboard') ? get_filter_request_var('dashboard') : 0;

	if ($dashboard > 0) {
		$props = monitorGetDashboardProperties($dashboard);

		if (!isset($props['cardorder']) || !is_array($props['cardorder'])) {
			$props['cardorder'] = [];
		}

		$props['cardorder'][$grouping] = $ids;

		db_execute_prepared('UPDATE plugin_monitor_dashboards
			SET properties = ?
			WHERE id = ?
			AND user_id = ?',
			[json_encode($props), $dashboard, $_SESSION['sess_user_id']]);
	} else {
		$json  = read_user_setting('monitor_cardorder');
		$order = $json != '' ? json_decode($json, true) : [];

		if (!is_array($order)) {
			$order = [];
		}

		$order[$grouping] = $ids;

		set_user_setting('monitor_cardorder', json_encode($order));
	}
}

/**
 * Render monitor page including filters, host layout, legend, and audio.
 *
 * @return void
 */
function drawPage(): void {
	global $config, $iclasses, $icolorsdisplay, $mon_zoom_state, $dozoomrefresh, $dozoombgndcolor, $font_sizes;
	global $new_form, $new_title;

	$errored_list = getHostsDownOrTriggeredByPermission(true);

	if (cacti_sizeof($errored_list) && read_user_setting('monitor_error_zoom') == 'on') {
		if ($_SESSION['monitor_zoom_state'] == 0) {
			$mon_zoom_state                   = $_SESSION['monitor_zoom_state'] = 1;
			$_SESSION['mon_zoom_hist_status'] = get_nfilter_request_var('status');
			$_SESSION['mon_zoom_hist_size']   = get_nfilter_request_var('size');
			$dozoomrefresh                    = true;
			$dozoombgndcolor                  = true;
		}
	} elseif (isset($_SESSION['monitor_zoom_state']) && $_SESSION['monitor_zoom_state'] == 1) {
		$_SESSION['monitor_zoom_state'] = 0;
		$dozoomrefresh                  = true;
		$dozoombgndcolor                = false;
	}

	$name = db_fetch_cell_prepared(
		'SELECT name
		FROM plugin_monitor_dashboards
		WHERE id = ?',
		[get_request_var('dashboard')]
	);

	if ($name == '') {
		$name = __('New Dashboard', 'monitor');
	}

	$new_form  = "<div id='newdialog'> <form id='new_dashboard'> <table class='monitorTableHeader'> <tr> <td colspan='2'>" . __('Enter the Dashboard Name and then press \'Save\' to continue, else press \'Cancel\'', 'monitor') . '</td> </tr> <tr> <td>' . __('Dashboard', 'monitor') . "</td> <td><input id='name' class='ui-state-default ui-corner-all' type='text' size='30' value='" . html_escape($name) . "'></td> </tr> </table> </form> </div>";

	$new_title = __('Create New Dashboard', 'monitor');

	findDownHosts();

	general_header();

	drawFilterAndStatus();

	print '<tr><td>';

	// Default with permissions = default_by_permission
	// Tree  = group_by_tree
	$function = 'render' . ucfirst(get_request_var('grouping'));

	if (function_exists($function) && get_request_var('view') != 'list') {
		if (get_request_var('grouping') == 'default' || get_request_var('grouping') == 'site') {
			html_start_box(__('Monitored Devices', 'monitor'), '100%', true, 3, 'center', '');
		} else {
			html_start_box('', '100%', true, 3, 'center', '');
		}
		print $function();
	} else {
		print renderDefault();
	}

	print '</td></tr>';

	html_end_box();

	if (read_user_setting('monitor_legend', read_config_option('monitor_legend'))) {
		print '<div class="center monitorLegendFooter">';
		monitor_legend($iclasses, $icolorsdisplay);
		print '</div>';
	}

	// If the host is down, we need to insert the embedded wav file
	$monitor_sound = getMonitorSound();

	if (isMonitorAudible()) {
		if (read_user_setting('monitor_sound_loop', read_config_option('monitor_sound_loop'))) {
			print "<audio id='audio' playsinline='' loop src='" . html_escape($config['url_path'] . 'plugins/monitor/sounds/' . $monitor_sound) . "'></audio>";
		} else {
			print "<audio id='audio' playsinline='' src='" . html_escape($config['url_path'] . 'plugins/monitor/sounds/' . $monitor_sound) . "'></audio>";
		}
	}

	print '<div class="center monitorFooter">' . getFilterText() . '</div>';

	bottom_footer();
}

/**
 * Determine if alert audio is available and should be considered playable.
 *
 * @return bool
 */
function isMonitorAudible(): bool {
	return getMonitorSound() != '';
}

/**
 * Resolve configured monitor sound file if it exists on disk.
 *
 * @return string
 */
function getMonitorSound(): string {
	$sound = (string) read_user_setting('monitor_sound', read_config_option('monitor_sound'));
	clearstatcache();
	$file   = __DIR__ . '/sounds/' . $sound;
	$exists = file_exists($file);

	return $exists ? $sound : '';
}

/**
 * Update down-host request flags and mute state based on current host status.
 *
 * @return void
 */
function findDownHosts(): void {
	$dhosts = getHostsDownOrTriggeredByPermission(false);

	if (cacti_sizeof($dhosts)) {
		set_request_var('downhosts', 'true');

		if (isset($_SESSION['monitor_muted_hosts'])) {
			unmuteUpNonTriggeredHosts($dhosts);

			$unmuted_hosts = array_diff($dhosts, $_SESSION['monitor_muted_hosts']);

			if (cacti_sizeof($unmuted_hosts)) {
				unmuteUser();
			}
		} else {
			set_request_var('mute', 'false');
		}
	} else {
		unmuteAllHosts();
		set_request_var('downhosts', 'false');
	}
}

/**
 * Remove recovered hosts from muted-host session state.
 *
 * @param array $dhosts Current down/triggered host id list.
 *
 * @return void
 */
function unmuteUpNonTriggeredHosts(array $dhosts): void {
	if (isset($_SESSION['monitor_muted_hosts'])) {
		foreach ($_SESSION['monitor_muted_hosts'] as $index => $host_id) {
			if (array_search($host_id, $dhosts, true) === false) {
				unset($_SESSION['monitor_muted_hosts'][$index]);
			}
		}
	}
}

/**
 * Mute all currently down/triggered hosts for the active user session.
 *
 * @return void
 */
function muteAllHosts(): void {
	$_SESSION['monitor_muted_hosts'] = getHostsDownOrTriggeredByPermission(false);
	muteUser();
}

/**
 * Clear muted-host list and unmute monitor notifications for this user.
 *
 * @return void
 */
function unmuteAllHosts(): void {
	$_SESSION['monitor_muted_hosts'] = [];
	unmuteUser();
}

/**
 * Persist user mute state as enabled.
 *
 * @return void
 */
function muteUser(): void {
	set_request_var('mute', 'true');
	set_user_setting('monitor_mute', 'true');
}

/**
 * Persist user mute state as disabled.
 *
 * @return void
 */
function unmuteUser(): void {
	set_request_var('mute', 'false');
	set_user_setting('monitor_mute', 'false');
}

/**
 * Build footer text describing currently active monitor filters.
 *
 * @return string
 */
function getFilterText(): string {
	$filter = '<div class="center monitorFooterText">';

	switch (get_request_var('status')) {
		case '-4':
			$filter .= __('Devices without Thresholds', 'monitor');

			break;
		case '-3':
			$filter .= __('Not Monitored Devices', 'monitor');

			break;
		case '-2':
			$filter .= __('All Devices', 'monitor');

			break;
		case '-1':
			$filter .= __('All Monitored Devices', 'monitor');

			break;
		case '0':
			$filter .= __('Monitored Devices either Down or Recovering', 'monitor');

			break;
		case '1':
			$filter .= __('Monitored Devices either Down, Recovering, or with Triggered Thresholds', 'monitor');

			break;
		case '2':
			$filter .= __('Monitored Devices either Down, Recovering, or with Breached or Triggered Thresholds', 'monitor');

			break;
		default:
			$filter .= __('Unknown monitoring status (%s)', get_request_var('status'), 'monitor');
	}

	switch (get_request_var('crit')) {
		case '0':
			$filter .= __(', and All Criticalities', 'monitor');

			break;
		case '1':
			$filter .= __(', and of Low Criticality or Higher', 'monitor');

			break;
		case '2':
			$filter .= __(', and of Medium Criticality or Higher', 'monitor');

			break;
		case '3':
			$filter .= __(', and of High Criticality or Higher', 'monitor');

			break;
		case '4':
			$filter .= __(', and of Mission Critical Status', 'monitor');

			break;
	}

	$filter .= __('</div><div class="center monitorFooterTextBold">Remember to first select eligible Devices to be Monitored from the Devices page!</div></div></div>', 'monitor');

	return $filter;
}

/**
 * Render one filter dropdown (or hidden fallback input) for the monitor form.
 *
 * @param string     $id       Filter field id/name.
 * @param string     $title    Filter display title.
 * @param array      $settings Option map of value => label.
 * @param string|int $value    Selected value override.
 *
 * @return void
 */
function drawFilterDropdown(string $id, string $title, array $settings = [], mixed $value = null): void {
	if ($value == null) {
		$value = get_nfilter_request_var($id);
	}

	if (cacti_sizeof($settings)) {
		print '<td>' . html_escape($title) . '</td>';

		// Cacti 1.2.32 added the select2 class helpers; only tag the select on versions that theme it.
		$select_class = version_compare(CACTI_VERSION, '1.2.32', '>=') ? ' class="select2-nosearch"' : '';

		print '<td><select id="' . $id . '"' . $select_class . ' title="' . html_escape($title) . '">' . PHP_EOL;

		foreach ($settings as $setting_value => $setting_name) {
			if ($value == null || $value == '') {
				$value = $setting_value;
			}

			$setting_selected = ($value == $setting_value) ? ' selected' : '';

			print '<option value="' . $setting_value . '"' . $setting_selected . '>' . html_escape($setting_name) . '</option>' . PHP_EOL;
		}

		print '</select></td>' . PHP_EOL;
	} else {
		print "<td style='display:none;'><input type='hidden' id='$id' value='" . html_escape($value) . "'></td>" . PHP_EOL;
	}
}

/**
 * Build dashboard dropdown option map for current user context.
 *
 * @return array
 */
function monitorGetDashboardOptions(): array {
	$dashboards = [0 => __('Unsaved', 'monitor')];
	$dashboards += array_rekey(
		db_fetch_assoc_prepared(
			'SELECT id, name
            FROM plugin_monitor_dashboards
            WHERE user_id = 0 OR user_id = ?
            ORDER BY name',
			[$_SESSION['sess_user_id']]
		),
		'id',
		'name'
	);

	return $dashboards;
}

/**
 * Resolve zoom override dropdown state from session values.
 *
 * @param bool $dozoombgndcolor Zoom background flag, updated in place.
 *
 * @return array{int|null, string|null}
 */
function monitorGetZoomDropdownState(bool &$dozoombgndcolor): array {
	$mon_zoom_status = null;
	$mon_zoom_size   = null;

	if (isset($_SESSION['monitor_zoom_state'])) {
		if ($_SESSION['monitor_zoom_state'] == 1) {
			$mon_zoom_status = 2;
			$mon_zoom_size   = 'monitor_errorzoom';
			$dozoombgndcolor = true;
		} else {
			if (isset($_SESSION['mon_zoom_hist_status'])) {
				$mon_zoom_status = $_SESSION['mon_zoom_hist_status'];
			}

			if (isset($_SESSION['mon_zoom_hist_size'])) {
				$currentddsize = get_nfilter_request_var('size');

				if ($currentddsize != $_SESSION['mon_zoom_hist_size'] && $currentddsize != 'monitor_errorzoom') {
					$_SESSION['mon_zoom_hist_size'] = $currentddsize;
				}

				$mon_zoom_size = $_SESSION['mon_zoom_hist_size'];
			}
		}
	}

	return [$mon_zoom_status, $mon_zoom_size];
}

/**
 * Render the primary filter row (layout/status/view/grouping/actions).
 *
 * @param array    $dashboards        Dashboard option map.
 * @param array    $monitor_status    Status filter options.
 * @param array    $monitor_view_type View mode options.
 * @param array    $monitor_grouping  Grouping mode options.
 * @param array    $item_rows         Device row count options.
 * @param int|null $mon_zoom_status   Zoom-driven status override.
 *
 * @return void
 */
function monitorRenderPrimaryFilterRow(array $dashboards, array $monitor_status, array $monitor_view_type, array $monitor_grouping, array $item_rows, mixed $mon_zoom_status): void {
	drawFilterDropdown('dashboard', __('Layout', 'monitor'), $dashboards);
	drawFilterDropdown('status', __('Status', 'monitor'), $monitor_status, $mon_zoom_status);
	drawFilterDropdown('view', __('View', 'monitor'), $monitor_view_type);
	drawFilterDropdown('grouping', __('Grouping', 'monitor'), $monitor_grouping);

	// The Rows filter only applies to the List view; other views show all devices.
	if (get_request_var('view') == 'list') {
		drawFilterDropdown('rows', __('Devices', 'monitor'), $item_rows);
	}

	print '<td><span>' . PHP_EOL;
	print '<input type="submit" value="' . __esc('Refresh', 'monitor') . '" id="go" title="' . __esc('Refresh the Device List', 'monitor') . '">' . PHP_EOL;
	print '<input type="button" value="' . __esc('Clear', 'monitor') . '" id="clear" title="' . __esc('Clear the set filter', 'monitor') . '">' . PHP_EOL;
	print '<input type="button" value="' . __esc('Save', 'monitor') . '" id="save" title="' . __esc('Save Filter Settings', 'monitor') . '">' . PHP_EOL;
	print '<input type="button" value="' . __esc('New', 'monitor') . '" id="new" title="' . __esc('Save New Dashboard', 'monitor') . '">' . PHP_EOL;

	if (get_request_var('dashboard') > 0) {
		print '<input type="button" value="' . __esc('Rename', 'monitor') . '" id="rename" title="' . __esc('Rename Dashboard', 'monitor') . '">' . PHP_EOL;
		print '<input type="button" value="' . __esc('Delete', 'monitor') . '" id="delete" title="' . __esc('Delete Dashboard', 'monitor') . '">' . PHP_EOL;
	}

	print '<input type="button" value="' . (get_request_var('mute') == 'false' ? getMuteText() : getUnmuteText()) . '" id="sound" title="' . (get_request_var('mute') == 'false' ? __('%s Alert for downed Devices', getMuteText(), 'monitor') : __('%s Alerts for downed Devices', getUnmuteText(), 'monitor')) . '">' . PHP_EOL;
	print '<input id="downhosts" type="hidden" value="' . html_escape(get_request_var('downhosts')) . '"><input id="mute" type="hidden" value="' . html_escape(get_request_var('mute')) . '">' . PHP_EOL;
	print '</span></td>';
}

/**
 * Render secondary grouping/filter controls for monitor page.
 *
 * @param array       $classes               Size class options.
 * @param array       $criticalities         Criticality options.
 * @param array       $monitor_trim          Trim options.
 * @param array       $page_refresh_interval Refresh options.
 * @param string|null $mon_zoom_size         Zoom-driven size override.
 *
 * @return void
 */
function monitorRenderGroupingDropdowns(array $classes, array $criticalities, array $monitor_trim, array $page_refresh_interval, mixed $mon_zoom_size): void {
	drawFilterDropdown('crit', __('Criticality', 'monitor'), $criticalities);

	if (get_request_var('view') != 'list') {
		drawFilterDropdown('size', __('Size', 'monitor'), $classes, $mon_zoom_size);
	}

	if (get_request_var('view') == 'default' || get_request_var('view') == 'names') {
		drawFilterDropdown('trim', __('Trim', 'monitor'), $monitor_trim);
	}

	if (get_nfilter_request_var('grouping') == 'tree') {
		$trees = [];

		if (get_request_var('grouping') == 'tree') {
			$trees_allowed = array_rekey(get_allowed_trees(), 'id', 'name');

			if (cacti_sizeof($trees_allowed)) {
				$trees_prefix = [-1 => __('All Trees', 'monitor')];
				$trees_suffix = [-2 => __('Non-Tree Devices', 'monitor')];
				$trees        = $trees_prefix + $trees_allowed + $trees_suffix;
			}
		}

		drawFilterDropdown('tree', __('Tree', 'monitor'), $trees);
	}

	if (get_nfilter_request_var('grouping') == 'site') {
		$sites = [];

		if (get_request_var('grouping') == 'site') {
			$sites = array_rekey(
				db_fetch_assoc('SELECT id, name
                FROM sites
                ORDER BY name'),
				'id',
				'name'
			);

			if (cacti_sizeof($sites)) {
				$sites_prefix = [-1 => __('All Sites', 'monitor')];
				$sites_suffix = [-2 => __('Non-Site Devices', 'monitor')];
				$sites        = $sites_prefix + $sites + $sites_suffix;
			}
		}

		drawFilterDropdown('site', __('Sites', 'monitor'), $sites);
	}

	if (get_request_var('grouping') == 'template') {
		$templates         = [];
		$templates_allowed = array_rekey(
			db_fetch_assoc('SELECT ht.id, ht.name, COUNT(gl.id) AS graphs
                FROM host_template AS ht
                INNER JOIN host AS h
                ON h.host_template_id = ht.id
                INNER JOIN graph_local AS gl
                ON h.id = gl.host_id
                GROUP BY ht.id
                HAVING graphs > 0'),
			'id',
			'name'
		);

		if (cacti_sizeof($templates_allowed)) {
			$templates_prefix = [-1 => __('All Templates', 'monitor')];
			$templates_suffix = [-2 => __('Non-Templated Devices', 'monitor')];
			$templates        = $templates_prefix + $templates_allowed + $templates_suffix;
		}

		drawFilterDropdown('template', __('Template', 'monitor'), $templates);
	}

	drawFilterDropdown('refresh', __('Refresh', 'monitor'), $page_refresh_interval);
}

/**
 * Render hidden fallback input fields for inactive filters.
 *
 * @return void
 */
function monitorRenderHiddenFilterInputs(): void {
	if (get_request_var('grouping') != 'tree') {
		print '<td><input type="hidden" id="tree" value="' . html_escape(get_request_var('tree')) . '"></td>' . PHP_EOL;
	}

	if (get_request_var('grouping') != 'site') {
		print '<td><input type="hidden" id="site" value="' . html_escape(get_request_var('site')) . '"></td>' . PHP_EOL;
	}

	if (get_request_var('grouping') != 'template') {
		print '<td><input type="hidden" id="template" value="' . html_escape(get_request_var('template')) . '"></td>' . PHP_EOL;
	}

	if (get_request_var('view') == 'list') {
		print '<td><input type="hidden" id="size" value="' . html_escape(get_request_var('size')) . '"></td>' . PHP_EOL;
	} else {
		print '<td><input type="hidden" id="rows" value="' . html_escape(get_request_var('rows')) . '"></td>' . PHP_EOL;
	}

	if (get_request_var('view') != 'default') {
		print '<td><input type="hidden" id="trim" value="' . html_escape(get_request_var('trim')) . '"></td>' . PHP_EOL;
	}
}

/**
 * Resolve zoom background color/font style for page rendering.
 *
 * @param bool $dozoombgndcolor Whether zoom background styling is enabled.
 *
 * @return array{string, string}
 */
function monitorGetZoomBackgroundStyle(bool $dozoombgndcolor): array {
	if ($dozoombgndcolor) {
		$mbcolora = db_fetch_row_prepared(
			'SELECT *
            FROM colors
            WHERE id = ?',
			[read_user_setting('monitor_error_background')]
		);

		$monitor_error_fontsize = read_user_setting('monitor_error_fontsize') . 'px';
		$mbcolor                = cacti_sizeof($mbcolora) ? '#' . $mbcolora['hex'] : 'snow';
	} else {
		$mbcolor                = '';
		$monitor_error_fontsize = '10px';
	}

	return [$mbcolor, $monitor_error_fontsize];
}

/**
 * Emit JavaScript bootstrap config and monitor JS include tag.
 *
 * @param array  $config                 Global Cacti config.
 * @param string $mbcolor                Monitor background color.
 * @param string $monitor_error_fontsize Zoom mode font size.
 * @param bool   $dozoomrefresh          Auto-refresh flag for zoom mode.
 * @param string $new_form               New dashboard dialog markup.
 * @param string $new_title              New dashboard dialog title.
 *
 * @return void
 */
function monitorPrintJsBootstrap(array $config, string $mbcolor, string $monitor_error_fontsize, bool $dozoomrefresh, string $new_form, string $new_title): void {
	$monitor_js_config = [
		'mbColor'       => $mbcolor,
		'monitorFont'   => $monitor_error_fontsize,
		'doZoomRefresh' => $dozoomrefresh,
		'newForm'       => $new_form,
		'newTitle'      => $new_title,
		'messages'      => [
			'filterSaved' => __(' [ Filter Settings Saved ]', 'monitor'),
			'cancel'      => __('Cancel', 'monitor'),
			'save'        => __('Save', 'monitor'),
			'loading'     => __('Loading...', 'monitor'),
			'deviceDetails' => __('Device Detail Panels', 'monitor')
		]
	];

	print '<script type="text/javascript" ' . plugin_monitor_csp_nonce() . '>window.monitorPageConfig = ' .
		json_encode($monitor_js_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) .
		';</script>';
	print get_md5_include_js('plugins/monitor/js/monitor.js');
}

/**
 * Render monitor filter area and inject page JS bootstrap config.
 *
 * @return void
 */
function drawFilterAndStatus(): void {
	global $config, $criticalities, $page_refresh_interval, $classes, $monitor_grouping;
	global $monitor_view_type, $monitor_status, $monitor_trim;
	global $dozoombgndcolor, $dozoomrefresh, $zoom_hist_status, $zoom_hist_size, $mon_zoom_state;
	global $new_form, $new_title, $item_rows;

	$header = __('Monitor Filter [ Last Refresh: %s ]', date('g:i:s a', time()), 'monitor') . (get_request_var('refresh') < 99999 ? __(' [ Refresh Again in <i style="padding:0px !important;margin:0px;" id="timer">%d</i> Seconds ]', get_request_var('refresh'), 'monitor') : '') . (get_request_var('view') == 'list' ? __('[ Showing only first 30 Devices ]', 'monitor') : '') . '<span id="text" style="vertical-align:baseline;padding:0px !important;display:none"></span>';

	html_start_box($header, '100%', false, 3, 'center', '');

	print '<tr class="even"><td>' . PHP_EOL;
	print '<form class="monitorFilterForm">' . PHP_EOL;

	print '<table class="filterTable">' . PHP_EOL;
	print '<tr class="even">' . PHP_EOL;
	[$mon_zoom_status, $mon_zoom_size] = monitorGetZoomDropdownState($dozoombgndcolor);
	monitorRenderPrimaryFilterRow(
		monitorGetDashboardOptions(),
		$monitor_status,
		$monitor_view_type,
		$monitor_grouping,
		$item_rows,
		$mon_zoom_status
	);
	print '</tr>';
	print '</table>';

	// Second line of filter
	print '<table class="filterTable">' . PHP_EOL;
	print '<tr>' . PHP_EOL;
	print '<td>' . __('Search', 'monitor') . '</td>';
	print '<td><input type="text" size="30" id="rfilter" value="' . html_escape_request_var('rfilter') . '"></input></td>';
	monitorRenderGroupingDropdowns($classes, $criticalities, $monitor_trim, $page_refresh_interval, $mon_zoom_size);
	monitorRenderHiddenFilterInputs();

	print '</tr>';
	print '</table>';
	print '</form></td></tr>' . PHP_EOL;

	html_end_box();

	[$mbcolor, $monitor_error_fontsize] = monitorGetZoomBackgroundStyle($dozoombgndcolor);
	monitorPrintJsBootstrap($config, $mbcolor, $monitor_error_fontsize, $dozoomrefresh, $new_form, $new_title);
}

/**
 * Get action label for mute button based on current audio capability.
 *
 * @return string
 */
function getMuteText(): string {
	if (isMonitorAudible()) {
		return __('Mute', 'monitor');
	} else {
		return __('Acknowledge', 'monitor');
	}
}

/**
 * Get action label for unmute/reset button based on audio capability.
 *
 * @return string
 */
function getUnmuteText(): string {
	if (isMonitorAudible()) {
		return __('Un-Mute', 'monitor');
	} else {
		return __('Reset', 'monitor');
	}
}

/**
 * Delete selected dashboard when owned by current user.
 *
 * @return void
 */
function removeDashboard(): void {
	$dashboard = get_filter_request_var('dashboard');

	$name = db_fetch_cell_prepared(
		'SELECT name
		FROM plugin_monitor_dashboards
		WHERE id = ?
		AND user_id = ?',
		[$dashboard, $_SESSION['sess_user_id']]
	);

	if ($name != '') {
		db_execute_prepared(
			'DELETE FROM plugin_monitor_dashboards
			WHERE id = ?',
			[$dashboard]
		);

		raise_message('removed', __('Dashboard \'%s\' Removed.', $name, 'monitor'), MESSAGE_LEVEL_INFO);
	} else {
		$name = db_fetch_cell_prepared(
			'SELECT name
			FROM plugin_monitor_dashboards
			WHERE id = ?',
			[$dashboard]
		);

		raise_message('notremoved', __('Dashboard \'%s\' is not owned by you.', $name, 'monitor'), MESSAGE_LEVEL_ERROR);
	}

	set_request_var('dashboard', '0');
}

/**
 * Save monitor filter settings to user prefs or selected dashboard record.
 *
 * @return void
 */
function saveSettings(): void {
	if (isset_request_var('dashboard') && get_filter_request_var('dashboard') != 0) {
		$save_db = true;
	} else {
		$save_db = false;
	}

	validateRequestVars();

	if (!$save_db) {
		if (cacti_sizeof($_REQUEST)) {
			foreach ($_REQUEST as $var => $value) {
				switch ($var) {
					case 'dashboard':
						set_user_setting('monitor_rfilter', get_request_var('dashboard'));

						break;
					case 'rfilter':
						set_user_setting('monitor_rfilter', get_request_var('rfilter'));

						break;
					case 'refresh':
						set_user_setting('monitor_refresh', get_request_var('refresh'));

						break;
					case 'grouping':
						set_user_setting('monitor_grouping', get_request_var('grouping'));

						break;
					case 'view':
						set_user_setting('monitor_view', get_request_var('view'));

						break;
					case 'rows':
						set_user_setting('monitor_rows', get_request_var('rows'));

						break;
					case 'crit':
						set_user_setting('monitor_crit', get_request_var('crit'));

						break;
					case 'mute':
						set_user_setting('monitor_mute', get_request_var('mute'));

						break;
					case 'size':
						set_user_setting('monitor_size', get_request_var('size'));

						break;
					case 'trim':
						set_user_setting('monitor_trim', get_request_var('trim'));

						break;
					case 'status':
						set_user_setting('monitor_status', get_request_var('status'));

						break;
					case 'tree':
						set_user_setting('monitor_tree', get_request_var('tree'));

						break;
					case 'mute':
						set_user_setting('monitor_mute', get_request_var('mute'));

						break;
					case 'site':
						set_user_setting('monitor_site', get_request_var('site'));

						break;
				}
			}
		}
	} else {
		$vars = [
			'refresh'  => get_request_var('refresh'),
			'grouping' => get_request_var('grouping'),
			'view'     => get_request_var('view'),
			'rows'     => get_request_var('rows'),
			'crit'     => get_request_var('crit'),
			'size'     => get_request_var('size'),
			'trim'     => get_request_var('trim'),
			'status'   => get_request_var('status'),
			'tree'     => get_request_var('tree'),
			'site'     => get_request_var('site'),
		];

		if (!isset_request_var('user')) {
			$user = $_SESSION['sess_user_id'];
		} else {
			$user = get_request_var('user');
		}

		$id   = get_request_var('dashboard');
		$name = get_nfilter_request_var('name');

		// Preserve any saved card ordering already stored for this dashboard.
		$existing = monitorGetDashboardProperties($id);
		$props    = ['vars' => $vars];

		if (isset($existing['cardorder'])) {
			$props['cardorder'] = $existing['cardorder'];
		}

		$save               = [];
		$save['id']         = $id;
		$save['name']       = $name;
		$save['user_id']    = $user;
		$save['properties'] = json_encode($props);

		$id = sql_save($save, 'plugin_monitor_dashboards');

		if (!empty($id)) {
			raise_message('monitorsaved', __('Dashboard \'%s\' has been Saved!', $name, 'monitor'), MESSAGE_LEVEL_INFO);
			set_request_var('dashboard', $id);
		} else {
			raise_message('monitornotsaved', __('Dashboard \'%s\' could not be Saved!', $name, 'monitor'), MESSAGE_LEVEL_INFO);
			set_request_var('dashboard', '0');
		}
	}

	validateRequestVars(true);
}

/**
 * Validate and persist monitor request/session filter variables.
 *
 * @param bool $force Force reload from saved defaults.
 *
 * @return void
 */
function validateRequestVars(bool $force = false): void {
	// ================= input validation and session storage =================
	$filters = [
		'refresh' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_refresh', read_config_option('monitor_refresh'), $force)
		],
		'dashboard' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('monitor_dashboard', '0', $force)
		],
		'rfilter' => [
			'filter'  => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => read_user_setting('monitor_rfilter', '', $force)
		],
		'name' => [
			'filter'  => FILTER_CALLBACK,
			'options' => ['options' => 'sanitize_search_string'],
			'default' => ''
		],
		'mute' => [
			'filter'  => FILTER_CALLBACK,
			'options' => ['options' => 'sanitize_search_string'],
			'default' => read_user_setting('monitor_mute', 'false', $force)
		],
		'grouping' => [
			'filter'  => FILTER_CALLBACK,
			'options' => ['options' => 'sanitize_search_string'],
			'pageset' => true,
			'default' => read_user_setting('monitor_grouping', read_config_option('monitor_grouping'), $force)
		],
		'view' => [
			'filter'  => FILTER_CALLBACK,
			'options' => ['options' => 'sanitize_search_string'],
			'pageset' => true,
			'default' => read_user_setting('monitor_view', read_config_option('monitor_view'), $force)
		],
		'rows' => [
			'filter'  => FILTER_VALIDATE_INT,
			'options' => ['options' => 'sanitize_search_string'],
			'default' => read_user_setting('monitor_rows', read_config_option('num_rows_table'), $force)
		],
		'size' => [
			'filter'  => FILTER_CALLBACK,
			'options' => ['options' => 'sanitize_search_string'],
			'default' => read_user_setting('monitor_size', 'monitor_medium', $force)
		],
		'trim' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_trim', read_config_option('monitor_trim'), $force)
		],
		'crit' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('monitor_crit', '-1', $force)
		],
		'status' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('monitor_status', '-1', $force)
		],
		'tree' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('monitor_tree', '-1', $force)
		],
		'site' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('monitor_site', '-1', $force)
		],
		'template' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('monitor_template', '-1', $force)
		],
		'id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '-1'
		],
		'page' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		],
		'sort_column' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'status',
			'options' => ['options' => 'sanitize_search_string']
		],
		'sort_direction' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => ['options' => 'sanitize_search_string']
		]
	];

	validate_store_request_vars($filters, 'sess_monitor');
	// ================= input validation =================
}

/**
 * Load host row and normalize status fields for AJAX tooltip rendering.
 *
 * @param int   $id|string   Host id.
 * @param array $thold_hosts Threshold host map.
 * @param array $config      Global Cacti config.
 *
 * @return array
 */
function monitorLoadAjaxStatusHost(mixed $id, array $thold_hosts, array $config): array {
	$host = db_fetch_row_prepared(
		'SELECT *
        FROM host
        WHERE id = ?',
		[$id]
	);

	if (!cacti_sizeof($host)) {
		return [];
	}

	$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];

	if ($host['status'] == 3 && array_key_exists($host['id'], $thold_hosts)) {
		$host['status'] = 4;
		$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=true&status=1&host_id=' . $host['id'];
	} elseif ($host['status'] == 3 && api_plugin_is_enabled('servcheck') && api_plugin_user_realm_auth('servcheck_test.php')) {
		// A triggered/failing service check promotes an otherwise Up device to
		// the distinct Service Check Failed status, pointing at the failing
		// check. Gated by the servcheck realm so unauthorized users neither see
		// the promoted status nor receive the direct history URL.
		$servchecks = getHostTriggeredServchecks($host);

		if (cacti_sizeof($servchecks)) {
			$host['status'] = 10;
			$host['anchor'] = $config['url_path'] . 'plugins/servcheck/servcheck_test.php?action=history&id=' . $servchecks[0]['id'];
		}
	}

	if ($host['availability_method'] == 0) {
		$host['status'] = 6;
	}

	$host['real_status'] = getHostStatus($host, true);
	$host['status']      = getHostStatus($host);

	return $host;
}

/**
 * Build quick-action link markup for AJAX tooltip panel.
 *
 * @param array $host   Host row.
 * @param array $config Global Cacti config.
 *
 * @return string
 */
function monitorGetAjaxStatusLinks(array $host, array $config): string {
	$links = '';

	if (api_plugin_user_realm_auth('host.php')) {
		$host_link = html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']);
		$links .= '<div><a title="' . __('Edit Device', 'monitor') . '" class="pic hyperLink monitorLink" href="' . $host_link . '"><i class="fas fa-pen-square deviceUp monitorLinkIcon"></i></a></div>';
	}

	$graphs = db_fetch_cell_prepared(
		'SELECT COUNT(*)
        FROM graph_local
        WHERE host_id = ?',
		[$host['id']]
	);

	if ($graphs > 0) {
		$graph_link = html_escape($config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id']);
		$links .= '<div><a title="' . __esc('View Graphs', 'monitor') . '" class="pic hyperLink monitorLink" href="' . $graph_link . '"><i class="fa fa-chart-line deviceUp monitorLinkIcon"></i></a></div>';
	}

	if (api_plugin_is_enabled('thold')) {
		$tholds = db_fetch_cell_prepared(
			'SELECT count(*)
            FROM thold_data
            WHERE host_id = ?',
			[$host['id']]
		);

		if ($tholds) {
			$thold_link = html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=true&status=1&host_id=' . $host['id']);
			$links .= '<div><a title="' . __esc('View Thresholds/Alerts', 'monitor') . '" class="pic hyperLink monitorLink" href="' . $thold_link . '"><i class="fas fa-tasks deviceRecovering monitorLinkIcon"></i></a></div>';
		}
	}

	if (api_plugin_is_enabled('syslog') && api_plugin_user_realm_auth('syslog.php')) {
		$syslog_path = $config['base_path'] . '/plugins/syslog';

		// Syslog exposes its DB helpers in one of two layouts: newer code keeps
		// them under includes/ (setup.php loads functions.php + database.php),
		// while older code keeps config.php + functions.php in the plugin root.
		// Detect whichever is present rather than keying off a release number.
		if (file_exists($syslog_path . '/includes/database.php')) {
			require_once($syslog_path . '/setup.php');
		} else {
			require($syslog_path . '/config.php');
			require_once($syslog_path . '/functions.php');
		}

		// Open the Syslog database connection so the queries below run against
		// the configured Syslog database (which may be separate from Cacti's)
		// instead of silently falling back to Cacti's default connection.
		if (function_exists('syslog_connect')) {
			syslog_connect();
		}

		$syslog_logs = syslog_db_fetch_cell_prepared(
			'SELECT count(*)
            FROM syslog_logs
            WHERE host = ?',
			[$host['hostname']]
		);

		$syslog_host = syslog_db_fetch_cell_prepared(
			'SELECT host_id
            FROM syslog_hosts
            WHERE host = ?',
			[$host['hostname']]
		);

		if ($syslog_logs && $syslog_host) {
			$syslog_log_link = html_escape($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=alerts&host_id=' . $syslog_host);
			$links .= '<div><a title="' . __esc('View Device Syslog Alerts', 'monitor') . '" class="pic hyperLink monitorLink" href="' . $syslog_log_link . '"><i class="fas fa-life-ring deviceDown monitorLinkIcon"></i></a></div>';
		}

		if ($syslog_host) {
			$syslog_link = html_escape($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=syslog&host_id=' . $syslog_host);
			$links .= '<div><a title="' . __esc('View Device Syslog Entries', 'monitor') . '" class="pic hyperLink monitorLink" href="' . $syslog_link . '"><i class="fas fa-life-ring deviceUp monitorLinkIcon"></i></a></div>';
		}
	}

	if (api_plugin_is_enabled('servcheck') && api_plugin_user_realm_auth('servcheck_test.php')) {
		$servchecks = getHostTriggeredServchecks($host);

		if (cacti_sizeof($servchecks)) {
			$servcheck_link = html_escape($config['url_path'] . 'plugins/servcheck/servcheck_test.php?action=history&id=' . $servchecks[0]['id']);
			$links .= '<div><a title="' . __esc('View Failing Service Check', 'monitor') . '" class="pic hyperLink monitorLink" href="' . $servcheck_link . '"><i class="fas fa-heartbeat deviceDown monitorLinkIcon"></i></a></div>';
		}
	}

	return $links;
}

/**
 * Render full hover tooltip table HTML for one host.
 *
 * @param array  $host          Host row.
 * @param string $size          Tooltip CSS size key.
 * @param string $links         Action links HTML.
 * @param string $site          Site display value.
 * @param string $sdisplay      Status display string.
 * @param string $iclass        Status CSS class.
 * @param array  $criticalities Criticality label map.
 *
 * @return string
 */
function monitorRenderAjaxStatusTooltip(array $host, string $size, string $links, string $site, string $sdisplay, string $iclass, array $criticalities): string {
	return "<table class='monitorHover $size'>
        <tr class='tableHeader'>
            <th class='left' colspan='2'>" . __('Device Status Information', 'monitor') . '</th>
        </tr>
        <tr>
            <td>' . __('Device:', 'monitor') . "</td>
            <td><a class='pic hyperLink monitorLink' href='" . html_escape($host['anchor']) . "'>" . html_escape($host['description']) . '</a></td>
        <tr>
            <td>' . __('Site:', 'monitor') . '</td>
            <td>' . html_escape($site) . '</td>
        </tr>
        <tr>
            <td>' . __('Location:', 'monitor') . '</td>
            <td>' . html_escape($host['location']) . '</td>
        </tr>' . (isset($host['monitor_criticality']) && $host['monitor_criticality'] > 0 ? '
        <tr>
            <td>' . __('Criticality:', 'monitor') . '</td>
            <td>' . html_escape($criticalities[$host['monitor_criticality']]) . '</td>
        </tr>' : '') . '
        <tr>
            <td>' . __('Status:', 'monitor') . "</td>
            <td class='$iclass'>$sdisplay</td>
        </tr>" . ($host['status'] < 3 || $host['status'] == 5 ? '
        <tr>
            <td>' . __('Admin Note:', 'monitor') . "</td>
            <td class='$iclass'>" . html_escape($host['monitor_text']) . '</td>
        </tr>' : '') . ($host['availability_method'] > 0 ? '
        <tr>
            <td>' . __('IP/Hostname:', 'monitor') . '</td>
            <td>' . html_escape($host['hostname']) . '</td>
        </tr>' : '') . ($host['availability_method'] > 0 ? "
        <tr>
            <td class='nowrap'>" . __('Curr/Avg:', 'monitor') . '</td>
            <td>' . __('%d ms', $host['cur_time'], 'monitor') . ' / ' . __('%d ms', $host['avg_time'], 'monitor') . '</td>
        </tr>' : '') . (isset($host['monitor_warn']) && ($host['monitor_warn'] > 0 || $host['monitor_alert'] > 0) ? "
        <tr>
            <td class='nowrap'>" . __('Warn/Alert:', 'monitor') . '</td>
            <td>' . __('%0.2d ms', $host['monitor_warn'], 'monitor') . ' / ' . __('%0.2d ms', $host['monitor_alert'], 'monitor') . '</td>
        </tr>' : '') . '
        <tr>
            <td>' . __('Last Fail:', 'monitor') . '</td>
            <td>' . html_escape($host['status_fail_date']) . '</td>
        </tr>
        <tr>
            <td>' . __('Time In State:', 'monitor') . '</td>
            <td>' . get_timeinstate($host) . '</td>
        </tr>
        <tr>
            <td>' . __('Availability:', 'monitor') . '</td>
            <td>' . round((float) $host['availability'], 2) . ' %</td>
        </tr>' . ($host['snmp_version'] > 0 && ($host['status'] == 3 || $host['status'] == 2) ? '
        <tr>
            <td>' . __('Agent Uptime:', 'monitor') . '</td>
            <td>' . ($host['status'] == 3 || $host['status'] == 5 ? monitorPrintHostTime($host['snmp_sysUpTimeInstance']) : __('N/A', 'monitor')) . "</td>
        </tr>
        <tr>
            <td class='nowrap'>" . __('Sys Description:', 'monitor') . '</td>
            <td>' . html_escape(monitorTrim($host['snmp_sysDescr'])) . '</td>
        </tr>
        <tr>
            <td>' . __('Location:', 'monitor') . '</td>
            <td>' . html_escape(monitorTrim($host['snmp_sysLocation'])) . '</td>
        </tr>
        <tr>
            <td>' . __('Contact:', 'monitor') . '</td>
            <td>' . html_escape(monitorTrim($host['snmp_sysContact'])) . '</td>
        </tr>' : '') . ($host['notes'] != '' ? '
        <tr>
            <td>' . __('Notes:', 'monitor') . '</td>
            <td>' . html_escape($host['notes']) . '</td>
        </tr>' : '') . "
        <tr><td colspan='2' style='width:100%'><hr></td></tr>
        <tr><td colspan='2' style='width:100%'><div style='display:flex;justify-content:space-around;'>$links</div></td></tr>
        </table>";
}

/**
 * Handle AJAX monitor status tooltip request and print response HTML.
 *
 * @return bool|null
 */
function ajaxStatus(): void {
	global $thold_hosts, $config, $iclasses, $criticalities;

	validateRequestVars();

	if (!isset_request_var('id') || !get_filter_request_var('id')) {
		return;
	}

	$id   = get_request_var('id');
	$size = get_request_var('size');
	$host = monitorLoadAjaxStatusHost($id, $thold_hosts, $config);

	if (!cacti_sizeof($host)) {
		cacti_log('Attempted to retrieve status for missing Device ' . $id, false, 'MONITOR', POLLER_VERBOSITY_HIGH);

		return;
	}

	$links = monitorGetAjaxStatusLinks($host, $config);

	if (strtotime($host['status_fail_date']) < 86400) {
		$host['status_fail_date'] = __('Never', 'monitor');
	}

	if ($host['location'] == '') {
		$host['location'] = __('Unspecified', 'monitor');
	}

	$iclass   = $iclasses[$host['status']];
	$sdisplay = getHostStatusDescription($host['real_status']);
	$site     = db_fetch_cell_prepared('SELECT name FROM sites WHERE id = ?', [$host['site_id']]);

	if ($site == '') {
		$site = __('None', 'monitor');
	}

	print monitorRenderAjaxStatusTooltip($host, $size, $links, $site, $sdisplay, $iclass, $criticalities);
}

/**
 * Handle AJAX request for the List-view device detail panel and print its HTML.
 *
 * Renders a stack of intropage-style cards (host info, data collection, service
 * checks, thresholds, recent syslog, device links) for a single device.
 *
 * @return void
 */
function ajaxHostPanel(): void {
	global $thold_hosts, $config, $iclasses;

	validateRequestVars();

	if (!isset_request_var('id') || !get_filter_request_var('id')) {
		return;
	}

	$host = monitorLoadAjaxStatusHost(get_request_var('id'), $thold_hosts, $config);

	if (!cacti_sizeof($host)) {
		return;
	}

	print "<div class='monitorPanels'>";
	print monitorPanelHostInfo($host, $iclasses);
	print monitorPanelCollection($host);
	print monitorPanelServiceChecks($host, $config);
	print monitorPanelTholds($host, $config);
	print monitorPanelSyslog($host, $config);
	print monitorPanelLinks($host, $config);
	print '</div>';
}

/**
 * Wrap panel body HTML in a titled intropage-style card.
 *
 * @param string $title Card title.
 * @param string $body  Pre-built, escaped card body HTML.
 * @param string $icon  Font Awesome icon class (e.g. fa-server).
 *
 * @return string
 */
function monitorPanelCard(string $title, string $body, string $icon = 'fa-info-circle'): string {
	return "<div class='monitorPanel'>
		<div class='monitorPanelHeader'><i class='fas $icon'></i> " . html_escape($title) . "</div>
		<div class='monitorPanelBody'>$body</div>
	</div>";
}

/**
 * Build one label/value list row for a panel body.
 *
 * @param string $label Human-readable label (escaped here).
 * @param string $value Pre-built value HTML (caller escapes plain text).
 *
 * @return string
 */
function monitorPanelRow(string $label, string $value): string {
	return '<li><span class="monitorPanelLabel">' . html_escape($label) . '</span><span class="monitorPanelValue">' . $value . '</span></li>';
}

/**
 * Map a host availability_method id to a readable label.
 *
 * @param int $method Availability method id.
 *
 * @return string
 */
function monitorAvailabilityMethodLabel(int $method): string {
	$labels = [
		0 => __('None', 'monitor'),
		1 => __('SNMP and Ping', 'monitor'),
		2 => __('SNMP Uptime', 'monitor'),
		3 => __('Ping', 'monitor'),
		4 => __('SNMP or Ping', 'monitor'),
		5 => __('SNMP Get SysDesc', 'monitor'),
		6 => __('SNMP Get Next', 'monitor'),
	];

	return $labels[$method] ?? __('Method %d', $method, 'monitor');
}

/**
 * Render the Host Information panel card.
 *
 * @param array $host     Host row with computed status fields.
 * @param array $iclasses Status index => CSS class map.
 *
 * @return string
 */
function monitorPanelHostInfo(array $host, array $iclasses): string {
	$iclass   = $iclasses[$host['status']] ?? 'deviceUnknown';
	$sdisplay = getHostStatusDescription($host['real_status']);

	$site = db_fetch_cell_prepared('SELECT name FROM sites WHERE id = ?', [$host['site_id']]);
	$tmpl = db_fetch_cell_prepared('SELECT name FROM host_template WHERE id = ?', [$host['host_template_id']]);

	$body  = '<ul class="monitorPanelProps">';
	$body .= monitorPanelRow(__('Description', 'monitor'), html_escape($host['description']));
	$body .= monitorPanelRow(__('Hostname', 'monitor'), html_escape($host['hostname']));
	$body .= monitorPanelRow(__('Status', 'monitor'), "<span class='monitorStatus deviceStatus $iclass'>" . html_escape($sdisplay) . '</span>');
	$body .= monitorPanelRow(__('Site', 'monitor'), html_escape($site != '' ? $site : __('None', 'monitor')));
	$body .= monitorPanelRow(__('Device Template', 'monitor'), html_escape($tmpl != '' ? $tmpl : __('None', 'monitor')));
	$body .= monitorPanelRow(__('Location', 'monitor'), html_escape($host['location'] != '' ? $host['location'] : __('Unspecified', 'monitor')));

	if (isset($host['snmp_sysUptimeInstance']) && $host['snmp_sysUptimeInstance'] > 0) {
		$body .= monitorPanelRow(__('SNMP Uptime', 'monitor'), html_escape(monitorPrintHostTime($host['snmp_sysUptimeInstance'])));
	}

	if (isset($host['snmp_sysContact']) && $host['snmp_sysContact'] != '') {
		$body .= monitorPanelRow(__('Contact', 'monitor'), html_escape(monitorTrim($host['snmp_sysContact'])));
	}

	$body .= '</ul>';

	return monitorPanelCard(__('Host Information', 'monitor'), $body, 'fa-server');
}

/**
 * Render the Data Collection timing panel card.
 *
 * @param array $host Host row.
 *
 * @return string
 */
function monitorPanelCollection(array $host): string {
	$body  = '<ul class="monitorPanelProps">';
	$body .= monitorPanelRow(__('Availability Method', 'monitor'), html_escape(monitorAvailabilityMethodLabel((int) $host['availability_method'])));

	if ($host['availability_method'] > 0) {
		$body .= monitorPanelRow(__('Current / Average Ping', 'monitor'), html_escape(__('%0.2f ms', $host['cur_time'], 'monitor') . ' / ' . __('%0.2f ms', $host['avg_time'], 'monitor')));
		$body .= monitorPanelRow(__('Min / Max Ping', 'monitor'), html_escape(__('%0.2f ms', $host['min_time'], 'monitor') . ' / ' . __('%0.2f ms', $host['max_time'], 'monitor')));
	}

	$body .= monitorPanelRow(__('Availability', 'monitor'), html_escape(round($host['availability'], 2) . ' %'));
	$body .= monitorPanelRow(__('Total / Failed Polls', 'monitor'), html_escape($host['total_polls'] . ' / ' . $host['failed_polls']));

	if (isset($host['status_rec_date']) && strtotime($host['status_rec_date']) > 943916400) {
		$body .= monitorPanelRow(__('In Current Status Since', 'monitor'), html_escape($host['status_rec_date']));
	}

	$fail = $host['status_fail_date'];

	if (strtotime((string) $fail) < 86400) {
		$fail = __('Never', 'monitor');
	}

	$body .= monitorPanelRow(__('Last Fail', 'monitor'), html_escape($fail));
	$body .= '</ul>';

	return monitorPanelCard(__('Data Collection', 'monitor'), $body, 'fa-stopwatch');
}

/**
 * Render the Service Checks panel card (servcheck plugin, schema-aware).
 *
 * @param array $host   Host row.
 * @param array $config Global Cacti config.
 *
 * @return string
 */
function monitorPanelServiceChecks(array $host, array $config): string {
	if (!api_plugin_is_enabled('servcheck') || !api_plugin_user_realm_auth('servcheck_test.php') || !db_table_exists('plugin_servcheck_test')) {
		return '';
	}

	$check_col  = db_column_exists('plugin_servcheck_test', 'last_check') ? 'last_check' : 'lastcheck';
	$has_result = db_column_exists('plugin_servcheck_test', 'last_result');

	$cols = 'id, name, enabled, triggered, failures' . ($has_result ? ', last_result' : '');

	$tests = db_fetch_assoc_prepared(
		"SELECT $cols
		FROM plugin_servcheck_test
		WHERE hostname = ? OR ipaddress = ?
		ORDER BY name",
		[$host['hostname'], $host['hostname']]
	);

	if (!cacti_sizeof($tests)) {
		return '';
	}

	$rows = '';

	foreach ($tests as $t) {
		if ($t['enabled'] == '') {
			$cls   = 'deviceUnmonitored';
			$label = __('Disabled', 'monitor');
		} elseif ($t['triggered'] > 0 || ($has_result && $t['last_result'] != 'ok' && $t['last_result'] != 'not yet')) {
			$cls   = 'deviceServiceCheck';
			$label = __('Failing', 'monitor');
		} elseif ($has_result && $t['last_result'] == 'not yet') {
			$cls   = 'deviceUnknown';
			$label = __('Not tested', 'monitor');
		} else {
			$cls   = 'deviceUp';
			$label = __('OK', 'monitor');
		}

		$link  = html_escape($config['url_path'] . 'plugins/servcheck/servcheck_test.php?action=history&id=' . $t['id']);
		$rows .= "<li><a class='monitorLink' href='$link'>" . html_escape($t['name']) . "</a><span class='monitorStatus deviceStatus $cls'>" . html_escape($label) . '</span></li>';
	}

	return monitorPanelCard(__('Service Checks', 'monitor'), '<ul class="monitorPanelProps">' . $rows . '</ul>', 'fa-heartbeat');
}

/**
 * Render the Thresholds panel card (thold plugin).
 *
 * @param array $host   Host row.
 * @param array $config Global Cacti config.
 *
 * @return string
 */
function monitorPanelTholds(array $host, array $config): string {
	if (!api_plugin_is_enabled('thold') || !db_table_exists('thold_data')) {
		return '';
	}

	$tholds = db_fetch_assoc_prepared(
		'SELECT *
		FROM thold_data
		WHERE host_id = ?
		ORDER BY id',
		[$host['id']]
	);

	if (!cacti_sizeof($tholds)) {
		return '';
	}

	$rows = '';

	foreach ($tholds as $t) {
		$name = $t['name_cache'] ?? ($t['name'] ?? __('Threshold %d', $t['id'], 'monitor'));

		if (isset($t['thold_enabled']) && $t['thold_enabled'] != 'on') {
			$cls   = 'deviceUnmonitored';
			$label = __('Disabled', 'monitor');
		} elseif ((isset($t['thold_alert']) && $t['thold_alert'] != 0) || (isset($t['bl_alert']) && $t['bl_alert'] > 0)) {
			$cls   = 'deviceThreshold';
			$label = __('Triggered', 'monitor');
		} else {
			$cls   = 'deviceUp';
			$label = __('OK', 'monitor');
		}

		$link  = html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=true&status=1&host_id=' . $host['id']);
		$rows .= "<li><a class='monitorLink' href='$link'>" . html_escape($name) . "</a><span class='monitorStatus deviceStatus $cls'>" . html_escape($label) . '</span></li>';
	}

	return monitorPanelCard(__('Thresholds', 'monitor'), '<ul class="monitorPanelProps">' . $rows . '</ul>', 'fa-tasks');
}

/**
 * Render the recent Syslog panel card (syslog plugin, last hour).
 *
 * @param array $host   Host row.
 * @param array $config Global Cacti config.
 *
 * @return string
 */
function monitorPanelSyslog(array $host, array $config): string {
	if (!api_plugin_is_enabled('syslog') || !api_plugin_user_realm_auth('syslog.php')) {
		return '';
	}

	$rows = monitorSyslogRecentForHost($host, $config);

	if ($rows === null) {
		return '';
	}

	if (!cacti_sizeof($rows)) {
		$body = '<ul class="monitorPanelProps"><li><span class="monitorPanelValue">' . __('No syslog messages in the last hour.', 'monitor') . '</span></li></ul>';

		return monitorPanelCard(__('Syslog (last hour)', 'monitor'), $body, 'fa-life-ring');
	}

	$list = '';

	foreach ($rows as $r) {
		$time    = isset($r['logtime']) ? html_escape($r['logtime']) : '';
		$message = isset($r['message']) ? html_escape($r['message']) : '';
		$list   .= "<li><span class='monitorSyslogTime'>$time</span><span class='monitorSyslogMsg'>$message</span></li>";
	}

	return monitorPanelCard(__('Syslog (last hour)', 'monitor'), '<ul class="monitorPanelSyslog">' . $list . '</ul>', 'fa-life-ring');
}

/**
 * Fetch recent syslog rows for a host within the last hour.
 *
 * Loads the syslog plugin's DB helpers (supporting both includes/ and legacy
 * layouts) and queries its configured database. Returns null when syslog is
 * not queryable, otherwise an array of rows (possibly empty).
 *
 * @param array $host   Host row.
 * @param array $config Global Cacti config.
 *
 * @return array|null
 */
function monitorSyslogRecentForHost(array $host, array $config): ?array {
	$syslog_path = $config['base_path'] . '/plugins/syslog';

	if (file_exists($syslog_path . '/includes/database.php')) {
		require_once($syslog_path . '/setup.php');
	} elseif (file_exists($syslog_path . '/config.php')) {
		require($syslog_path . '/config.php');
		require_once($syslog_path . '/functions.php');
	} else {
		return null;
	}

	if (!function_exists('syslog_db_fetch_assoc_prepared')) {
		return null;
	}

	if (function_exists('syslog_connect')) {
		syslog_connect();
	}

	$rows = syslog_db_fetch_assoc_prepared(
		"SELECT logtime, message
		FROM syslog_logs
		WHERE host = ?
		AND logtime > DATE_SUB(NOW(), INTERVAL 1 HOUR)
		ORDER BY logtime DESC
		LIMIT 20",
		[$host['hostname']]
	);

	return is_array($rows) ? $rows : [];
}

/**
 * Render the Device Links panel card (edit, graphs, device address).
 *
 * @param array $host   Host row.
 * @param array $config Global Cacti config.
 *
 * @return string
 */
function monitorPanelLinks(array $host, array $config): string {
	$links = '';

	if (api_plugin_user_realm_auth('host.php')) {
		$edit  = html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']);
		$links .= "<li><a class='monitorLink' href='$edit'><i class='fas fa-pen-square'></i> " . __('Edit Device', 'monitor') . '</a></li>';
	}

	$graphs = db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_local WHERE host_id = ?', [$host['id']]);

	if ($graphs > 0) {
		$glink  = html_escape($config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id']);
		$links .= "<li><a class='monitorLink' href='$glink'><i class='fa fa-chart-line'></i> " . __('View Graphs', 'monitor') . '</a></li>';
	}

	if ($host['hostname'] != '') {
		$scheme = (filter_var($host['hostname'], FILTER_VALIDATE_IP) || preg_match('/^[a-z0-9.\-]+$/i', $host['hostname'])) ? 'http://' : '';

		if ($scheme != '') {
			$device = html_escape($scheme . $host['hostname']);
			$links .= "<li><a class='monitorLink' href='$device' target='_blank' rel='noopener noreferrer'><i class='fas fa-external-link-alt'></i> " . __('Open Actual Device', 'monitor') . '</a></li>';
		}
	}

	if ($links == '') {
		return '';
	}

	return monitorPanelCard(__('Device Links', 'monitor'), '<ul class="monitorPanelLinks">' . $links . '</ul>', 'fa-link');
}
