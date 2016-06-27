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

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

$criticalities = array(
	0 => __('Disabled'),
	1 => __('Low'),
	2 => __('Medium'),
	3 => __('High'),
	4 => __('Mission Critical')
);

$iclasses = array(
	0 => 'deviceError',
	1 => 'deviceDown',
	2 => 'deviceRecovering',
	3 => 'deviceUp',
	4 => 'deviceThrehold',
	5 => 'deviceDownMuted',
	6 => 'deviceUnmonitored',
	7 => 'deviceWarning',
	8 => 'deviceAlert',
);

$icolors = array(
	0 => 'red', 
	1 => 'red', 
	2 => 'blue', 
	3 => 'green', 
	4 => 'orange', 
	5 => 'grey',
	6 => 'grey',
	7 => 'yellow',
	8 => 'maroon'
);

$icolorsdisplay = array(
	0 => __('Unknown'), 
	1 => __('Down'),
	2 => __('Recovering'), 
	3 => __('Up'), 
	4 => __('Threshold Breached'), 
	5 => __('Down (Muted)'),
	6 => __('Unmonitored'),
	7 => __('Warning Ping Threshold'),
	8 => __('Alert Ping Threshold'),
);

$iconsizes = array(
	10 => __('Extra Small'),
	20 => __('Small'),
	40 => __('Medium'),
	80 => __('Large')
);

validate_request_vars();

if (in_array('thold',$plugins)) {
	$thold_alerts = array();
	$thold_hosts = array();

	$result = db_fetch_assoc('SELECT rra_id FROM thold_data WHERE thold_alert > 0 AND thold_enabled = "on"', FALSE);

	if (count($result)) {
		foreach ($result as $row) {
			$thold_alerts[] = $row['rra_id'];
		}

		if (count($thold_alerts) > 0) {
			$result = db_fetch_assoc('SELECT id, host_id FROM data_local');

			foreach ($result as $h) {
				if (in_array($h['id'], $thold_alerts)) {
					$thold_hosts[] = $h['host_id'];
				}
			}
		}
	}
}

$thold = (in_array('thold',$plugins) ? true : false);

// Default = default
$muted_hosts = array();
if (isset($_SESSION['muted_hosts'])) {
	$muted_hosts = explode(',',$_SESSION['muted_hosts']);
}

if (isset_request_var('action')) {
	switch(get_nfilter_request_var('action')) {
	case 'ajax_status':
		ajax_status();
		break;
	case 'save':
		save_settings();
		break;
	}

	exit;
}

/* Record Start Time */
list($micro,$seconds) = split(" ", microtime());
$start = $seconds + $micro;

$sound = true;
// Check to see if we just turned on/off the sound via the button
if (isset_request_var('sound')) {
	if (get_nfilter_request_var('sound') == 'off') {
		$_SESSION['sound'] = 'off';
		$sound = false;
	} else {
		$_SESSION['sound'] = 'on';
		$sound = true;
	}
}

// Check to see if we turned off the sound before
if (isset($_SESSION['sound']) && $_SESSION['sound'] == 'off' && $_SESSION['sound'] != '') {
	$sound = false;
}

// Check to see if a host is down
$host_down = false;
$dhosts = array();
$chosts = array();

$chosts = get_host_down_by_permission();

if (sizeof($chosts) > 0){
	$host_down = true;
}

if (!$host_down) {
	$sound = true;
	$_SESSION['sound'] = 'on';
	$_SESSION['hosts_down'] = '';
	$_SESSION['muted_hosts'] = '';
} else {
	// Check the session to see if we had any down hosts before
	if (isset($_SESSION['hosts_down'])) {
		$dhosts = explode(',',$_SESSION['hosts_down']);
		$x = count($dhosts);
		$y = count($chosts);

		if (!$sound) {
			$muted_hosts = $dhosts;
			$_SESSION['muted_hosts'] = implode(',',$dhosts);
		}

		if ($x != $y && $x < $y) {
			// We have more down hosts than before
			$sound = true;
			$_SESSION['sound'] = 'on';
			$_SESSION['hosts_down'] = implode(',',$chosts);
		} elseif ($x > $y) {
			// We have less down hosts than before
			// Need to check here to make sure that one didn't come on line and others go off
			$_SESSION['hosts_down'] = implode(',',$chosts);
		} else {
			// We have the same number of hosts, so loop through and make sure they are the same ones
			// These arrays are already sorted, so we don't need to worry about doing a real compare
			for ($a = 0; $a < $x; $a++) {
				if ($dhosts[$a] != $chosts[$a]) {
					$sound = true;
					$_SESSION['sound'] = 'on';
					$_SESSION['hosts_down'] = implode(',',$chosts);
					break;
				}
			}
		}
	} else {
		$_SESSION['hosts_down'] = implode(',',$chosts);
	}
}
$_SESSION['custom']=false;

general_header();

print '<center><form><table><tr><td>' . "\n";

print '<td><select id="view" title="' . __('View Type') . '">' . "\n";
print '<option value="default"' . (get_nfilter_request_var('view') == 'default' ? ' selected':'') . '>' . __('Default') . '</option>';
print '<option value="tiles"' . (get_nfilter_request_var('view') == 'tiles' ? ' selected':'') . '>' . __('Tiles') . '</option>';
print '<option value="tilesadt"' . (get_nfilter_request_var('view') == 'tilesadt' ? ' selected':'') . '>' . __('Tiles & Downtime') . '</option>';
print '</select></td>' . "\n";

