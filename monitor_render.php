<?php

declare(strict_types = 1);

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
 * Render default monitor grouping output with active filters applied.
 *
 * @return string
 */
function renderDefault(): string {
	global $maxchars;

	$result = '';

	$sql_where = '';
	$sql_join  = '';
	$sql_limit = '';
	$sql_order = 'ORDER BY description';

	$rows = get_request_var('rows');

	if ($rows == '-1') {
		$rows = read_user_setting('monitor_rows');
	}

	if (!is_numeric($rows)) {
		$rows = read_config_option('num_rows_table');
	}

	if (get_request_var('view') == 'list') {
		$sql_order = get_order_string();
	}

	renderWhereJoin($sql_where, $sql_join);

	$poller_interval = read_config_option('poller_interval');

	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$hosts_sql = ("SELECT DISTINCT h.*, IFNULL(s.name,' " . __('Non-Site Device', 'monitor') . " ') AS site_name,
        CAST(IF(availability_method = 0, '0',
            IF(status_event_count > 0 AND status IN (1, 2), status_event_count*$poller_interval,
            IF(UNIX_TIMESTAMP(status_rec_date) < 943916400 AND status IN (0, 3), total_polls*$poller_interval,
            IF(UNIX_TIMESTAMP(status_rec_date) > 943916400, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(status_rec_date),
            IF(snmp_sysUptimeInstance>0 AND snmp_version > 0, snmp_sysUptimeInstance/100, UNIX_TIMESTAMP()
        ))))) AS unsigned) AS instate
		FROM host AS h
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		$sql_join
		$sql_where
		$sql_order
		$sql_limit");

	$hosts = db_fetch_assoc($hosts_sql);

	$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT h.id)
        FROM host AS h
        LEFT JOIN sites AS s
        ON h.site_id = s.id
        $sql_join
        $sql_where");

	if (cacti_sizeof($hosts)) {
		// Determine the correct width of the cell
		$maxlen = 10;

		if (get_request_var('view') == 'default') {
			$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
				FROM host AS h
				$sql_join
				$sql_where");
		}

		$maxlen = getMonitorTrimLength($maxlen);

		$function = 'renderHeader' . ucfirst(get_request_var('view'));

		if (function_exists($function)) {
			// Call the custom render_header_ function
			$result .= $function($total_rows, $rows);
		}

		$count = 0;

		foreach ($hosts as $host) {
			if (is_device_allowed($host['id'])) {
				$result .= renderHost($host, true, $maxlen);
			}

			$count++;
		}

		$function = 'renderFooter' . ucfirst(get_request_var('view'));

		if (function_exists($function)) {
			// Call the custom render_footer_ function
			$result .= $function($total_rows, $rows);
		}
	}

	return $result;
}

/**
 * Render host output grouped by site.
 *
 * @return string
 */
