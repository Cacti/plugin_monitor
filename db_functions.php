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

function getTholdWhere() {
	if (get_request_var('status') == '2') { // breached
		return "(td.thold_enabled = 'on'
			AND (td.thold_alert != 0 OR td.bl_alert > 0))";
	} else { // triggered
		return "(td.thold_enabled='on'
			AND ((td.thold_alert != 0 AND td.thold_fail_count >= td.thold_fail_trigger)
			OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger)))";
	}
}

function checkTholds() {
	$thold_hosts  = [];

	if (api_plugin_is_enabled('thold')) {
		return array_rekey(
			db_fetch_assoc('SELECT DISTINCT dl.host_id
				FROM thold_data AS td
				INNER JOIN data_local AS dl
				ON td.local_data_id=dl.id
				WHERE ' . getTholdWhere()),
			'host_id', 'host_id'
		);
	}

	return $thold_hosts;
}


function renderGroupConcat(&$sql_where, $sql_join, $sql_field, $sql_data, $sql_suffix = '') {
	// Remove empty entries if something was returned
	if (!empty($sql_data)) {
		$sql_data = trim(str_replace(',,',',',$sql_data), ',');

		if (!empty($sql_data)) {
			$sql_where .= ($sql_where != '' ? $sql_join : '') . "($sql_field IN($sql_data) $sql_suffix)";
		}
	}
}

function renderWhereJoin(&$sql_where, &$sql_join) {
	if (get_request_var('crit') > 0) {
		$awhere = 'h.monitor_criticality >= ' . get_request_var('crit');
	} else {
		$awhere = '';
	}

	if (get_request_var('grouping') == 'site') {
		if (get_request_var('site') > 0) {
			$awhere .= ($awhere == '' ? '' : ' AND ') . 'h.site_id = ' . get_request_var('site');
		} elseif (get_request_var('site') == -2) {
			$awhere .= ($awhere == '' ? '' : ' AND ') . ' h.site_id = 0';
		}
	}

	if (get_request_var('rfilter') != '') {
		$awhere .= ($awhere == '' ? '' : ' AND ') . " h.description RLIKE '" . get_request_var('rfilter') . "'";
	}

	if (get_request_var('grouping') == 'tree') {
		if (get_request_var('tree') > 0) {
			$hlist = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT host_id)
				FROM graph_tree_items AS gti
				INNER JOIN host AS h
				ON h.id = gti.host_id
				WHERE host_id > 0
				AND graph_tree_id = ?
				AND h.deleted = ""',
				[get_request_var('tree')]);

			renderGroupConcat($awhere, ' AND ', 'h.id', $hlist);
		} elseif (get_request_var('tree') == -2) {
			$hlist = db_fetch_cell('SELECT GROUP_CONCAT(DISTINCT h.id)
				FROM host AS h
				LEFT JOIN (SELECT DISTINCT host_id FROM graph_tree_items WHERE host_id > 0) AS gti
				ON h.id = gti.host_id
				WHERE gti.host_id IS NULL
				AND h.deleted = ""');

			renderGroupConcat($awhere, ' AND ', 'h.id', $hlist);
		}
	}

	if (!empty($awhere)) {
		$awhere = ' AND ' . $awhere;
	}

	if (get_request_var('status') == '0') {
		$sql_join  = '';
		$sql_where = 'WHERE h.disabled = ""
			AND h.monitor = "on"
			AND h.status < 3
			AND h.deleted = ""
			AND (h.availability_method > 0
				OR h.snmp_version > 0
				OR (h.cur_time >= h.monitor_warn AND monitor_warn > 0)
				OR (h.cur_time >= h.monitor_alert AND h.monitor_alert > 0)
			)' . $awhere;
	} elseif (get_request_var('status') == '1' || get_request_var('status') == 2) {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';

		$sql_where = 'WHERE h.disabled = ""
			AND h.monitor = "on"
			AND h.deleted = ""
			AND (h.status < 3
			OR ' . getTholdWhere() . '
			OR ((h.availability_method > 0 OR h.snmp_version > 0)
				AND ((h.cur_time > h.monitor_warn AND h.monitor_warn > 0)
				OR (h.cur_time > h.monitor_alert AND h.monitor_alert > 0))
			))' . $awhere;
	} elseif (get_request_var('status') == -1) {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';

		$sql_where = 'WHERE h.disabled = ""
			AND h.monitor = "on"
			AND h.deleted = ""
			AND (h.availability_method > 0 OR h.snmp_version > 0
				OR ((td.thold_enabled="on" AND td.thold_alert > 0)
				OR td.id IS NULL)
			)' . $awhere;
	} elseif (get_request_var('status') == -2) {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';

		$sql_where = 'WHERE h.disabled = ""
			AND h.deleted = ""' . $awhere;
	} elseif (get_request_var('status') == -3) {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';

		$sql_where = 'WHERE h.disabled = ""
			AND h.monitor = ""
			AND h.deleted = "")' . $awhere;
	} else {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';

		$sql_where = 'WHERE (h.disabled = ""
			AND h.deleted = ""
			AND td.id IS NULL)' . $awhere;
	}
}