print '<td><select id="grouping" title="' . __('Device Grouping') . '">' . "\n";
print '<option value="default"' . (get_nfilter_request_var('grouping') == 'default' ? ' selected':'') . '>' . __('Default') . '</option>';
print '<option value="tree"' . (get_nfilter_request_var('grouping') == 'tree' ? ' selected':'') . '>' . __('Tree') . '</option>';
print '<option value="template"' . (get_nfilter_request_var('grouping') == 'template' ? ' selected':'') . '>' . __('Device Template') . '</option>';
print '</select></td>' . "\n";

if (get_request_var('grouping') == 'tree') {
	$trees = get_allowed_trees();
	if (sizeof($trees)) {
		print '<td><select id="tree" title="' . __('Select Tree') . '">' . "\n";
		print '<option value="-1"' . (get_nfilter_request_var('tree') == '-1' ? ' selected':'') . '>' . __('All Trees') . '</option>';
		foreach($trees as $tree) {
			print "<option value='" . $tree['id'] . "'" . (get_nfilter_request_var('tree') == $tree['id'] ? ' selected':'') . '>' . $tree['name'] . '</option>';
		}
		print '<option value="-2"' . (get_nfilter_request_var('tree') == '-2' ? ' selected':'') . '>' . __('Non Tree Hosts') . '</option>';
		print '</select></td>' . "\n";
	}else{
		print "<input type='hidden' id='tree' value='" . get_request_var('tree') . "'>\n";
	}
}else{
	print "<input type='hidden' id='tree' value='" . get_request_var('tree') . "'>\n";
}

print '<td><select id="refresh" title="' . __('Refresh Frequency') . '">' . "\n";
foreach($page_refresh_interval as $id => $value) {
	print "<option value='$id'" . (get_nfilter_request_var('refresh') == $id ? ' selected':'') . '>' . $value . '</option>';
}
print '</select></td>' . "\n";

$critical_hosts = db_fetch_cell('SELECT count(*) FROM host WHERE monitor_criticality > 0');
if ($critical_hosts) {
	print '<td><select id="crit" title="' . __('Select Minimum Criticality') . '">' . "\n";
	print '<option value="-1"' . (get_nfilter_request_var('crit') == '-1' ? ' selected':'') . '>' . __('All Criticalities') . '</option>';
	foreach($criticalities as $key => $value) {
		if ($key > 0) {
			print "<option value='" . $key . "'" . (get_nfilter_request_var('crit') == $key ? ' selected':'') . '>' . $value . '</option>';
		}
	}
	print '</select></td>' . "\n";
}else{
	print "<input type='hidden' id='crit' value='" . get_request_var('crit') . "'>\n";
}

print '<td><select id="size" title="' . __('Device Icon Size') . '">' . "\n";
foreach($iconsizes as $id => $value) {
	print "<option value='$id'" . (get_nfilter_request_var('size') == $id ? ' selected':'') . '>' . $value . '</option>';
}
print '</select></td>' . "\n";

print '<td><select id="status" title="' . __('Device Status') . '">' . "\n";
print '<option value="-1"' . (get_nfilter_request_var('status') == '-1' ? ' selected':'') . '>' . __('All Monitored Devices') . '</option>';
print '<option value="0"' . (get_nfilter_request_var('status') == '0' ? ' selected':'') . '>' . __('Down Only') . '</option>';
print '<option value="1"' . (get_nfilter_request_var('status') == '1' ? ' selected':'') . '>' . __('Down or Triggered') . '</option>';
print '</select></td>' . "\n";

print '<td><input type="button" value="' . __('Refresh') . '" id="go" title="' . __('Refresh the Device List') . '"></td>' . "\n";
print '<td><input type="button" value="' . __('Save') . '" id="save" title="' . __('Save Filter Settings') . '"></td>' . "\n";
print '<td><input type="button" value="' . (get_request_var('mute') == 'false' ? __('Mute'):__('Un-mute')) . '" id="sound" title="' . (get_request_var('mute') == 'false' ? __('Mute Sounds for Newly Downed Devices'):__('Un-mute Sounds for Newly Downed Devices')) . '"></td>' . "\n";
print '<td><input id="mute" type="hidden" value="' . get_request_var('mute') . '">' . "\n";

print '</tr></table>' . "\n";
print '</form></center>' . "\n";

// Display the Current Time
print '<center style="padding-bottom:4px;"><span id="text" style="display:none;">Filter Settings Saved</span><br></center>';
print '<center style="padding-bottom:4px;">' . __('Last Refresh : %s', date('g:i:s a', time())) . (get_request_var('refresh') < 99999 ? ', ' . __('Refresh Again in <i id="timer">%d</i> Seconds', get_request_var('refresh')):'') . '</center>';
print '<center style="padding-bottom:4px;font-weight:bold;">' . get_filter_text() . '</center>';

if (!$sound && $host_down) {
	print '<br><center><b>' . __('Alerting has been disabled by the client!') . '</b></center>';
}

print "<center>\n";