function renderSite(): string {
	global $maxchars;

	$result = '';

	$sql_where = '';
	$sql_join  = '';
	$sql_limit = '';

	$rows = get_request_var('rows');

	if ($rows == '-1') {
		$rows = read_user_setting('monitor_rows');
	}

	if (!is_numeric($rows)) {
		$rows = read_config_option('num_rows_table');
	}

	renderWhereJoin($sql_where, $sql_join);

	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$hosts_sql = ("SELECT DISTINCT h.*, IFNULL(s.name,' " . __('Non-Site Devices', 'monitor') . " ') AS site_name
		FROM host AS h
		LEFT JOIN sites AS s
		ON s.id = h.site_id
		$sql_join
		$sql_where
		ORDER BY site_name, description
		$sql_limit");

	$hosts = db_fetch_assoc($hosts_sql);

	$ctemp = -1;
	$ptemp = -1;

	if (cacti_sizeof($hosts)) {
		$suppressGroups = false;
		$function       = 'renderSuppressgroups' . ucfirst(get_request_var('view'));

		if (function_exists($function)) {
			$suppressGroups = $function();
		}

		$function = 'renderHeader' . ucfirst(get_request_var('view'));

		if (function_exists($function)) {
			// Call the custom render_header_ function
			$result .= $function();
			$suppressGroups = true;
		}

		foreach ($hosts as $index => $host) {
			if (is_device_allowed($host['id'])) {
				$host_ids[] = $host['id'];
			} else {
				unset($hosts[$index]);
			}
		}

		// Determine the correct width of the cell
		$maxlen = 10;

		if (get_request_var('view') == 'default') {
			$maxlen = db_fetch_cell('SELECT MAX(LENGTH(description))
				FROM host AS h
				WHERE id IN (' . implode(',', $host_ids) . ')');
		}
		$maxlen = getMonitorTrimLength($maxlen);

		$class   = get_request_var('size');
		$csuffix = get_request_var('view');

		if ($csuffix == 'default') {
			$csuffix = '';
		}

		foreach ($hosts as $host) {
			$ctemp = $host['site_id'];

			if (!$suppressGroups) {
				if ($ctemp != $ptemp && $ptemp > 0) {
					$result .= '</div>';
				}

				if ($ctemp != $ptemp) {
					$result .= "<div class='monitorTableHeader'>
						<div class='navBarNavigation'>
							<div class='navBarNavigationNone'>" . html_escape($host['site_name']) . "</div>
						</div>
					</div>
					<div class='monitor_container'>";
				}
			}

			$result .= renderHost($host, true, $maxlen);

			if ($ctemp != $ptemp) {
				$ptemp = $ctemp;
			}
		}

		if ($ptemp == $ctemp && !$suppressGroups) {
			$result .= '</div>';
		}

		$function = 'renderFooter' . ucfirst(get_request_var('view'));

		if (function_exists($function)) {
			// Call the custom render_footer_ function
			$result .= $function();
		}
	}

	return $result;
}

/**
 * Render host output grouped by host template.
 *
 * @return string
 */
function renderTemplate(): string {
	global $maxchars;

	$result = '';

	$sql_where = '';
	$sql_join  = '';
	$sql_limit = '';

	$rows = get_request_var('rows');

	if ($rows == '-1') {
		$rows = read_user_setting('monitor_rows');
	}

	if (!is_numeric($rows)) {
		$rows = read_config_option('num_rows_table');
	}

	renderWhereJoin($sql_where, $sql_join);

	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	if (get_request_var('template') > 0) {
		$sql_where .= ($sql_where == '' ? '' : 'AND ') . 'ht.id = ' . get_request_var('template');
	}

	$sql_template  = 'INNER JOIN host_template AS ht ON h.host_template_id=ht.id ';

	if (get_request_var('template') == -2) {
		$sql_where .= ($sql_where == '' ? '' : 'AND ') . 'ht.id IS NULL';
		$sql_template = 'LEFT JOIN host_template AS ht ON h.host_template_id=ht.id ';
	}

	$hosts = db_fetch_assoc("SELECT DISTINCT
		h.*, ht.name AS host_template_name
		FROM host AS h
		$sql_template
		$sql_join
		$sql_where
		ORDER BY ht.name, h.description
		$sql_limit");

	$ctemp = -1;
	$ptemp = -1;

	if (cacti_sizeof($hosts)) {
		$suppressGroups = false;
		$function       = 'renderSuppressgroups' . ucfirst(get_request_var('view'));

		if (function_exists($function)) {
			$suppressGroups = $function();
		}

		$function = 'renderHeader' . ucfirst(get_request_var('view'));

		if (function_exists($function)) {
			// Call the custom render_header_ function
			$result .= $function();
			$suppressGroups = true;
		}

		foreach ($hosts as $index => $host) {
			if (is_device_allowed($host['id'])) {
				$host_ids[] = $host['id'];
			} else {
				unset($hosts[$index]);
			}
		}

		// Determine the correct width of the cell
		$maxlen = 10;

		if (get_request_var('view') == 'default') {
			$maxlen = db_fetch_cell('SELECT MAX(LENGTH(description))
				FROM host AS h
				WHERE id IN (' . implode(',', $host_ids) . ')');
		}
		$maxlen = getMonitorTrimLength($maxlen);

		$class   = get_request_var('size');
		$csuffix = get_request_var('view');

		if ($csuffix == 'default') {
			$csuffix = '';
		}

		foreach ($hosts as $host) {
			$ctemp = $host['host_template_id'];

			if (!$suppressGroups) {
				if ($ctemp != $ptemp && $ptemp > 0) {
					$result .= '</div>';
				}

				if ($ctemp != $ptemp) {
					$result .= "<div class='monitorTableHeader'>
						<div class='navBarNavigation'>
							<div class='navBarNavigationNone'>" . html_escape($host['host_template_name']) . "</div>
						</div>
					</div>
					<div class='monitor_container'>";
				}
			}

			$result .= renderHost($host, true, $maxlen);

			if ($ctemp != $ptemp) {
				$ptemp = $ctemp;
			}
		}

		if ($ptemp == $ctemp && !$suppressGroups) {
			$result .= '</div>';
		}

		$function = 'renderFooter' . ucfirst(get_request_var('view'));

		if (function_exists($function)) {
			// Call the custom render_footer_ function
			$result .= $function();
		}
	}

	return $result;
}

/**
 * Filter out disallowed hosts and return normalized host/id lists.
 *
 * @param array $hosts Host rows.
 *
 * @return array{array, array}
 */
function monitorFilterAllowedHosts(array $hosts): array {
	$host_ids = [];

	foreach ($hosts as $index => $host) {
		if (is_device_allowed($host['id'])) {
			$host_ids[] = $host['id'];
		} else {
			unset($hosts[$index]);
		}
	}

	return [array_values($hosts), $host_ids];
}

/**
 * Determine max description trim length for tree rendering context.
 *
 * @return int
 */
function monitorGetTreeRenderMaxLength(): int {
	$maxlen = 10;

	if (get_request_var('view') == 'default') {
		$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
            FROM host AS h
            INNER JOIN graph_tree_items AS gti
            ON gti.host_id = h.id
            WHERE disabled = ''
            AND deleted = ''");
	}

	return getMonitorTrimLength($maxlen);
}

/**
 * Build map of tree branch labels keyed by "tree_id:parent_id".
 *
 * @param array $branchWhost Tree branch/host rows.
 *
 * @return array
 */
function monitorBuildTreeTitles(array $branchWhost): array {
	$titles = [];
	$ptree  = '';

	foreach ($branchWhost as $b) {
		if ($ptree != $b['graph_tree_id']) {
			$titles[$b['graph_tree_id'] . ':0'] = __('Root Branch', 'monitor');
			$ptree                              = $b['graph_tree_id'];
		}

		if ($b['parent'] > 0) {
			$titles[$b['graph_tree_id'] . ':' . $b['parent']] = db_fetch_cell_prepared(
				'SELECT title
                FROM graph_tree_items
                WHERE id = ?
                AND graph_tree_id = ?
                ORDER BY position',
				[$b['parent'], $b['graph_tree_id']]
			);
		}
	}

	return $titles;
}

/**
 * Render grouped tree title/branch sections for monitor view.
 *
 * @param array $titles Tree titles map keyed by "tree_id:parent_id".
 * @param int   $maxlen Trim length used for host title rendering.
 *
 * @return string
 */
function monitorRenderTreeTitleSections(array $titles, int $maxlen): string {
	$result = '';
	$ptree  = '';

	foreach ($titles as $index => $title) {
		[$graph_tree_id, $parent] = explode(':', $index);
		$oid                      = $parent;

		$sql_where = '';
		$sql_join  = '';
		renderWhereJoin($sql_where, $sql_join);

		$hosts_sql = "SELECT h.*, IFNULL(s.name,' " . __('Non-Site Device', 'monitor') . " ') AS site_name
            FROM host AS h
            LEFT JOIN sites AS s
            ON h.site_id = s.id
            INNER JOIN graph_tree_items AS gti
            ON h.id = gti.host_id
            $sql_join
            $sql_where
            AND parent = ?
            AND graph_tree_id = ?
            GROUP BY h.id
            ORDER BY gti.position";

		$hosts = db_fetch_assoc_prepared($hosts_sql, [$oid, $graph_tree_id]);

		$tree_name = db_fetch_cell_prepared(
			'SELECT name
            FROM graph_tree
            WHERE id = ?',
			[$graph_tree_id]
		);

		if ($ptree != $tree_name) {
			if ($ptree != '') {
				$result .= '</div>';
			}

			$result .= "<div class='monitorTableHeader'>
                <div class='navBarNavigation'>
                    <div class='navBarNavigationNone'>" . __esc('Tree: %s', $tree_name, 'monitor') . "</div>
                </div>
            </div>
            <div class='monitorTable'>
                <div class='monitor_sub_container'>";

			$ptree = $tree_name;
		}

		if (!cacti_sizeof($hosts)) {
			continue;
		}

		[$hosts] = monitorFilterAllowedHosts($hosts);

		if (!cacti_sizeof($hosts)) {
			continue;
		}

		$result .= "<div class='monitorSubTable'><div class='navBarNavigation'><div class='navBarNavigationNone'>" . __esc('Branch: %s', $title, 'monitor') . "</div></div><div class='monitor_sub_container'>";

		foreach ($hosts as $host) {
			$result .= renderHost($host, true, $maxlen);
		}

		$result .= '</div></div>';
	}

	return $result;
}

/**
 * Render section for monitored hosts that are not attached to any tree.
 *
 * @return string
 */
function monitorRenderNonTreeSection(): string {
	$result = '';

	if (get_request_var('tree') >= 0) {
		return $result;
	}

	$hosts = getHostNonTreeArray();

	if (!cacti_sizeof($hosts)) {
		return $result;
	}

	[$hosts, $host_ids] = monitorFilterAllowedHosts($hosts);

	if (!cacti_sizeof($hosts)) {
		return $result;
	}

	$maxlen = 10;

	if (get_request_var('view') == 'default' && cacti_sizeof($host_ids)) {
		$maxlen = db_fetch_cell('SELECT MAX(LENGTH(description))
            FROM host AS h
            WHERE id IN (' . implode(',', $host_ids) . ")
            AND h.deleted = ''");
	}

	$maxlen = getMonitorTrimLength($maxlen);

	$result .= "<div class='monitorTableHeader'>
        <div class='navBarNavigation'>
            <div class='navBarNavigationNone'>" . __('Non-Tree Devices', 'monitor') . "</div>
        </div>
    </div>
    <div class='monitor_container'>";

	foreach ($hosts as $leaf) {
		$result .= renderHost($leaf, true, $maxlen);
	}

	$result .= '</div></div>';

	return $result;
}

/**
 * Render monitor tree grouping view, including tree and non-tree sections.
 *
 * @return string
 */
function renderTree(): string {
	$result = '';

	if (get_request_var('tree') > 0) {
		$sql_where = 'gt.id=' . get_request_var('tree');
	} else {
		$sql_where = '';
	}

	if (get_request_var('tree') != -2) {
		$tree_list = get_allowed_trees(false, false, $sql_where, 'sequence');
	} else {
		$tree_list = [];
	}

	$function = 'renderHeader' . ucfirst(get_request_var('view'));

	if (function_exists($function)) {
		$hosts = [];

		// Call the custom render_header_ function
		$result .= $function();
	}

	if (cacti_sizeof($tree_list)) {
		$tree_ids = [];

		foreach ($tree_list as $tree) {
			$tree_ids[$tree['id']] = $tree['id'];
		}

		$sql_where = '';
		$sql_join  = '';
		renderWhereJoin($sql_where, $sql_join);

		$branchWhost = db_fetch_assoc("SELECT DISTINCT gti.graph_tree_id, gti.parent
            FROM graph_tree_items AS gti
            INNER JOIN graph_tree AS gt
            ON gt.id = gti.graph_tree_id
            INNER JOIN host AS h
            ON h.id = gti.host_id
            $sql_join
            $sql_where
            AND gti.host_id > 0
            AND gti.graph_tree_id IN (" . implode(',', $tree_ids) . ')
            ORDER BY gt.sequence, gti.position');

		if (cacti_sizeof($branchWhost)) {
			$titles = monitorBuildTreeTitles($branchWhost);
			$result .= monitorRenderTreeTitleSections($titles, monitorGetTreeRenderMaxLength());
		}

		$result .= '</div>';
	}

	$result .= monitorRenderNonTreeSection();

	$function = 'renderFooter' . ucfirst(get_request_var('view'));

	if (function_exists($function)) {
		// Call the custom render_footer_ function
		$result .= $function();
	}

	return $result;
}

/**
 * Resolve display status for a host with monitor/thold/mute overlays applied.
 *
 * @param array $host Host row data.
 * @param bool  $real Return raw computed status even if icon class is missing.
 *
 * @return int
 */
function getHostStatus(array $host, bool $real = false): int {
	global $thold_hosts, $iclasses;

	// If the host has been muted, show the muted Icon
	if ($host['status'] != 1 && in_array($host['id'], $thold_hosts, true)) {
		$host['status'] = 4;
	}

	if (in_array($host['id'], $_SESSION['monitor_muted_hosts'], true) && $host['status'] == 1) {
		$host['status'] = 5;
	} elseif (in_array($host['id'], $_SESSION['monitor_muted_hosts'], true) && $host['status'] == 4) {
		$host['status'] = 9;
	} elseif ($host['status'] == 3) {
		if ($host['cur_time'] > $host['monitor_alert'] && !empty($host['monitor_alert'])) {
			$host['status'] = 8;
		} elseif ($host['cur_time'] > $host['monitor_warn'] && !empty($host['monitor_warn'])) {
			$host['status'] = 7;
		}
	}

	// If wanting the real status, or the status is already known
	// return the real status, otherwise default to unknown
	return ($real || array_key_exists($host['status'], $iclasses)) ? $host['status'] : 0;
}

/**
 * Translate status code into localized display label.
 *
 * @param int $status Monitor status code.
 *
 * @return string
 */
function getHostStatusDescription(int|string $status): string {
	global $icolorsdisplay;

	if (array_key_exists($status, $icolorsdisplay)) {
		return $icolorsdisplay[$status];
	} else {
		return __('Unknown', 'monitor') . " ($status)";
	}
}

/**
 * Render one host using view-specific renderer or default tile layout.
 *
 * @param array $host   Host row data.
 * @param bool  $float  Legacy compatibility flag (currently unused).
 * @param int   $maxlen Maximum host description trim length.
 *
 * @return string|null
 */
function renderHost(array $host, bool $float = true, int $maxlen = 10): ?string {
	global $thold_hosts, $config, $icolorsdisplay, $iclasses, $classes, $maxchars, $mon_zoom_state;

	// throw out tree root items
	if (array_key_exists('name', $host)) {
		return null;
	}

	if ($host['id'] <= 0) {
		return null;
	}

	$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];

	if ($host['status'] == 3 && array_key_exists($host['id'], $thold_hosts)) {
		$host['status'] = 4;
		$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=true&status=1&host_id=' . $host['id'];
	}

	$host['real_status'] = getHostStatus($host, true);
	$host['status']      = getHostStatus($host);
	$host['iclass']      = $iclasses[$host['status']];

	$function = 'renderHost' . ucfirst(get_request_var('view'));

	if (function_exists($function)) {
		// Call the custom render_host_ function
		$result = $function($host);
	} else {
		$iclass = getStatusIcon($host['status'], $host['monitor_icon']);
		$fclass = get_request_var('size');

		$monitor_times     = read_user_setting('monitor_uptime');
		$monitor_time_html = '';

		if ($host['status'] <= 2 || $host['status'] == 5) {
			if ($mon_zoom_state) {
				$fclass = 'monitor_errorzoom';
			}
			$tis = get_timeinstate($host);

			if ($monitor_times == 'on') {
				$monitor_time_html = "<br><span class='monitor_device{$fclass} deviceDown'>$tis</span>";
			}
			$result = "<div class='$fclass flash monitor_device_frame'><a class='pic hyperLink' href='" . html_escape($host['anchor']) . "'><i id='" . $host['id'] . "' class='$iclass " . $host['iclass'] . "'></i><br><span class='{$fclass}_title'>" . title_trim(html_escape($host['description']), $maxlen) . "</span>$monitor_time_html</a></div>";
		} else {
			$tis = get_uptime($host);

			if ($monitor_times == 'on') {
				$monitor_time_html = "<br><div class='monitor_device{$fclass} deviceUp'>$tis</div>";
			}

			$result = "<div class='$fclass monitor_device_frame'><a class='pic hyperLink' href='" . html_escape($host['anchor']) . "'><i id=" . $host['id'] . " class='$iclass " . $host['iclass'] . "'></i><br><span class='{$fclass}_title'>" . title_trim(html_escape($host['description']), $maxlen) . "</span>$monitor_time_html</a></div>";
		}
	}

	return $result;
}

/**
 * Resolve icon class for host status and configured monitor icon.
 *
 * @param int    $status Current monitor status.
 * @param string $icon   Configured icon key.
 *
 * @return string
 */
function getStatusIcon(int $status, string $icon): string {
	global $fa_icons;

	if (($status == 1 || ($status == 4 && get_request_var('status') > 0)) && read_user_setting('monitor_sound') == 'First Orders Suite.mp3') {
		return 'fab fa-first-order fa-spin mon_icon';
	}

	if ($icon != '' && array_key_exists($icon, $fa_icons)) {
		if (isset($fa_icons[$icon]['class'])) {
			return $fa_icons[$icon]['class'] . ' mon_icon';
		} else {
			return "fa fa-$icon mon_icon";
		}
	} else {
		return 'fa fa-server' . ' mon_icon';
	}
}

/**
 * Convert uptime/fail timestamp into compact human-readable duration text.
 *
 * @param string|int $status_time Timestamp string or SNMP uptime ticks.
 * @param bool       $seconds     Include seconds in output string.
 *
 * @return string
 */
function monitorPrintHostTime(int|string $status_time, bool $seconds = false): string {
	// If the host is down, make a downtime since message
	$dt   = '';

	if (is_numeric($status_time)) {
		$sfd  = round($status_time / 100, 0);
	} else {
		$sfd  = time() - strtotime($status_time);
	}
	$dt_d = floor($sfd / 86400);
	$dt_h = floor(($sfd - ($dt_d * 86400)) / 3600);
	$dt_m = floor(($sfd - ($dt_d * 86400) - ($dt_h * 3600)) / 60);
	$dt_s = $sfd - ($dt_d * 86400) - ($dt_h * 3600) - ($dt_m * 60);

	if ($dt_d > 0) {
		$dt .= $dt_d . 'd:' . $dt_h . 'h:' . $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's' : '');
	} elseif ($dt_h > 0) {
		$dt .= $dt_h . 'h:' . $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's' : '');
	} elseif ($dt_m > 0) {
		$dt .= $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's' : '');
	} else {
		$dt .= ($seconds ? $dt_s . 's' : __('Just Up', 'monitor'));
	}

	return $dt;
}

/**
 * Trim monitor text fields for quote and whitespace artifacts.
 *
 * @param string $string Input string.
 *
 * @return string
 */
function monitorTrim(string $string): string {
	return trim($string, "\"'\\ \n\t\r");
}

/**
 * Render wrapper header for default/tile monitor views.
 *
 * @return string
 */
function renderHeaderDefault(): string {
	return "<div class='monitorTable monitor'><div class='monitor_container'>";
}

/**
 * Render wrapper header for names view table.
 *
 * @return string
 */
function renderHeaderNames(): string {
	return "<table class='monitorTable monitor'>";
}

/**
 * Render wrapper header for icon tile view.
 *
 * @return string
 */
function renderHeaderTiles(): string {
	return renderHeaderDefault();
}

/**
 * Render wrapper header for advanced tile view.
 *
 * @return string
 */
function renderHeaderTilesadt(): string {
	return renderHeaderDefault();
}

/**
 * Render header and sortable column bar for list view.
 *
 * @param int $total_rows Total rows matching active filters.
 * @param int $rows       Page row limit.
 *
 * @return string
 */
function renderHeaderList(int $total_rows = 0, int $rows = 0): string {
	$display_text = [
		'hostname' => [
			'display' => __('Hostname', 'monitor'),
			'sort'    => 'ASC',
			'align'   => 'left', 'tip' => __('Hostname of device', 'monitor')
		],
		'id' => [
			'display' => __('ID', 'monitor'),
			'sort'    => 'ASC',
			'align'   => 'left'
		],
		'description' => [
			'display' => __('Description', 'monitor'),
			'sort'    => 'ASC',
			'align'   => 'left'
		],
		'site_name' => [
			'display' => __('Site', 'monitor'),
			'sort'    => 'ASC',
			'align'   => 'left'
		],
		'monitor_criticality' => [
			'display' => __('Criticality', 'monitor'),
			'sort'    => 'ASC',
			'align'   => 'left'
		],
		'status' => [
			'display' => __('Status', 'monitor'),
			'sort'    => 'DESC',
			'align'   => 'center'
		],
		'instate' => [
			'display' => __('Length in Status', 'monitor'),
			'sort'    => 'ASC',
			'align'   => 'center'
		],
		'avg_time' => [
			'display' => __('Averages', 'monitor'),
			'sort'    => 'DESC',
			'align'   => 'left'
		],
		'monitor_warn' => [
			'display' => __('Warning', 'monitor'),
			'sort'    => 'DESC',
			'align'   => 'left'
		],
		'monitor_text' => [
			'display' => __('Admin', 'monitor'),
			'sort'    => 'ASC',
			'tip'     => __('Monitor Text Column represents \'Admin\'', 'monitor'),
			'align'   => 'left'
		],
		'notes' => [
			'display' => __('Notes', 'monitor'),
			'sort'    => 'ASC',
			'align'   => 'left'
		],
		'availability' => [
			'display' => __('Availability', 'monitor'),
			'sort'    => 'DESC',
			'align'   => 'right'
		],
		'status_fail_date' => [
			'display' => __('Last Fail', 'monitor'),
			'sort'    => 'DESC',
			'align'   => 'right'
		],
	];

	ob_start();

	$nav = html_nav_bar('monitor.php?rfilter=' . get_request_var('rfilter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 12, __('Devices'), 'page', 'main');

	html_start_box(__('Monitored Devices', 'monitor'), '100%', false, '3', 'center', '');

	print $nav;

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$output = ob_get_contents();

	ob_end_clean();

	return $output;
}

/**
 * Indicate whether grouped section headers are suppressed for list view.
 *
 * @return bool
 */
function renderSuppressgroupsList(): bool {
	return true;
}

/**
 * Render wrapper footer for default/tile monitor views.
 *
 * @return string
 */
function renderFooterDefault(): string {
	return '</div></div>';
}

/**
 * Render footer row for names view including trailing empty cells.
 *
 * @return string
 */
function renderFooterNames(): string {
	$col = 7 - $_SESSION['names'];

	if ($col == 0) {
		return '</tr></table>';
	} else {
		return '<td colspan="' . $col . '"></td></tr></table>';
	}
}

/**
 * Render wrapper footer for icon tile view.
 *
 * @return string
 */
function renderFooterTiles(): string {
	return renderFooterDefault();
}

/**
 * Render wrapper footer for advanced tile view.
 *
 * @return string
 */
function renderFooterTilesadt(): string {
	return renderFooterDefault();
}

/**
 * Render list view footer and bottom pager.
 *
 * @param int $total_rows Total rows matching active filters.
 * @param int $rows       Page row limit.
 *
 * @return string
 */
function renderFooterList(int $total_rows, int $rows): string {
	ob_start();

	html_end_box(false);

	if ($total_rows > 0) {
		$nav = html_nav_bar('monitor.php?rfilter=' . get_request_var('rfilter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 12, __('Devices'), 'page', 'main');

		print $nav;
	}

	$output = ob_get_contents();

	ob_end_clean();

	return $output;
}

/**
 * Render one host row in list view.
 *
 * @param array $host Host row data.
 *
 * @return string
 */
function renderHostList(array $host): string {
	global $criticalities, $iclasses;

	if ($host['status'] < 2 || $host['status'] == 5) {
		$dt = get_timeinstate($host);
	} elseif (strtotime($host['status_rec_date']) > 192800) {
		$dt = get_timeinstate($host);
	} else {
		$dt = __('Never', 'monitor');
	}

	if ($host['status'] < 3 || $host['status'] == 5) {
		$host_admin = $host['monitor_text'];
	} else {
		$host_admin = '';
	}

	if (isset($host['monitor_criticality']) && $host['monitor_criticality'] > 0) {
		$host_crit = $criticalities[$host['monitor_criticality']];
	} else {
		$host_crit = '';
	}

	if ($host['availability_method'] > 0) {
		$host_avg     =	__('%d ms', $host['cur_time'], 'monitor') . ' / ' . __('%d ms', $host['avg_time'], 'monitor');
	} else {
		$host_avg     = __('N/A', 'monitor');
	}

	if (isset($host['monitor_warn']) && ($host['monitor_warn'] > 0 || $host['monitor_alert'] > 0)) {
		$host_warn = __('%0.2d ms', $host['monitor_warn'], 'monitor') . ' / ' . __('%0.2d ms', $host['monitor_alert'], 'monitor');
	} else {
		$host_warn = '';
	}

	if (strtotime($host['status_fail_date']) < 86400) {
		$host['status_fail_date'] = __('Never', 'monitor');
	}

	$host_datefail = $host['status_fail_date'];

	$iclass   = $iclasses[$host['status']];
	$sdisplay = getHostStatusDescription($host['real_status']);

	$row_class = "{$iclass}Full";

	ob_start();

	print "<tr class='tableRow line{$host['id']} selectable $row_class'>";

	$url = $host['anchor'];

	form_selectable_cell(filter_value($host['hostname'], '', $url), $host['id'], '', 'left');
	form_selectable_cell($host['id'], $host['id'], '', 'left');
	form_selectable_cell($host['description'], $host['id'], '', 'left');
	form_selectable_cell($host['site_name'], $host['id'], '', 'left');
	form_selectable_cell($host_crit, $host['id'], '', 'left');
	form_selectable_cell($sdisplay, $host['id'], '', 'center');
	form_selectable_cell($dt, $host['id'], '', 'center');
	form_selectable_cell($host_avg, $host['id'], '', 'left');
	form_selectable_cell($host_warn, $host['id'], '', 'left');
	form_selectable_cell($host_admin, $host['id'], '', 'white-space:pre-wrap;text-align:left');
	form_selectable_cell(str_replace(["\n", "\r"], [' ', ''], $host['notes']), $host['id'], '', 'white-space:pre-wrap;text-align:left');
	form_selectable_cell(round($host['availability'], 2) . ' %', $host['id'], '', 'right');
	form_selectable_cell($host_datefail, $host['id'], '', 'right');

	form_end_row();

	$result = ob_get_contents();

	ob_end_clean();

	return $result;
}

/**
 * Render one host cell in names view grid.
 *
 * @param array $host Host row data.
 *
 * @return string
 */
function renderHostNames(array $host): string {
	$fclass = get_request_var('size');

	$result = '';

	$maxlen            = getMonitorTrimLength(100);

	if ($_SESSION['names'] == 0) {
		$result .= '<tr>';
	}

	if ($host['status'] <= 2 || $host['status'] == 5) {
		$result .= "<td class='{$fclass}_names flash'><a class='hyperLink' href='" . html_escape($host['anchor']) . "'><span class='{$fclass} deviceDown '>" . title_trim(html_escape($host['description']), $maxlen) . '</span></a></td>';
	} else {
		$result .= "<td class='{$fclass}_names'><a class='hyperLink' href='" . html_escape($host['anchor']) . "'><span class='{$fclass}'>" . title_trim(html_escape($host['description']), $maxlen) . '</span></a></td>';
	}

	$_SESSION['names']++;

	if ($_SESSION['names'] > 7) {
		$result .= '</tr>';
		$_SESSION['names'] = 0;
	}

	return $result;
}

/**
 * Render one host icon tile.
 *
 * @param array $host Host row data.
 *
 * @return string
 */
function renderHostTiles(array $host): string {
	$class  = getStatusIcon($host['status'], $host['monitor_icon']);
	$fclass = get_request_var('size');

	return "<div class='{$fclass}_tiles monitor_device_frame'><a class='pic hyperLink textSubHeaderDark' href='" . html_escape($host['anchor']) . "'><i id='" . $host['id'] . "' class='$class " . $host['iclass'] . "'></i></a></div>";
}

/**
 * Render one advanced host tile including time-in-state/uptime text.
 *
 * @param array $host Host row data.
 *
 * @return string
 */
function renderHostTilesadt(array $host): string {
	$tis = '';

	$class  = getStatusIcon($host['status'], $host['monitor_icon']);
	$fclass = get_request_var('size');

	if ($host['status'] < 2 || $host['status'] == 5) {
		$tis = get_timeinstate($host);

		return "<div class='{$fclass}_tilesadt monitor_device_frame'><a class='pic hyperLink textSubHeaderDark' href='" . html_escape($host['anchor']) . "'><i id='" . $host['id'] . "' class='$class " . $host['iclass'] . "'></i><br><span class='monitor_device_{$fclass} deviceDown'>$tis</span></a></div>";
	} else {
		$tis = get_uptime($host);

		return "<div class='{$fclass}_tilesadt monitor_device_frame'><a class='pic hyperLink textSubHeaderDark' href='" . html_escape($host['anchor']) . "'><i id='" . $host['id'] . "' class='$class " . $host['iclass'] . "'></i><br><span class='monitor_device_{$fclass} deviceUp'>$tis</span></a></div>";
	}
}

/**
 * Apply monitor trim setting to a computed source field length.
 *
 * @param int $fieldlen Initial field length.
 *
 * @return int
 */
function getMonitorTrimLength(int $fieldlen): int {
	global $maxchars;

	if (get_request_var('view') == 'default' || get_request_var('view') == 'names') {
		$maxlen = $maxchars;

		if (get_request_var('trim') < 0) {
			$maxlen = 4000;
		} elseif (get_request_var('trim') > 0) {
			$maxlen = get_request_var('trim');
		}

		if ($fieldlen > $maxlen) {
			$fieldlen = $maxlen;
		}
	}

	return $fieldlen;
}