// Render functions

function getHostsDownOrTriggeredByPermission($prescan) {
	global $render_style;
	$PreScanValue = 2;

	if ($prescan) {
		$PreScanValue = 3;
	}

	$result = [];

	if (get_request_var('crit') > 0) {
		$sql_add_where = 'monitor_criticality >= ' . get_request_var('crit');
	} else {
		$sql_add_where = '';
	}

	if (get_request_var('grouping') == 'tree') {
		if (get_request_var('tree') > 0) {
			$devices = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT host_id) AS hosts
				FROM graph_tree_items AS gti
				INNER JOIN host AS h
				WHERE host_id > 0
				AND h.deleted = ""
				AND graph_tree_id = ?',
				[get_request_var('tree')]);

			renderGroupConcat($sql_add_where, ' OR ', 'h.id', $devices,'AND h.status < 2');
		}
	}

	if (get_request_var('status') > 0) {
		$triggered = db_fetch_cell('SELECT GROUP_CONCAT(DISTINCT host_id) AS hosts
			FROM host AS h
			INNER JOIN thold_data AS td
			ON td.host_id = h.id
			WHERE ' . getTholdWhere() . '
			AND h.deleted = ""');

		renderGroupConcat($sql_add_where, ' OR ', 'h.id', $triggered, 'AND h.status > 1');

		$_SESSION['monitor_triggered'] = array_rekey(
			db_fetch_assoc('SELECT td.host_id, COUNT(DISTINCT td.id) AS triggered
				FROM thold_data AS td
				INNER JOIN host AS h
				ON td.host_id = h.id
				WHERE ' . getTholdWhere() . '
				AND h.deleted = ""
				GROUP BY td.host_id'),
			'host_id', 'triggered'
		);
	}

	$sql_where = "h.monitor = 'on'
		AND h.disabled = ''
		AND h.deleted = ''
		AND ((h.status < " . $PreScanValue . ' AND (h.availability_method > 0 OR h.snmp_version > 0)) ' .
		($sql_add_where != '' ? ' OR (' . $sql_add_where . '))' : ')');

	// do a quick loop through to pull the hosts that are down
	$hosts = get_allowed_devices($sql_where);

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			$result[] = $host['id'];
			sort($result);
		}
	}

	return $result;
}

/*
// This function is not used and contains an undefined variable

function getHostTreeArray() {
	return $leafs;
}
*/

function getHostNonTreeArray() {
	$leafs = [];

	$sql_where = '';
	$sql_join  = '';

	renderWhereJoin($sql_where, $sql_join);

	$hierarchy = db_fetch_assoc("SELECT DISTINCT
		h.*, gti.title, gti.host_id, gti.host_grouping_type, gti.graph_tree_id
		FROM host AS h
		LEFT JOIN graph_tree_items AS gti
		ON h.id=gti.host_id
		$sql_join
		$sql_where
		AND gti.graph_tree_id IS NULL
		ORDER BY h.description");

	if (cacti_sizeof($hierarchy) > 0) {
		$leafs       = [];
		$branchleafs = 0;

		foreach ($hierarchy as $leaf) {
			$leafs[$branchleafs] = $leaf;
			$branchleafs++;
		}
	}

	return $leafs;
}