// Default with permissions = default_by_permissions
// Tree  = group_by_tree
$function = 'render_' . get_request_var('grouping');
if (function_exists($function)) {
	print $function();
}else{
	print render_default();
}

print '</center>';

if ($host_down) {
	$render_down_host_message = 0;
	$down_host_message = '';
	$down_host_message .= '<br><br><center><h2>' . __('Down Device Messages') . '</h2><table class="center" style="background-color:black;"><tr><td><table style="background-color:while;width:100%;">';

	foreach ($chosts as $id) {
		$message = db_fetch_row_prepared('SELECT hostname, description, monitor_text FROM host WHERE id = ?' , array($id));

		$message['monitor_text'] = str_replace("\n", '<br>', $message['monitor_text']);

		if ($message['monitor_text'] != '') {
			$render_down_host_message = 1;
			$down_host_message .= '<tr><td><b>' . $message['description'] . ' (' . $message['hostname'] . ')</b> - </td><td>' . $message['monitor_text'] . '</td></tr>';
		}
	}

	$down_host_message .= '</table></td></tr></table></center>';
	if ($render_down_host_message) {
		print $down_host_message;
	}
}

if (read_config_option('monitor_legend')) {
	print "<br><br><br><table class='center' style='padding:2px;background-color:#000000'><tr><td>&nbsp;<font style='color:#FFFFFF;'><b>" . __('Legend') . "</b></font></td></tr><tr><td bgcolor='#000000'>\n";
	print "<table cellspacing=10 bgcolor='#FFFFFF' id=legend>\n";
	if ($thold) {
		print "<tr align=center><td><img src='" . $config['url_path'] . "plugins/monitor/images/green.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/blue.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/orange.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/red.gif'></td></tr>\n";
		print "<tr valign=top align=center><td width='25%'>" . __('Normal') . "</td><td width='25%'>" . __('Recovering') . "</td><td width='25%'>" . __('Threshold Breached') . "</td><td width='25%'>" . __('Down') . "</td></tr>";
	} else {
		print "<tr align=center><td><img src='" . $config['url_path'] . "plugins/monitor/images/green.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/blue.gif'></td><td><img src='" . $config['url_path'] . "plugins/monitor/images/red.gif'></td></tr>\n";
		print "<tr valign=top align=center><td width='33%'>" . __('Normal') . "</td><td width='33%'>" . __('Recovering') . "</td><td width='33%'>" . __('Down') . "</td></tr>";
	}
	print "</table></td></tr></table>\n";
}

// If the host is down, we need to insert the embedded wav file
if ($host_down && $sound) {
	$monitor_sound = read_config_option('monitor_sound');
	if ($monitor_sound != '' && $monitor_sound != __('None')) {
		print "<audio loop><source src='" . $config['url_path'] . "plugins/monitor/sounds/" . $monitor_sound . "' type='auto/mpeg'></audio>\n";
	}
}

?>
<script type='text/javascript'>
var myTimer;

function timeStep() {
	value = $('#timer').html() - 1;

	if (value <= 0) {
		applyFilter();
	}else{
		$('#timer').html(value);
		myTimer = setTimeout(timeStep, 1000);
	}
}

function closeTip() {
	console.log('wtf');
	$(document).tooltip('close');
}

function applyFilter() {
	clearTimeout(myTimer);
	$('.fa-server').unbind();

	strURL  = 'monitor.php?header=false';
	strURL += '&refresh='+$('#refresh').val();
	strURL += '&grouping='+$('#grouping').val();
	strURL += '&tree='+$('#tree').val();
	strURL += '&view='+$('#view').val();
	strURL += '&crit='+$('#crit').val();
	strURL += '&size='+$('#size').val();
	strURL += '&mute='+$('#mute').val();
	strURL += '&status='+$('#status').val();
	loadPageNoHeader(strURL);
}

function saveFilter() {
    url='monitor.php?action=save' +
        '&refresh='  + $('#refresh').val() +
        '&grouping=' + $('#grouping').val() +
        '&tree='     + $('#tree').val() +
        '&view='     + $('#view').val() +
        '&crit='     + $('#crit').val() +
        '&size='     + $('#size').val() +
        '&status='   + $('#status').val();

    $.get(url, function(data) {
        $('#text').show().text('Filter Settings Saved').fadeOut(2000);
    });
}

function setupTooltips() {
}

$('#go').click(function() {
	applyFilter();
});

$('#sound').click(function() {
	if ($('#mute').val() == 'false') {
		$('#mute').val('true');
		$('#sound').val('<?php print __('Un-mute');?>');
	}else{
		$('#mute').val('false');
		$('#sound').val('<?php print __('Mute');?>');
	}

	applyFilter();
});

$('#refresh, #view, #crit, #grouping, #size, #status, #tree').change(function() {
	applyFilter();
});

$('#save').click(function() {
	saveFilter();
});

$(function() {
	$(document).tooltip({
		items: '.fa-server',
		open: function(event, ui) {
			if (typeof(event.originalEvent) == 'undefined') {
				return false;
			}

			var $id = $(ui.tooltip).attr('id');

			$('div.ui-tooltip').not('#'+ $id).remove();
		},
		close: function(event, ui) {
			ui.tooltip.hover(
			function () {
				$(this).stop(true).fadeTo(400, 1);
			},
			function() {
				$(this).fadeOut('400', function() {
					$(this).remove();
				});
			});
		},
		position: {my: "left:15 top", at: "right center"},
		content: function(callback) {
			var id = $(this).attr('id');
			$.get('monitor.php?action=ajax_status&id='+id, function(data) {
				callback(data);
			})
		}
	});

	myTimer = setTimeout(timeStep, 1000);

	$(window).resize(function() {
		$(document).tooltip('option', 'position', {my: "1eft:15 top", at: "right center"});
	});
});

</script>
<?php

bottom_footer();

exit;

function get_filter_text() {
	$filter = '';

	switch(get_request_var('status')) {
	case '-1':
		$filter = __('All Monitored Devices');
		break;
	case '0':
		$filter = __('Monitored Devices either Down or Recovering');;
		break;
	case '1':
		$filter = __('Monitored Devices either Down, Recovering, of with Breached Thresholds');;
		break;
	}

	switch(get_request_var('crit')) {
	case '0':
		$filter .= __(', and All Criticalities');
		break;
	case '1':
		$filter .= __(', and of Low Criticality or Higher');
		break;
	case '2':
		$filter .= __(', and of Medium Criticality or Higher');
		break;
	case '3':
		$filter .= __(', and of High Criticality or Higher');
		break;
	case '4':
		$filter .= __(', and of Mission Critical Status');
		break;
	}

	return $filter;
}

function save_settings() {
    validate_request_vars();

    if (sizeof($_REQUEST)) {
    foreach($_REQUEST as $var => $value) {
        switch($var) {
        case 'refresh':
            set_user_setting('monitor_refresh', get_request_var('refresh'));
            break;
        case 'grouping':
            set_user_setting('monitor_grouping', get_request_var('grouping'));
            break;
        case 'view':
            set_user_setting('monitor_view', get_request_var('view'));
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
        case 'status':
            set_user_setting('monitor_status', get_request_var('status'));
            break;
        case 'tree':
            set_user_setting('monitor_tree', get_request_var('tree'));
            break;
		}
    }
    }

    validate_request_vars(true);
}

function validate_request_vars($force = false) {
    /* ================= input validation and session storage ================= */
    $filters = array(
        'refresh' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_user_setting('monitor_refresh', read_config_option('monitor_refresh'), $force)
            ),
        'mute' => array(
            'filter' => FILTER_CALLBACK,
            'options' => array('options' => 'sanitize_search_string'),
            'default' => read_user_setting('monitor_mute', 'false', $force)
			),
        'grouping' => array(
            'filter' => FILTER_CALLBACK,
            'options' => array('options' => 'sanitize_search_string'),
            'default' => read_user_setting('monitor_grouping', read_config_option('monitor_grouping'), $force)
            ),
        'view' => array(
            'filter' => FILTER_CALLBACK,
            'options' => array('options' => 'sanitize_search_string'),
            'default' => read_user_setting('monitor_view', read_config_option('monitor_view'), $force)
			),
        'size' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_user_setting('monitor_size', '40', $force)
			),
        'crit' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_user_setting('monitor_crit', '-1', $force)
			),
        'status' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_user_setting('monitor_status', '-1', $force)
			),
        'tree' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_user_setting('monitor_tree', '-1', $force)
			)
	);

    validate_store_request_vars($filters, 'sess_monitor');
    /* ================= input validation ================= */
}

function render_where_join(&$sql_where, &$sql_join) {
	if (get_request_var('crit') > 0) {
		$crit = ' AND h.monitor_criticality>' . get_request_var('crit');
	}else{
		$crit = '';
	}
	if (get_request_var('status') == '0') {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';
		$sql_where = 'WHERE h.disabled = "" AND h.monitor = "on" AND h.status < 3 OR (td.thold_enabled="on" AND td.thold_alert>0)' . $crit;
	}elseif (get_request_var('status') == '1') {
		$sql_join  = '';
		$sql_where = 'WHERE h.disabled = "" AND h.monitor = "on" AND h.status < 3 AND (availability_method>0 || snmp_version>0)' . $crit;
	}else{
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';
		$sql_where = 'WHERE h.disabled = "" AND h.monitor = "on" AND ((availability_method>0 OR snmp_version>0) OR (td.thold_enabled="on" AND td.thold_alert>0))' . $crit;
	}
}

/* Render functions */
function render_default() {
	$result = '';

	$sql_where = '';
	$sql_join  = '';
	render_where_join($sql_where, $sql_join);

	$hosts  = db_fetch_assoc("SELECT DISTINCT h.*
		FROM host AS h
		$sql_join
		$sql_where
		ORDER BY description");

	cacti_log("SELECT * FROM host AS h $sql_join $sql_where ORDER BY description");

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			$result .= render_host($host);
		}
	}

	return $result;
}

function render_perms() {
	global $row_stripe;

	$hosts = get_allowed_devices();

	if (sizeof($hosts) > 0) {
		foreach ($host as $host) {
			$result .= render_host($host);
		}
	}

	return $result;
}

function render_template() {
	$result = '';

	$sql_where = '';
	$sql_join  = '';
	render_where_join($sql_where, $sql_join);

	$hosts  = db_fetch_assoc("SELECT DISTINCT
		h.*, ht.name AS host_template_name
		FROM host AS h
		INNER JOIN host_template AS ht
		ON h.host_template_id=ht.id
		$sql_join
		$sql_where
		ORDER BY ht.name, h.description");

	$ctemp = -1;
	$ptemp = -1;

	if (get_request_var('view') == 'tiles') {
		$offset  = 0;
		$offset2 = 0;
	}else{
		$offset  = 52;
		$offset2 = 38;
	}

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			$ctemp = $host['host_template_id'];

			if ($ctemp != $ptemp && $ptemp > 0) {
				$result .= "</td></tr></table></div></div>\n";
			}

			if ($ctemp != $ptemp) {
				$result .= "<div style='vertical-align:top;margin-left:auto;margin-right:auto;display:table-row;height:" . intval(get_request_var('size') + $offset) . "px;position:relative;padding:3px;margin:4px;'><div style='float:left;display:table-cell;'><table class='odd'><tr class='tableHeader'><th class='left'>" . $host['host_template_name'] . "</th></tr><tr><td class='center' style='height:" . intval(get_request_var('size') + $offset2) . "px;'>\n";
			}

			$result .= render_host($host, true);

			if ($ctemp != $ptemp) {
				$ptemp = $ctemp;
			}
		}

		if ($ptemp == $ctemp) {
			$result .= "</td></tr></table></div></div>\n";
		}
	}

	return $result;
}

function render_tree() {
	$result = '';

	$leafs = array();

	if (get_request_var('tree') > 0) {
		$sql_where = 'gt.id=' . get_request_var('tree');
	}else{
		$sql_where = '';
	}

	if (get_request_var('tree') != -2) {
		$tree_list = get_allowed_trees(false, false, $sql_where);
	}else{
		$tree_list = array();
	}

	if (sizeof($tree_list)) {
		$ptree = '';
		foreach($tree_list as $tree) {
			$tree_ids[$tree['id']] = $tree['id'];
		}

		$branchWhost = db_fetch_assoc("SELECT DISTINCT gti.graph_tree_id, gti.parent
			FROM graph_tree_items AS gti
			WHERE gti.host_id>0 AND gti.graph_tree_id IN (" . implode(',', $tree_ids) . ") ORDER BY gti.graph_tree_id");

		if (sizeof($branchWhost)) {
			foreach($branchWhost as $b) {
				$oid   = $b['parent'];
				$title = '';

				while (true) {
					$branch = db_fetch_row_prepared('SELECT * FROM graph_tree_items WHERE id = ?', array($b['parent']));

					if (sizeof($branch)) {
						$title = $branch['title'] . ($title != '' ? ' > ' . $title:'');
						if ($branch['parent'] == 0) {
							break;
						}else{
							$b['parent'] = $branch['parent'];
						}
					}else{
						break;
					}
				}

				$sql_where = '';
				$sql_join  = '';
				render_where_join($sql_where, $sql_join);

				$hosts = db_fetch_assoc_prepared("SELECT DISTINCT h.* 
					FROM host AS h 
					INNER JOIN graph_tree_items AS gti 
					ON h.id=gti.host_id 
					$sql_join
					$sql_where
					AND parent = ? AND h.disabled = '' AND h.monitor = 'on' AND (h.availability_method>0 OR h.snmp_version>0)", array($oid));

				if (sizeof($hosts)) {
					$tree_name = db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array($b['graph_tree_id']));
					if ($ptree != $tree_name) {
						if ($ptree != '') {
							$result .= '</div></td></tr></table></div>';
						}
						$result .= '<div style="padding:3px;margin:4px;width:100%;"><table class="odd" style="width:100%;margin-left:auto;margin-right:auto;"><tr class="tableHeader"><th>' . $tree_name . '</th></tr><tr><td><div style="width:100%">';
						$ptree = $tree_name;
					}

					$title = $title !='' ? $title:'Root Folder';

					$result .= '<div style="vertical-align:top;float:left;position:relative;height:' . intval(get_request_var('size') + 52) . 'px;padding:3px;margin:4px;white-space:nowrap;"><table class="odd"><tr class="tableHeader"><th>' . $title . '</th></tr><tr><td class="center"><div>';
					foreach($hosts as $host) {
						$result .= render_host($host);
					}

					$result .= '</div></td></tr></table></div>';
				}
			}
		}

		$result .= '</div></td></tr></table></div>';
	}

	/* begin others - lets get the monitor items that are not associated with any tree */
	if (get_request_var('tree') < 0) {
		$heirarchy = get_host_non_tree_array();
		if (sizeof($heirarchy)) {
			$result .= '<div style="padding:3px;margin:4px;width:100%;display:table;"><table class="odd" style="width:100%;margin-left:auto;margin-right:auto;"><tr class="tableHeader"><th>Non Tree Hosts</th></tr><tr><td><div style="width:100%">';
			foreach($heirarchy as $leaf) {
				$result .= render_host($leaf);
			}

			$result .= '</td></tr></table></div></div>';
		}
	}

	return $result;
}

/* Branch rendering */
function render_branch($leafs, $title = '') {
	global $render_style;
	global $row_stripe;

	$row_stripe=false;

	if ($title == '') {
		foreach ($leafs as $row) {
			/* get our proper branch title */
			$title = $row['branch_name'];
			break;
		}
	}
	if ($title == '') {
		/* Insert a default title */
		$title = 'Items';
		$title .= ' (' . sizeof($leafs) . ')';
	}
	//$branch_percentup = '%' . leafs_percentup($leafs);
	//$title .= " - $branch_percentup";

	/* select function to render here */
	$function = "render_branch_$render_style";
	if (function_exists($function)) {
		/* Call the custom render_branch_ function */
		return $function($leafs, $title);
	}else{
		return render_branch_tree($leafs, $title);
	}
}

function get_host_status($host) {
	global $muted_hosts;

	/* If the host has been muted, show the muted Icon */
	if (in_array($host['id'], $muted_hosts) && $host['status'] == 1) {
		$host['status'] = 5;
	}

	return $host['status'];
}

/*Single host  rendering */
function render_host($host, $float = true) {
	global $thold, $thold_hosts, $config, $muted_hosts, $icolorsdisplay, $icolors, $iclasses;

	//throw out tree root items
	if (array_key_exists('name', $host))  {
		return;
	}

	if ($host['id'] <= 0) {
		return;
	}

	$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];
	if ($thold) {
		if ($host['status'] == 3 && in_array($host['id'], $thold_hosts)) {
			$host['status'] = 4;
			if (file_exists($config['base_path'] . '/plugins/thold/thold_graph.php')) {
				$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php';
			} else {
				$host['anchor'] = $config['url_path'] . 'plugins/thold/graph_thold.php';
			}
		}
	}

	$host['status'] = get_host_status($host);
	$host['icolor'] = $icolors[$host['status']];
	$host['iclass'] = $iclasses[$host['status']];

	$dt = '';
	if ($host['status'] < 2 || $host['status'] == 5) {
		$dt = monitor_print_host_time($host['status_fail_date']);
	}

	$function = 'render_host_' . get_request_var('view');

	if (function_exists($function)) {
		/* Call the custom render_host_ function */
		$result = $function($host);
	}else{
		if ($host['status'] < 2 || $host['status'] == 5) {
			$result = "<div " . ($host['status'] != 3 ? 'class="flash"':'') . "' style='width:" . max(get_request_var('size'), 80) . "px;text-align:center;display:block;" . ($float ? 'float:left;':'') . "padding:3px;'><a style='width:100px;' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa fa-server " . $host['iclass'] . "' style='font-size:" . get_request_var('size') . "px;'></i><br>" . trim($host['description']) . "<br><font style='font-size:10px;padding:2px;' class='deviceDown'>$dt</font></a></div>\n";
		} else {
			$result = "<div style='width:" . max(get_request_var('size'), 80) . "px;text-align:center;display:block;" . ($float ? 'float:left;':'') . "padding:3px;;'><a style='width:100px;' href='" . $host['anchor'] . "'><i id=" . $host['id'] . " class='fa fa-server " . $host['iclass'] . "' style='font-size:" . get_request_var('size') . "px;'></i><br>" . trim($host['description']) . "</a></div>\n";
		}
	}

	return $result;
}

function monitor_print_host_time($status_time, $seconds = false) {
	// If the host is down, make a downtime since message
	$dt   = '';
	if (is_numeric($status_time)) {
		$sfd  = round($status_time / 100,0);
	}else{
		$sfd  = time() - strtotime($status_time);
	}
	$dt_d = floor($sfd/86400);
	$dt_h = floor(($sfd - ($dt_d * 86400))/3600);
	$dt_m = floor(($sfd - ($dt_d * 86400) - ($dt_h * 3600))/60);
	$dt_s = $sfd - ($dt_d * 86400) - ($dt_h * 3600) - ($dt_m * 60);

	if ($dt_d > 0 ) {
		$dt .= $dt_d . 'd:' . $dt_h . 'h:' . $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's':'');
	} else if ($dt_h > 0 ) {
		$dt .= $dt_h . 'h:' . $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's':'');
	} else if ($dt_m > 0 ) {
		$dt .= $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's':'');;
	} else {
		$dt .= ($seconds ? $dt_s . 's':__('Just Up'));
	}

	return $dt;
}

function ajax_status() {
	global $thold, $thold_hosts, $config, $muted_hosts, $icolorsdisplay, $icolors, $iclasses, $criticalities;

	if (isset_request_var('id') && get_filter_request_var('id')) {
		$id = get_request_var('id');

		$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($id));

		$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];
		if ($thold) {
			if ($host['status'] == 3 && in_array($host['id'], $thold_hosts)) {
				$host['status'] = 4;
				if (file_exists($config['base_path'] . '/plugins/thold/thold_graph.php')) {
					$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php';
				} else {
					$host['anchor'] = $config['url_path'] . 'plugins/thold/graph_thold.php';
				}
			}
		}

		if ($host['availability_method'] == 0) {
			$host['status'] = 6;
		}

		if (sizeof($host)) {
			if (api_plugin_user_realm_auth('host.php')) {
				$host_link = htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']);
			}

			// Get the number of graphs
			$graphs   = db_fetch_cell_prepared('SELECT count(*) FROM graph_local WHERE host_id = ?', array($host['id']));
			if ($graphs > 0) {
				$graph_link = htmlspecialchars($config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id']);
			}

			// Get the number of thresholds
			if (api_plugin_is_enabled('thold')) {
				$tholds     = db_fetch_cell_prepared('SELECT count(*) FROM thold_data WHERE host_id = ?', array($host['id']));
				if ($tholds > 0) {
					$thold_link = htmlspecialchars($config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=1&status=-1&host_id=' . $host['id']);
				}
			}else{
				$tholds = 0;
			}

			// Get the number of syslogs
			if (api_plugin_is_enabled('syslog') && api_plugin_user_realm_auth('syslog.php')) {
				include($config['base_path'] . '/plugins/syslog/config.php');
				include_once($config['base_path'] . '/plugins/syslog/functions.php');
				$syslog_logs = syslog_db_fetch_cell_prepared('SELECT count(*) FROM syslog_logs WHERE host = ?', array($host['hostname']));
				$syslog_host = syslog_db_fetch_cell_prepared('SELECT host_id FROM syslog_hosts WHERE host = ?', array($host['hostname']));

				if ($syslog_logs && $syslog_host) {
					$syslog_log_link = htmlspecialchars($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=alerts&host_id=' . $syslog_host);
				}
				if ($syslog_host) {
					$syslog_link = htmlspecialchars($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=syslog&host_id=' . $syslog_host);
				}
			}else{
				$syslog_logs  = 0;
				$syslog_host  = 0;
			}

			$links = '';
			if (isset($host_link)) {
				$links .= '<a class="hyperLink" href="' . $host_link . '">' . __('Edit Device') . '</a>';
			}
			if (isset($graph_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink" href="' . $graph_link . '">' . __('View Graphs') . '</a>';
			}
			if (isset($thold_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink" href="' . $thold_link . '">' . __('View Thresholds') . '</a>';
			}
			if (isset($syslog_log_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink" href="' . $syslog_log_link . '">' . __('View Syslog Alerts') . '</a>';
			}
			if (isset($syslog_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink" href="' . $syslog_link . '">' . __('View Syslog Messages') . '</a>';
			}

			$icolor   = $icolors[$host['status']];
			$iclass   = $iclasses[$host['status']];
			$sdisplay = $icolorsdisplay[$host['status']];

			print "<table class='monitorHover' style='padding:2px;margin:0px;width:overflow:hidden;max-width:400px;max-height:600px;vertical-align:top;'>
				<tr class='tableHeader'>
					<th colspan='2'>Device Status Information</th>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Device:') . "</td>
					<td style='vertical-align:top;'><a href='" . $host['anchor'] . "'><span>" . $host['description'] . "</span></a></td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Status:') . "</td>
					<td class='$iclass' style='vertical-align:top;'>$sdisplay</td>
				</tr>" . ($host['availability_method'] > 0 ? "
				<tr>
					<td style='vertical-align:top;'>" . __('IP/Hostname:') . "</td>
					<td style='vertical-align:top;'>" . $host['hostname'] . "</td>
				</tr>":"") . ($host['notes'] != '' ? "
				<tr>
					<td style='vertical-align:top;'>" . __('Notes:') . "</td>
					<td style='vertical-align:top;'>" . $host['notes'] . "</td>
				</tr>":"") . (($graphs || $syslog_logs || $syslog_host || $tholds) ? "
				<tr>
					<td style='vertical-align:top;'>" . __('Links:') . "</td>
					<td style='vertical-align:top;'>" . $links . "
				 	</td>
				</tr>":"") . ($host['availability_method'] > 0 ? "
				<tr>
					<td style='white-space:nowrap;vertical-align:top;'>" . __('Curr/Avg Ping:') . "</td>
					<td style='vertical-align:top;'>" . __('%d ms', $host['cur_time']) . ' / ' .  __('%d ms', $host['avg_time']) . "</td>
				</tr>" . (isset($host['monitor_criticality']) && $host['monitor_criticality'] > 0 ? "
				<tr>
					<td style='vertical-align:top;'>" . __('Criticality:') . "</td>
					<td style='vertical-align:top;'>" . $criticalities[$host['monitor_criticality']] . "</td>
				</tr>":"") . (isset($host['monitor_warn']) && ($host['monitor_warn'] > 0 || $host['monitor_alert'] > 0) ? "
				<tr>
					<td style='white-space:nowrap;vertical-align:top;'>" . __('Ping Warn/Alert:') . "</td>
					<td style='vertical-align:top;'>" . __('%0.2d ms', $host['monitor_warn']) . ' / ' . __('%0.2d ms', $host['monitor_alert']) . "</td>
				</tr>":"") . "
				<tr>
					<td style='vertical-align:top;'>" . __('Last Fail:') . "</td>
					<td style='vertical-align:top;'>" . $host['status_fail_date'] . "</td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Time In State:') . "</td>
					<td style='vertical-align:top;'>" . get_timeinstate($host) . "</td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Availability:') . "</td>
					<td style='vertical-align:top;'>" . round($host['availability'],2) . " %</td>
				</tr>":"") . ($host['snmp_version'] > 0 ? "
				<tr>
					<td style='vertical-align:top;'>" . __('Agent Uptime:') . "</td>
					<td style='vertical-align:top;'>" . ($host['status'] == 3 || $host['status'] == 5 ? monitor_print_host_time($host['snmp_sysUpTimeInstance']):'N/A') . "</td>
				</tr>
				<tr>
					<td style='white-space:nowrap;vertical-align:top;'>" . __('Sys Description:') . "</td>
					<td style='vertical-align:top;'>" . $host['snmp_sysDescr'] . "</td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Location:') . "</td>
					<td style='vertical-align:top;'>" . $host['snmp_sysLocation'] . "</td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>" . __('Contact:') . "</td>
					<td style='vertical-align:top;'>" . $host['snmp_sysContact'] . "</td>
				</tr>":"") . "
				</table>\n";
		}
	}
}

function render_host_tiles($host) {
	return "<div style='padding:2px;float:left;text-align:center;'><a class='textSubHeaderDark' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa fa-server " . $host['iclass'] . "' style='font-size:" . get_request_var('size') . "px;'></i></a></div>";
}

function render_host_tilesadt($host) {
	$dt = '';
	if ($host['status'] < 2 || $host['status'] == 5) {
		$dt = monitor_print_host_time($host['status_fail_date']);
		return "<div style='margin:2px;float:left;text-align:center;width:" . max(get_request_var('size'), 80) . "px;'><a class='textSubHeaderDark' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa fa-server " . $host['iclass'] . "' style='font-size:" . get_request_var('size') . "px;'></i><br><font style='font-size:10px;padding:2px;' class='deviceDown'>$dt</font></a></div>\n";
	}else{
		if ($host['status_rec_date'] != '0000-00-00 00:00:00') {
			$dt = monitor_print_host_time($host['status_rec_date']);
		}else{
			$dt = __('Never');
		}
		return "<div style='margin:2px;float:left;text-align:center;width:" . max(get_request_var('size'), 80) . "px;'><a class='textSubHeaderDark' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa fa-server " . $host['iclass'] . "' style='font-size:" . get_request_var('size') . "px;'></i><br><font style='font-size:10px;padding:2px;' class='deviceUp'>$dt</font></a></div>\n";
	}

}

function get_host_down_by_permission() {
	$result = array();

	global $render_style;
	if ($render_style == 'default') {
		$hosts = get_allowed_devices("h.monitor='on' AND h.status < 2 AND h.disabled='' AND (h.availability_method>0 OR h.snmp_version>0)");
		// do a quick loop through to pull the hosts that are down
		if (sizeof($hosts)) {
			foreach($hosts as $host) {
				$host_down = true;
				$result[] = $host['id'];
				sort($result);
			}
		}
	} else {
		/* Only get hosts */
		$hosts = get_allowed_devices("h.monitor='on' AND h.status < 2 AND h.disabled='' AND (h.availability_method>0 OR h.snmp_version>0)");
		if (sizeof($hosts) > 0) {
			foreach ($hosts as $host) {
				$host_down = true;
				$result[] = $host['id'];
				sort($result);
			}
		}
	}

	return $result;
}

function get_host_tree_array() {
	return $leafs;
}

function get_host_non_tree_array() {
	$leafs = array();

	$sql_where = '';
	$sql_join  = '';

	render_where_join($sql_where, $sql_join);

	//$sql_where .= " AND ((host.disabled = '' AND host.monitor = 'on' AND (host.availability_method>0 OR host.snmp_version>0)) OR (title != ''))";

	$heirarchy = db_fetch_assoc("SELECT DISTINCT
		gti.title, gti.host_id, gti.host_grouping_type, gti.graph_tree_id,
		h.id, h.description, h.status, h.hostname, h.cur_time, h.status_rec_date,
		h.status_fail_date, h.availability
		FROM host AS h
		LEFT JOIN graph_tree_items AS gti 
		ON h.id=gti.host_id
		$sql_join
		$sql_where
		AND gti.graph_tree_id IS NULL
		ORDER BY h.description");

	if (sizeof($heirarchy) > 0) {
		$leafs = array();
		$branchleafs = 0;
		foreach ($heirarchy as $leaf) {
			$leafs[$branchleafs] = $leaf;
			$branchleafs++;
		}
	}
	return $leafs;
}

/* Supporting functions */
function get_status_color($status=3) {
	$color = '#183C8F';
	switch ($status) {
		case 0: //error
			$color = '#993333';
			break;
		case 1: //error
			$color = '#993333';
			break;
		case 2: //recovering
			$color = '#7293B9';
			break;
		case 3: //ok
			$color = '#669966';
			break;
		case 4: //threshold
			$color = '#c56500';
			break;
		case 5: //muted
			$color = '#996666';
			break;
		default: //unknown
			$color = '#999999';
			break;
		}
	return $color;
}

function leafs_status_min($leafs) {
	global $thold;
	global $thold_hosts;
	$thold_breached = 0;
	$result = 3;
	foreach ($leafs as $row) {
		$status = intval($row['status']);
		if ($result > $status) {
			$result = $status;
		}
		if ($thold) {
			if ($status == 3 && in_array($row['id'], $thold_hosts)) {
				$thold_breached = 1;
			}
		}
	}
	if ($result == 3 && $thold_breached) {
		$result = 4;
	}
	return $result;
}

function leafs_percentup($leafs) {
	$result = 0;
	$countup = 0;
	$count = sizeof($leafs);
	foreach ($leafs as $row) {
		$status = intval($row['status']);
		if ($status >= 3) {
			$countup++;
		}
	}
	if ($countup>=$count){
		return 100;
	}
	$result = round($countup/$count*100,0);
	return $result;
}

