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

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

set_default_action();

/* Record Start Time */
list($micro,$seconds) = explode(" ", microtime());
$start = $seconds + $micro;

$criticalities = array(
	0 => __('Disabled', 'monitor'),
	1 => __('Low', 'monitor'),
	2 => __('Medium', 'monitor'),
	3 => __('High', 'monitor'),
	4 => __('Mission Critical', 'monitor')
);

$iclasses = array(
	0 => 'deviceUnknown',
	1 => 'deviceDown',
	2 => 'deviceRecovering',
	3 => 'deviceUp',
	4 => 'deviceThreshold',
	5 => 'deviceDownMuted',
	6 => 'deviceUnmonitored',
	7 => 'deviceWarning',
	8 => 'deviceAlert',
	9 => 'deviceThresholdMuted',
);

$icolorsdisplay = array(
	0 => __('Unknown', 'monitor'),
	1 => __('Down', 'monitor'),
	2 => __('Recovering', 'monitor'),
	3 => __('Up', 'monitor'),
	4 => __('Triggered', 'monitor'),
	9 => __('Triggered (Muted/Acked)', 'monitor'),
	5 => __('Down (Muted/Acked)', 'monitor'),
	6 => __('No Availability Check', 'monitor'),
	7 => __('Warning Ping', 'monitor'),
	8 => __('Alert Ping', 'monitor'),
);

$classes = array(
	'monitor_exsmall'   => __('Extra Small', 'monitor'),
	'monitor_small'     => __('Small', 'monitor'),
	'monitor_medium'    => __('Medium', 'monitor'),
	'monitor_large'     => __('Large', 'monitor'),
	'monitor_exlarge'   => __('Extra Large', 'monitor'),
	'monitor_errorzoom' => __('Zoom', 'monitor')
);

$monitor_status = array(
	-1 => __('All Monitored', 'monitor'),
	0  => __('Not Up', 'monitor'),
	1  => __('Not Up or Triggered', 'monitor'),
	2  => __('Not Up, Triggered or Breached', 'monitor')
);

$monitor_view_type = array(
	'default'  => __('Default', 'monitor'),
	'list'     => __('List', 'monitor'),
	'tiles'    => __('Tiles', 'monitor'),
	'tilesadt' => __('Tiles & Time', 'monitor')
);

$monitor_grouping = array(
	'default'  => __('Default', 'monitor'),
	'tree'     => __('Tree', 'monitor'),
	'site'     => __('Site', 'monitor'),
	'template' => __('Device Template', 'monitor')
);

$monitor_trim = array(
	0   => __('Default', 'monitor'),
	-1  => __('Full', 'monitor'),
	10  => __('10 Chars', 'monitor'),
	20  => __('20 Chars', 'monitor'),
	30  => __('30 Chars', 'monitor'),
	40  => __('40 Chars', 'monitor'),
	50  => __('50 Chars', 'monitor'),
	75  => __('75 Chars', 'monitor'),
	100 => __('100 Chars', 'monitor'),
);

global $thold_hosts, $maxchars;

$dozoomrefresh   = false;
$dozoombgndcolor = false;

$maxchars = 12;

if (!isset($_SESSION['monitor_muted_hosts'])) {
	$_SESSION['monitor_muted_hosts'] = array();
}

validate_request_vars(true);

$thold_hosts = check_tholds();

switch(get_nfilter_request_var('action')) {
	case 'ajax_status':
		ajax_status();

		break;
	case 'ajax_mute_all':
		mute_all_hosts();
		draw_page();

		break;
	case 'ajax_unmute_all':
		unmute_all_hosts();
		draw_page();

		break;
	case 'dbchange':
		load_dashboard_settings();
		draw_page();

		break;
	case 'remove':
		remove_dashboard();
		draw_page();

		break;
	case 'saveDb':
		save_settings();
		draw_page();

		break;
	case 'save':
		save_settings();

		break;
	default:
		draw_page();
}

exit;

function load_dashboard_settings() {
	$dashboard = get_filter_request_var('dashboard');

	if ($dashboard > 0) {
		$db_settings = db_fetch_cell_prepared('SELECT url
			FROM plugin_monitor_dashboards
			WHERE id = ?',
			array($dashboard));

		if ($db_settings != '') {
			$db_settings = str_replace('monitor.php?', '', $db_settings);
			$settings = explode('&', $db_settings);

			if (cacti_sizeof($settings)) {
				foreach($settings as $setting) {
					list($name, $value) = explode('=', $setting);

					set_request_var($name, $value);
				}
			}
		}
	}
}

function draw_page() {
	global $config, $iclasses, $icolorsdisplay, $mon_zoom_state, $dozoomrefresh, $dozoombgndcolor, $font_sizes;

	$errored_list = get_hosts_down_or_triggered_by_permission(true);

	if (cacti_sizeof($errored_list) && read_user_setting('monitor_error_zoom') == 'on') {
		if ($_SESSION['monitor_zoom_state'] == 0) {
			$mon_zoom_state = $_SESSION['monitor_zoom_state'] = 1;
			$_SESSION['mon_zoom_hist_status'] = get_nfilter_request_var('status');
			$_SESSION['mon_zoom_hist_size']   = get_nfilter_request_var('size');
			$dozoomrefresh   = true;
			$dozoombgndcolor = true;
		}
	} elseif (isset($_SESSION['monitor_zoom_state']) && $_SESSION['monitor_zoom_state'] == 1) {
		$_SESSION['monitor_zoom_state'] = 0;
		$dozoomrefresh   = true;
		$dozoombgndcolor = false;
	}

	find_down_hosts();

	general_header();

	draw_filter_and_status();

	print '<tr><td>';

	// Default with permissions = default_by_permission
	// Tree  = group_by_tree
	$function = 'render_' . get_request_var('grouping');

	if (function_exists($function) && get_request_var('view') != 'list') {
		if (get_request_var('grouping') == 'default' || get_request_var('grouping') == 'site') {
			html_start_box(__('Monitored Devices', 'monitor'), '100%', '', '3', 'center', '');
		} else {
			html_start_box('', '100%', '', '3', 'center', '');
		}

		print $function();
	} else {
		print render_default();
	}

	print '</td></tr>';

	html_end_box();

	if (read_user_setting('monitor_legend', read_config_option('monitor_legend'))) {
		print "<div class='center monitor_legend'>";

		foreach($iclasses as $index => $class) {
			print "<div class='monitor_legend_cell center $class" . "Bg'>" . $icolorsdisplay[$index] . "</div>";
		}

		print "</div>";
	}

	$name = db_fetch_cell_prepared('SELECT name
		FROM plugin_monitor_dashboards
		WHERE id = ?',
		array(get_request_var('dashboard')));

	if ($name == '') {
		$name = __('New Dashboard', 'monitor');
	}

	$new_form  = "<div id='newdialog'><form id='new_dashboard'><table class='monitorTable'><tr><td colspan='2'>" . __('Enter the Dashboard Name and then press \'Save\' to continue, else press \'Cancel\'', 'monitor') . '</td></tr><tr><td>' . __('Dashboard', 'monitor') . "</td><td><input id='name' class='ui-state-default ui-corner-all' type='text' size='30' value='" . html_escape($name) . "'></td></tr></table></form></div>";

	$new_title = __('Create New Dashboard', 'monitor');

	// If the host is down, we need to insert the embedded wav file
	$monitor_sound = get_monitor_sound();
	if (is_monitor_audible()) {
		if (read_user_setting('monitor_sound_loop', read_config_option('monitor_sound_loop'))) {
			print "<audio id='audio' loop src='" . html_escape($config['url_path'] . 'plugins/monitor/sounds/' . $monitor_sound) . "'></audio>";
		} else {
			print "<audio id='audio' src='" . html_escape($config['url_path'] . 'plugins/monitor/sounds/' . $monitor_sound) . "'></audio>";
		}
	}

	if ($dozoombgndcolor) {
		$mbcolora = db_fetch_row_prepared('SELECT *
			FROM colors
			WHERE id = ?',
			array(read_user_setting('monitor_error_background')));

		$monitor_error_fontsize = read_user_setting('monitor_error_fontsize') . 'px';
		$mbcolor = "";
		$mbcolor = '#' . array_values($mbcolora)[2];

		print "<script type=\"text/javascript\">
			var monoe = false;

			function setZoomErrorBackgrounds() {
				if ('$mbcolor' != '') {
					$('.monitor_container').css('background-color', '$mbcolor');
					$('.cactiConsoleContentArea').css('background-color', '$mbcolor');
				}
			};

			setZoomErrorBackgrounds();
			$('.monitor_errorzoom_title').css('font-size', '$monitor_error_fontsize');

			function setIntervalX(callback, delay, repetitions) {
				var x = 0;
				var intervalID = window.setInterval(function () {
					callback();
					if (++x === repetitions) {
						window.clearInterval(intervalID);
						setZoomErrorBackgrounds();
					}
				}, delay);
			}

			setIntervalX(function () {
				if (monoe === false) {
					setZoomErrorBackgrounds();

					monoe = true;
				} else {
					if ('$mbcolor' != '') {
						$('.monitor_container').css('background-color', '');
						$('.cactiConsoleContentArea').css('background-color','');
					}

					monoe = false;
				}
			}, 600, 8);
		</script>";
	} else {
		print "<script type=\"text/javascript\">
			$('.monitor_container').css('background-color', '');
			$('.cactiConsoleContentArea').css('background-color','');
		</script>";
	}

	?>
	<script type='text/javascript'>
	var refreshMSeconds=99999999;
	var myTimer;

	function timeStep() {
		value = $('#timer').html() - 1;

		if (value <= 0) {
			applyFilter('refresh');
		} else {
			$('#timer').html(value);
			// What is a second, well if you are an
			// emperial storm tropper, it's just a little more than a second.
			myTimer = setTimeout(timeStep, 1284);
		}
	}

	function muteUnmuteAudio(mute) {
		if (mute) {
			$('audio').each(function() {
				this.pause();
				this.currentTime = 0;
			});
		} else if ($('#downhosts').val() == 'true') {
			$('audio').each(function() {
				this.play();
			});
		}
	}

	function closeTip() {
		$(document).tooltip('close');
	}

	function applyFilter(action) {
		if (typeof action == 'undefined') {
			action = '';
		}

		clearTimeout(myTimer);
		$('.fa-server, .fa-first-order').unbind();

		if (action != 'dashboard') {
			var strURL  = 'monitor.php?header=false';

			if (action >= '') {
				strURL += '&action='+action;
			}

			strURL += '&refresh='  + $('#refresh').val();
			strURL += '&grouping=' + $('#grouping').val();
			strURL += '&tree='     + $('#tree').val();
			strURL += '&site='     + $('#site').val();
			strURL += '&template=' + $('#template').val();
			strURL += '&view='     + $('#view').val();
			strURL += '&crit='     + $('#crit').val();
			strURL += '&size='     + $('#size').val();
			strURL += '&trim='     + $('#trim').val();
			strURL += '&mute='     + $('#mute').val();
			strURL += '&rfilter='  + base64_encode($('#rfilter').val());
			strURL += '&status='   + $('#status').val();
		} else {
			strURL  = 'monitor.php?action=dbchange&header=false';
			strURL += '&dashboard=' + $('#dashboard').val();
		}

		loadPageNoHeader(strURL);
	}

	function saveFilter() {
		var url = 'monitor.php?action=save&header=false';

		var post = {
			dashboard: $('#dashboard').val(),
			refresh: $('#refresh').val(),
			grouping: $('#grouping').val(),
			tree: $('#tree').val(),
			site: $('#site').val(),
			template: $('#template').val(),
			view: $('#view').val(),
			crit: $('#crit').val(),
			rfilter: base64_encode($('#rfilter').val()),
			trim: $('#trim').val(),
			size: $('#size').val(),
			trim: $('#trim').val(),
			status: $('#status').val(),
			__csrf_magic: csrfMagicToken
		};

		$.post(url, post).done(function(data) {
			$('#text').show().text('<?php print __(' [ Filter Settings Saved ]', 'monitor');?>').fadeOut(2000);
		});
	}

	function saveNewDashboard(action) {
		if (action == 'new') {
			dashboard = '-1';
		} else {
			dashboard = $('#dashboard').val();
		}

		url = 'monitor.php?action=saveDb&header=false' +
			'&dashboard=' + dashboard +
			'&name='      + $('#name').val() +
			'&refresh='   + $('#refresh').val() +
			'&grouping='  + $('#grouping').val() +
			'&tree='      + $('#tree').val() +
			'&site='      + $('#site').val() +
			'&template='  + $('#template').val() +
			'&view='      + $('#view').val() +
			'&crit='      + $('#crit').val() +
			'&rfilter='   + base64_encode($('#rfilter').val()) +
			'&trim='      + $('#trim').val() +
			'&size='      + $('#size').val() +
			'&status='    + $('#status').val();

		loadPageNoHeader(url);
	}

	function removeDashboard() {
		url = 'monitor.php?action=remove&header=false&dashboard=' + $('#dashboard').val();
		loadPageNoHeader(url);
	}

	function saveDashboard(action) {
		var btnDialog = {
			'Cancel': {
				text: '<?php print __('Cancel', 'monitor');?>',
				id: 'btnCancel',
				click: function() {
					$(this).dialog('close');
				}
			},
			'Save': {
				text: '<?php print __('Save', 'monitor');?>',
				id: 'btnSave',
				click: function() {
					saveNewDashboard(action);
				}
			}
		};

		$('body').remove('#newdialog').append("<?php print $new_form;?>");

		$('#newdialog').dialog({
			title: '<?php print $new_title;?>',
			minHeight: 80,
			minWidth: 500,
			buttons: btnDialog,
			open: function() {
				$('#btnSave').focus();
			}
		});
	}

	$(function() {
		// Clear the timeout to keep countdown accurate
		clearTimeout(myTimer);

		$('#go').click(function() {
			applyFilter('go');
		});

		$('#sound').click(function() {
			if ($('#mute').val() == 'false') {
				$('#mute').val('true');
				muteUnmuteAudio(true);
				applyFilter('ajax_mute_all');
			} else {
				$('#mute').val('false');
				muteUnmuteAudio(false);
				applyFilter('ajax_unmute_all');
			}
		});

		$('#refresh, #view, #trim, #crit, #grouping, #size, #status, #tree, #site, #template').change(function() {
			applyFilter('change');
		});

		$('#dashboard').change(function() {
			applyFilter('dashboard');
		});

		$('#save').click(function() {
			saveFilter();
		});

		$('#new').click(function() {
			saveDashboard('new');
		});

		$('#rename').click(function() {
			saveDashboard('rename');
		});

		$('#delete').click(function() {
			removeDashboard();
		});

		$('.monitorFilterForm').submit(function(event) {
			event.preventDefault();
			applyFilter('change');
		});

		// Servers need tooltips
		$('.monitor_device_frame').find('i').tooltip({
			items: '.fa-server, .fa-first-order',
			open: function(event, ui) {
				if (typeof(event.originalEvent) == 'undefined') {
					return false;
				}

				var id = $(ui.tooltip).attr('id');

				$('div.ui-tooltip').not('#'+ id).remove();
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
				var size = $('#size').val();
				$.get('monitor.php?action=ajax_status&size='+size+'&id='+id, function(data) {
					callback(data);
				});
			}
		});

		// Start the countdown
		myTimer = setTimeout(timeStep, 1000);

		// Attempt to reposition the tooltips on resize
		$(window).resize(function() {
			$(document).tooltip('option', 'position', {my: "1eft:15 top", at: "right center"});
		});

		if ($('#mute').val() == 'true') {
			muteUnmuteAudio(true);
		} else {
			muteUnmuteAudio(false);
		}
		$('#main').css('margin-right', '15px');
	});

	</script>
	<?php

	print '<div class="center monitorFooter">' . get_filter_text() . '</div>';

	bottom_footer();
}

function is_monitor_audible() {
	return get_monitor_sound() != '';
}

function get_monitor_sound() {
	$sound = read_user_setting('monitor_sound', read_config_option('monitor_sound'));
	clearstatcache();
	$file = dirname(__FILE__) . '/sounds/' . $sound;
	$exists = file_exists($file);
	return $exists ? $sound : '';
}

function find_down_hosts() {
	$dhosts = get_hosts_down_or_triggered_by_permission(false);

	if (cacti_sizeof($dhosts)) {
		set_request_var('downhosts', 'true');

		if (isset($_SESSION['monitor_muted_hosts'])) {
			unmute_up_non_triggered_hosts($dhosts);

			$unmuted_hosts = array_diff($dhosts, $_SESSION['monitor_muted_hosts']);

			if (cacti_sizeof($unmuted_hosts)) {
				unmute_user();
			}
		} else {
			set_request_var('mute', 'false');
		}
	} else {
		unmute_all_hosts();
		set_request_var('downhosts', 'false');
	}
}

function unmute_up_non_triggered_hosts($dhosts) {
	if (isset($_SESSION['monitor_muted_hosts'])) {
		foreach($_SESSION['monitor_muted_hosts'] AS $index => $host_id) {
			if (array_search($host_id, $dhosts) === false) {
				unset($_SESSION['monitor_muted_hosts'][$index]);
			}
		}
	}
}

function mute_all_hosts() {
	$_SESSION['monitor_muted_hosts'] = get_hosts_down_or_triggered_by_permission(false);
	mute_user();
}

function unmute_all_hosts() {
	$_SESSION['monitor_muted_hosts'] = array();
	unmute_user();
}

function mute_user() {
	set_request_var('mute', 'true');
	set_user_setting('monitor_mute','true');
}

function unmute_user() {
	set_request_var('mute', 'false');
	set_user_setting('monitor_mute','false');
}

function get_thold_where() {
	if (get_request_var('status') == '2') { /* breached */
		return "(td.thold_enabled = 'on'
			AND (td.thold_alert != 0 OR td.bl_alert > 0))";
	} else { /* triggered */
		return "(td.thold_enabled='on'
			AND ((td.thold_alert != 0 AND td.thold_fail_count >= td.thold_fail_trigger)
			OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger)))";
	}
}

function check_tholds() {
	$thold_hosts  = array();

	if (api_plugin_is_enabled('thold')) {
		return array_rekey(
			db_fetch_assoc("SELECT DISTINCT dl.host_id
				FROM thold_data AS td
				INNER JOIN data_local AS dl
				ON td.local_data_id=dl.id
				WHERE " . get_thold_where()),
			'host_id', 'host_id'
		);
	}

	return $thold_hosts;
}

function get_filter_text() {
	$filter = '<div class="center monitorFooterText">';

	switch(get_request_var('status')) {
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

	switch(get_request_var('crit')) {
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

function draw_filter_dropdown($id, $title, $settings = array(), $value = null) {
	if ($value == null) {
		$value = get_nfilter_request_var($id);
	}

	if (cacti_sizeof($settings)) {
		print '<td>' . html_escape($title) . '</td>';
		print '<td><select id="' . $id . '" title="' . html_escape($title) . '">' . PHP_EOL;

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

function draw_filter_and_status() {
	global $criticalities, $page_refresh_interval, $classes, $monitor_grouping, $monitor_view_type, $monitor_status, $monitor_trim, $dozoomrefresh, $zoom_hist_status, $zoom_hist_size, $dozoombgndcolor, $mon_zoom_state;

	$header = __('Monitor Filter [ Last Refresh: %s ]', date('g:i:s a', time()), 'monitor') . (get_request_var('refresh') < 99999 ? __(' [ Refresh Again in <i id="timer">%d</i> Seconds ]', get_request_var('refresh'), 'monitor') : '') . '<span id="text" style="vertical-align:baseline;padding:0px !important;display:none"></span>';

	html_start_box($header, '100%', '', '3', 'center', '');

	print '<tr class="even"><td>' . PHP_EOL;
	print '<form class="monitorFilterForm">' . PHP_EOL;

	// First line of filter
	print '<table class="filterTable">' . PHP_EOL;
	print '<tr class="even">' . PHP_EOL;

	$dashboards[0] = __('Unsaved', 'monitor');
	$dashboards += array_rekey(
		db_fetch_assoc_prepared('SELECT id, name
			FROM plugin_monitor_dashboards
			WHERE user_id = 0 OR user_id = ?
			ORDER BY name',
			array($_SESSION['sess_user_id'])),
		'id', 'name'
	);

	$name = db_fetch_cell_prepared('SELECT name
		FROM plugin_monitor_dashboards
		WHERE id = ?',
		array(get_request_var('dashboard')));

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
			} else {
				$mon_zoom_status = null;
			}

			if (isset($_SESSION['mon_zoom_hist_size'])) {
				$currentddsize = get_nfilter_request_var('size');

				if ($currentddsize != $_SESSION['mon_zoom_hist_size'] && $currentddsize != 'monitor_errorzoom') {
					$_SESSION['mon_zoom_hist_size'] = $currentddsize;
				}

				$mon_zoom_size = $_SESSION['mon_zoom_hist_size'];
			} else {
				$mon_zoom_size = null;
			}
		}
	}

	draw_filter_dropdown('dashboard', __('Layout', 'monitor'), $dashboards);
	draw_filter_dropdown('status', __('Status', 'monitor'), $monitor_status, $mon_zoom_status);
	draw_filter_dropdown('view', __('View', 'monitor'), $monitor_view_type);
	draw_filter_dropdown('grouping', __('Grouping', 'monitor'), $monitor_grouping);

	// Buttons
	print '<td><span>' . PHP_EOL;

	print '<input type="submit" value="' . __esc('Refresh', 'monitor') . '" id="go" title="' . __esc('Refresh the Device List', 'monitor') . '">' . PHP_EOL;

	print '<input type="button" value="' . __esc('Save', 'monitor') . '" id="save" title="' . __esc('Save Filter Settings', 'monitor') . '">' . PHP_EOL;

	print '<input type="button" value="' . __esc('New', 'monitor') . '" id="new" title="' . __esc('Save New Dashboard', 'monitor') . '">' . PHP_EOL;

	if (get_request_var('dashboard') > 0) {
		print '<input type="button" value="' . __esc('Rename', 'monitor') . '" id="rename" title="' . __esc('Rename Dashboard', 'monitor') . '">' . PHP_EOL;
	}

	if (get_request_var('dashboard') > 0) {
		print '<input type="button" value="' . __esc('Delete', 'monitor') . '" id="delete" title="' . __esc('Delete Dashboard', 'monitor') . '">' . PHP_EOL;
	}

	print '<input type="button" value="' . (get_request_var('mute') == 'false' ? get_mute_text():get_unmute_text()) . '" id="sound" title="' . (get_request_var('mute') == 'false' ? __('%s Alert for downed Devices', get_mute_text(), 'monitor'):__('%s Alerts for downed Devices', get_unmute_text(), 'monitor')) . '">' . PHP_EOL;
	print '<input id="downhosts" type="hidden" value="' . get_request_var('downhosts') . '"><input id="mute" type="hidden" value="' . get_request_var('mute') . '">' . PHP_EOL;
	print '</span></td>';
	print '</tr>';
	print '</table>';

	// Second line of filter
	print '<table class="filterTable">' . PHP_EOL;
	print '<tr>' . PHP_EOL;
	print '<td>' . __('Search', 'monitor') . '</td>';
	print '<td><input type="text" size="30" id="rfilter" value="' . html_escape_request_var('rfilter') . '"></input></td>';

	draw_filter_dropdown('crit', __('Criticality', 'monitor'), $criticalities);

	if (get_request_var('view') != 'list') {
		draw_filter_dropdown('size', __('Size', 'monitor'), $classes, $mon_zoom_size);
	}

	if (get_request_var('view') == 'default') {
		draw_filter_dropdown('trim', __('Trim', 'monitor'), $monitor_trim);
	}

	if (get_nfilter_request_var('grouping') == 'tree') {
		$trees = array();
		if (get_request_var('grouping') == 'tree') {
			$trees_allowed = array_rekey(get_allowed_trees(), 'id', 'name');
			if (cacti_sizeof($trees_allowed)) {
				$trees_prefix = array(-1 => __('All Trees', 'monitor'));
				$trees_suffix = array(-2 => __('Non-Tree Devices', 'monitor'));

				$trees = $trees_prefix + $trees_allowed + $trees_suffix;
			}
		}

		draw_filter_dropdown('tree', __('Tree', 'monitor'), $trees);
	}

	if (get_nfilter_request_var('grouping') == 'site') {
		$sites = array();
		if (get_request_var('grouping') == 'site') {
			$sites = array_rekey(
				db_fetch_assoc('SELECT id, name
					FROM sites
					ORDER BY name'),
				'id', 'name'
			);

			if (cacti_sizeof($sites)) {
				$sites_prefix = array(-1 => __('All Sites', 'monitor'));
				$sites_suffix = array(-2 => __('Non-Site Devices', 'monitor'));

				$sites = $sites_prefix + $sites + $sites_suffix;
			}
		}

		draw_filter_dropdown('site', __('Sites', 'monitor'), $sites);
	}

	if (get_request_var('grouping') == 'template') {
		$templates = array();
		$templates_allowed = array_rekey(db_fetch_assoc('SELECT id, name FROM host_template'), 'id', 'name');

		if (cacti_sizeof($templates_allowed)) {
			$templates_prefix = array(-1 => __('All Templates', 'monitor'));
			$templates_suffix = array(-2 => __('Non-Templated Devices', 'monitor'));

			$templates = $templates_prefix + $templates_allowed + $templates_suffix;
		}

		draw_filter_dropdown('template', __('Template', 'monitor'), $templates);
	}

	draw_filter_dropdown('refresh', __('Refresh', 'monitor'), $page_refresh_interval);

	if (get_request_var('grouping') != 'tree') {
		print '<td><input type="hidden" id="tree" value="' . get_request_var('tree') . '"></td>' . PHP_EOL;
	}

	if (get_request_var('grouping') != 'site') {
		print '<td><input type="hidden" id="site" value="' . get_request_var('site') . '"></td>' . PHP_EOL;
	}

	if (get_request_var('grouping') != 'template') {
		print '<td><input type="hidden" id="template" value="' . get_request_var('template') . '"></td>' . PHP_EOL;
	}

	if (get_request_var('view') == 'list') {
		print '<td><input type="hidden" id="size" value="' . get_request_var('size') . '"></td>' . PHP_EOL;
	}

	if (get_request_var('view') != 'default') {
		print '<td><input type="hidden" id="trim" value="' . get_request_var('trim') . '"></td>' . PHP_EOL;
	}

	print '</tr>';
	print '</table>';
	print '</form></td></tr>' . PHP_EOL;

	html_end_box();

	if ($dozoomrefresh == true) {
		$dozoomrefresh = false;

		print "<script type=\"text/javascript\">
			applyFilter('refresh');
		</script>";
	}
}

function get_mute_text() {
	if (is_monitor_audible()) {
		return __('Mute', 'monitor');
	} else {
		return __('Acknowledge', 'monitor');
	}
}

function get_unmute_text() {
	if (is_monitor_audible()) {
		return __('Un-Mute', 'monitor');
	} else {
		return __('Reset', 'monitor');
	}
}

function remove_dashboard() {
	$dashboard = get_filter_request_var('dashboard');

	$name = db_fetch_cell_prepared('SELECT name
		FROM plugin_monitor_dashboards
		WHERE id = ?
		AND user_id = ?',
		array($dashboard, $_SESSION['sess_user_id']));

	if ($name != '') {
		db_execute_prepared('DELETE FROM plugin_monitor_dashboards
			WHERE id = ?',
			array($dashboard));

		raise_message('removed', __('Dashboard \'%s\' Removed.', $name, 'monitor'), MESSAGE_LEVEL_INFO);
	} else {
		$name = db_fetch_cell_prepared('SELECT name
			FROM plugin_monitor_dashboards
			WHERE id = ?',
			array($dashboard));

		raise_message('notremoved', __('Dashboard \'%s\' is not owned by you.', $name, 'monitor'), MESSAGE_LEVEL_ERROR);
	}

	set_request_var('dashboard', '0');
}

function save_settings() {
	if (isset_request_var('dashboard') && get_filter_request_var('dashboard') != 0) {
		$save_db = true;
	} else {
		$save_db = false;
	}

	validate_request_vars();

	if (!$save_db) {
		if (cacti_sizeof($_REQUEST)) {
			foreach($_REQUEST as $var => $value) {
				switch($var) {
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
				case 'site':
					set_user_setting('monitor_site', get_request_var('site'));
					break;
				}
			}
		}
	} else {
		$url = 'monitor.php' .
			'?refresh='   . get_request_var('refresh') .
			'&grouping='  . get_request_var('grouping') .
			'&view='      . get_request_var('view') .
			'&crit='      . get_request_var('crit') .
			'&size='      . get_request_var('size') .
			'&trim='      . get_request_var('trim') .
			'&status='    . get_request_var('status') .
			'&tree='      . get_request_var('tree') .
			'&site='      . get_request_var('site');

		if (!isset_request_var('user')) {
			$user = $_SESSION['sess_user_id'];
		} else {
			$user = get_request_var('user');
		}

		$id   = get_request_var('dashboard');
		$name = get_nfilter_request_var('name');

		$save = array();
		$save['id']      = $id;
		$save['name']    = $name;
		$save['user_id'] = $user;
		$save['url']     = $url;

		$id = sql_save($save, 'plugin_monitor_dashboards');

		if (!empty($id)) {
			raise_message('monitorsaved', __('Dashboard \'%s\' has been Saved!', $name, 'monitor'), MESSAGE_LEVEL_INFO);
			set_request_var('dashboard', $id);
		} else {
			raise_message('monitornotsaved', __('Dashboard \'%s\' could not be Saved!', $name, 'monitor'), MESSAGE_LEVEL_INFO);
			set_request_var('dashboard', '0');
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
		'dashboard' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_dashboard', '0', $force)
		),
		'rfilter' => array(
			'filter' => FILTER_VALIDATE_IS_REGEX,
			'default' => read_user_setting('monitor_rfilter', '', $force)
		),
		'name' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => ''
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
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => read_user_setting('monitor_size', 'monitor_medium', $force)
		),
		'trim' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_trim', read_config_option('monitor_trim'), $force)
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
		),
		'site' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_site', '-1', $force)
		),
		'template' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_template', '-1', $force)
		),
		'id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
		),
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'status',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_monitor');
	/* ================= input validation ================= */
}

function render_group_concat(&$sql_where, $sql_join, $sql_field, $sql_data, $sql_suffix = '') {
	// Remove empty entries if something was returned
	if (!empty($sql_data)) {
		$sql_data = trim(str_replace(',,',',',$sql_data), ',');

		if (!empty($sql_data)) {
			$sql_where .= ($sql_where != '' ? $sql_join : '') . "($sql_field IN($sql_data) $sql_suffix)";
		}
	}
}

function render_where_join(&$sql_where, &$sql_join) {
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
				array(get_request_var('tree')));

			render_group_concat($awhere, ' AND ', 'h.id', $hlist);
		} elseif (get_request_var('tree') == -2) {
			$hlist = db_fetch_cell('SELECT GROUP_CONCAT(DISTINCT h.id)
				FROM host AS h
				LEFT JOIN (SELECT DISTINCT host_id FROM graph_tree_items WHERE host_id > 0) AS gti
				ON h.id = gti.host_id
				WHERE gti.host_id IS NULL
				AND h.deleted = ""');

			render_group_concat($ahwere, ' AND ', 'h.id', $hlist);
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
			OR ' . get_thold_where() . '
			OR ((h.availability_method > 0 OR h.snmp_version > 0)
				AND ((h.cur_time > h.monitor_warn AND h.monitor_warn > 0)
				OR (h.cur_time > h.monitor_alert AND h.monitor_alert > 0))
			))' . $awhere;
	} else {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';

		$sql_where = 'WHERE h.disabled = ""
			AND h.monitor = "on"
			AND h.deleted = ""
			AND (h.availability_method > 0 OR h.snmp_version > 0
				OR (td.thold_enabled="on" AND td.thold_alert > 0)
			)' . $awhere;
	}
}

/* Render functions */
function render_default() {
	global $maxchars;

	$result = '';

	$sql_where = '';
	$sql_join  = '';

	render_where_join($sql_where, $sql_join);

	$hosts_sql = ("SELECT DISTINCT h.*, IFNULL(s.name,' " . __('Non-Site Device', 'monitor') . " ') AS site_name
		FROM host AS h
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		$sql_join
		$sql_where
		ORDER BY description");

	$hosts = db_fetch_assoc($hosts_sql);

	if (cacti_sizeof($hosts)) {
		// Determine the correct width of the cell
		$maxlen = 10;
		if (get_request_var('view') == 'default') {
			$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
				FROM host AS h
				$sql_join
				$sql_where");
		}
		$maxlen = get_monitor_trim_length($maxlen);

		$function = 'render_header_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_header_ function */
			$result .= $function($hosts);
		}

		foreach($hosts as $host) {
			$result .= render_host($host, true, $maxlen);
		}

		$function = 'render_footer_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_footer_ function */
			$result .= $function($hosts);
		}
	}

	return $result;
}

function render_site() {
	global $maxchars;

	$result = '';

	$sql_where = '';
	$sql_join = '';

	render_where_join($sql_where, $sql_join);

	$hosts_sql = ("SELECT DISTINCT h.*, IFNULL(s.name,' " . __('Non-Site Devices', 'monitor') . " ') AS site_name
		FROM host AS h
		LEFT JOIN sites AS s
		ON s.id = h.site_id
		$sql_join
		$sql_where
		ORDER BY site_name, description");

	$hosts = db_fetch_assoc($hosts_sql);

	$ctemp = -1;
	$ptemp = -1;

	if (cacti_sizeof($hosts)) {
		$suppressGroups = false;
		$function = 'render_suppressgroups_'. get_request_var('view');
		if (function_exists($function)) {
			$suppressGroups = $function($hosts);
		}

		$function = 'render_header_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_header_ function */
			$result .= $function($hosts);
			$suppresGroups = true;
		}

		foreach($hosts as $host) {
			$host_ids[] = $host['id'];
		}

		// Determine the correct width of the cell
		$maxlen = 10;
		if (get_request_var('view') == 'default') {
			$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
				FROM host AS h
				WHERE id IN (" . implode(',', $host_ids) . ")");
		}
		$maxlen = get_monitor_trim_length($maxlen);

		$class   = get_request_var('size');
		$csuffix = get_request_var('view');

		if ($csuffix == 'default') {
			$csuffix = '';
		}

		foreach($hosts as $host) {
			$ctemp = $host['site_id'];

			if (!$suppressGroups) {
				if ($ctemp != $ptemp && $ptemp > 0) {
					$result .= "</div>";
				}

				if ($ctemp != $ptemp) {
					$result .= "<div class='monitorTable'><div class='navBarNavigation'><div class='navBarNavigationNone'>" . $host['site_name'] . "</div></div></div><div class='monitor_container'>";
				}
			}

			$result .= render_host($host, true, $maxlen);

			if ($ctemp != $ptemp) {
				$ptemp = $ctemp;
			}
		}

		if ($ptemp == $ctemp && !$suppressGroups) {
			$result .= "</div>";
		}

		$function = 'render_footer_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_footer_ function */
			$result .= $function($hosts);
		}
	}

	return $result;
}

function render_template() {
	global $maxchars;

	$result = '';

	$sql_where = '';
	$sql_join = '';

	render_where_join($sql_where, $sql_join);

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
		ORDER BY ht.name, h.description");

	$ctemp = -1;
	$ptemp = -1;

	if (cacti_sizeof($hosts)) {
		$suppressGroups = false;
		$function = 'render_suppressgroups_'. get_request_var('view');
		if (function_exists($function)) {
			$suppressGroups = $function($hosts);
		}

		$function = 'render_header_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_header_ function */
			$result .= $function($hosts);
			$suppresGroups = true;
		}

		foreach($hosts as $host) {
			$host_ids[] = $host['id'];
		}

		// Determine the correct width of the cell
		$maxlen = 10;
		if (get_request_var('view') == 'default') {
			$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
				FROM host AS h
				WHERE id IN (" . implode(',', $host_ids) . ")");
		}
		$maxlen = get_monitor_trim_length($maxlen);

		$class   = get_request_var('size');
		$csuffix = get_request_var('view');

		if ($csuffix == 'default') {
			$csuffix = '';
		}

		foreach($hosts as $host) {
			$ctemp = $host['host_template_id'];

			if (!$suppressGroups) {
				if ($ctemp != $ptemp && $ptemp > 0) {
					$result .= "</div>";
				}

				if ($ctemp != $ptemp) {
					$result .= "<div class='monitorTable'><div class='navBarNavigation'><div class='navBarNavigationNone'>" . $host['host_template_name'] . "</div></div></div><div class='monitor_container'>";
				}
			}

			$result .= render_host($host, true, $maxlen);

			if ($ctemp != $ptemp) {
				$ptemp = $ctemp;
			}
		}

		if ($ptemp == $ctemp && !$suppressGroups) {
			$result .= "</div>";
		}

		$function = 'render_footer_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_footer_ function */
			$result .= $function($hosts);
		}
	}

	return $result;
}

function render_tree() {
	global $maxchars;

	$result = '';

	$leafs = array();

	if (get_request_var('tree') > 0) {
		$sql_where = 'gt.id=' . get_request_var('tree');
	} else {
		$sql_where = '';
	}

	if (get_request_var('tree') != -2) {
		$tree_list = get_allowed_trees(false, false, $sql_where, 'sequence');
	} else {
		$tree_list = array();
	}

	$function = 'render_header_' . get_request_var('view');
	if (function_exists($function)) {
		$hosts = array();

		/* Call the custom render_header_ function */
		$result .= $function($hosts);
	}

	if (cacti_sizeof($tree_list)) {
		$ptree = '';
		foreach($tree_list as $tree) {
			$tree_ids[$tree['id']] = $tree['id'];
		}

		render_where_join($sql_where, $sql_join);

		$branchWhost_SQL = ("SELECT DISTINCT gti.graph_tree_id, gti.parent
			FROM graph_tree_items AS gti
			INNER JOIN graph_tree AS gt
			ON gt.id = gti.graph_tree_id
			INNER JOIN host AS h
			ON h.id=gti.host_id
			$sql_join
			$sql_where
			AND gti.host_id > 0
			AND gti.graph_tree_id IN (" . implode(',', $tree_ids) . ")
			ORDER BY gt.sequence, gti.position");

		$branchWhost = db_fetch_assoc($branchWhost_SQL);

		// Determine the correct width of the cell
		$maxlen = 10;
		if (get_request_var('view') == 'default') {
			$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
				FROM host AS h
				INNER JOIN graph_tree_items AS gti
				ON gti.host_id = h.id
				WHERE disabled = ''
				AND deleted = ''");
		}
		$maxlen = get_monitor_trim_length($maxlen);

		if (cacti_sizeof($branchWhost)) {
			foreach($branchWhost as $b) {
				if ($ptree != $b['graph_tree_id']) {
					$titles[$b['graph_tree_id'] . ':0'] = __('Root Branch', 'monitor');
					$ptree = $b['graph_tree_id'];
				}

				if ($b['parent'] > 0) {
					$titles[$b['graph_tree_id'] . ':' . $b['parent']] = db_fetch_cell_prepared('SELECT title
						FROM graph_tree_items
						WHERE id = ?
						AND graph_tree_id = ?
						ORDER BY position',
						array($b['parent'], $b['graph_tree_id']));
				}
			}

			$ptree = '';

			foreach($titles as $index => $title) {
				list($graph_tree_id, $parent) = explode(':', $index);

				$oid = $parent;

				$sql_where = '';
				$sql_join  = '';

				render_where_join($sql_where, $sql_join);

				$hosts_sql = "SELECT h.*, IFNULL(s.name,' " . __('Non-Site Device', 'monitor') . " ') AS site_name
					FROM host AS h
					LEFT JOIN sites AS s
					ON h.site_id=s.id
					INNER JOIN graph_tree_items AS gti
					ON h.id=gti.host_id
					$sql_join
					$sql_where
					AND parent = ?
					AND graph_tree_id = ?
					GROUP BY h.id";

				$hosts = db_fetch_assoc_prepared($hosts_sql, array($oid, $graph_tree_id));

				$tree_name = db_fetch_cell_prepared('SELECT name
					FROM graph_tree
					WHERE id = ?',
					array($graph_tree_id));

				if ($ptree != $tree_name) {
					if ($ptree != '') {
						$result .= '</div>';
					}

					$result .= "<div class='monitorTable'><div class='navBarNavigation'><div class='navBarNavigationNone'>" . __('Tree: %s', $tree_name, 'monitor') . "</div></div></div><div class='monitorTable'><div class='monitor_sub_container'>";

					$ptree = $tree_name;
				}

				if (cacti_sizeof($hosts)) {
					foreach($hosts as $host) {
						$host_ids[] = $host['id'];
					}

					$class = get_request_var('size');

					$result .= "<div class='monitorSubTable'><div class='navBarNavigation'><div class='navBarNavigationNone'>" . __('Branch: %s', $title, 'monitor') . "</div></div><div class='monitor_sub_container'>";

					foreach($hosts as $host) {
						$result .= render_host($host, true, $maxlen);
					}

					$result .= '</div></div>';
				}
			}
		}

		$result .= '</div>';
	}

	/* begin others - lets get the monitor items that are not associated with any tree */
	if (get_request_var('tree') < 0) {
		$hosts = get_host_non_tree_array();

		if (cacti_sizeof($hosts)) {
			foreach($hosts as $host) {
				$host_ids[] = $host['id'];
			}

			// Determine the correct width of the cell
			$maxlen = 10;
			if (get_request_var('view') == 'default') {
				if (cacti_sizeof($host_ids)) {
					$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
						FROM host AS h
						WHERE id IN (" . implode(',', $host_ids) . ")
						AND h.deleted = ''");
				}
			}
			$maxlen = get_monitor_trim_length($maxlen);

			$result .= "<div><div class='navBarNavigation'><div class='navBarNavigationNone'>" . __('Non-Tree Devices', 'monitor') . "</div></div></div><div class='monitor_container'>";
			foreach($hosts as $leaf) {
				$result .= render_host($leaf, true, $maxlen);
			}

			$result .= '</div></div>';
		}
	}

	$function = 'render_footer_' . get_request_var('view');
	if (function_exists($function)) {
		/* Call the custom render_footer_ function */
		$result .= $function($hosts);
	}

	return $result;
}

function get_host_status($host, $real = false) {
	global $thold_hosts, $iclasses;

	/* If the host has been muted, show the muted Icon */
	if ($host['status'] != 1 && in_array($host['id'], $thold_hosts)) {
		$host['status'] = 4;
	}

	if (in_array($host['id'], $_SESSION['monitor_muted_hosts']) && $host['status'] == 1) {
		$host['status'] = 5;
	} elseif (in_array($host['id'], $_SESSION['monitor_muted_hosts']) && $host['status'] == 4) {
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

function get_host_status_description($status) {
	global $icolorsdisplay;

	if (array_key_exists($status, $icolorsdisplay)) {
		return $icolorsdisplay[$status];
	} else {
		return __('Unknown', 'monitor') . " ($status)";
	}
}

/*Single host  rendering */
function render_host($host, $float = true, $maxlen = 10) {
	global $thold_hosts, $config, $icolorsdisplay, $iclasses, $classes, $maxchars, $mon_zoom_state;

	//throw out tree root items
	if (array_key_exists('name', $host))  {
		return;
	}

	if (!is_device_allowed($host['id'])) {
		return;
	}

	if ($host['id'] <= 0) {
		return;
	}

	$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];

	if ($host['status'] == 3 && array_key_exists($host['id'], $thold_hosts)) {
		$host['status'] = 4;
		$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=true&status=1&host_id=' . $host['id'];
	}

	$host['real_status'] = get_host_status($host, true); $host['status'] = get_host_status($host); $host['iclass'] = $iclasses[$host['status']];

	$function = 'render_host_' . get_request_var('view');

	if (function_exists($function)) {
		/* Call the custom render_host_ function */
		$result = $function($host);
	} else {
		$iclass = get_status_icon($host['status']);
		$fclass = get_request_var('size');

		$monitor_times=read_user_setting('monitor_uptime');
		$monitor_time_html="";

		if ($host['status'] <= 2 || $host['status'] == 5) {
			if ($mon_zoom_state) {
				$fclass ='monitor_errorzoom';
			}
			$tis = get_timeinstate($host);

			if ($monitor_times=='on') {
				$monitor_time_html="<br><div class='monitor_device${fclass} deviceDown'>$tis</div>";
			}
			$result = "<div class='$fclass flash monitor_device_frame'><a class='pic hyperLink' href='" . html_escape($host['anchor']) . "'><i id='" . $host['id'] . "' class='$iclass " . $host['iclass'] . "'></i><br><div class='${fclass}_title'>" . title_trim(html_escape($host['description']), $maxlen) . "</div>$monitor_time_html</a></div>";
		} else {
			$tis = get_uptime($host);

			if ($monitor_times=='on') {
				$monitor_time_html="<br><div class='monitor_device${fclass} deviceUp'>$tis</div>";
			}

			$result = "<div class='$fclass monitor_device_frame'><a class='pic hyperLink' href='" . html_escape($host['anchor']) . "'><i id=" . $host['id'] . " class='$iclass " . $host['iclass'] . "'></i><br><div class='${fclass}_title'>" . title_trim(html_escape($host['description']), $maxlen) . "</div>$monitor_time_html</a></div>";
		}
	}

	return $result;
}

function get_status_icon($status) {
	if (($status == 1 || ($status == 4 && get_request_var('status') > 0)) && read_user_setting('monitor_sound') == 'First Orders Suite.mp3') {
		return 'fab fa-first-order fa-spin';
	} else {
		return 'fa fa-server';
	}
}

function monitor_print_host_time($status_time, $seconds = false) {
	// If the host is down, make a downtime since message
	$dt   = '';
	if (is_numeric($status_time)) {
		$sfd  = round($status_time / 100,0);
	} else {
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
		$dt .= ($seconds ? $dt_s . 's':__('Just Up', 'monitor'));
	}

	return $dt;
}

function ajax_status() {
	global $thold_hosts, $config, $icolorsdisplay, $iclasses, $criticalities;

	$tholds = 0;

	validate_request_vars();

	if (isset_request_var('id') && get_filter_request_var('id')) {
		$id   = get_request_var('id');
		$size = get_request_var('size');

		$host = db_fetch_row_prepared('SELECT *
			FROM host
			WHERE id = ?',
			array($id));

		if (!cacti_sizeof($host)) {
			cacti_log('Attempted to retrieve status for missing Device ' . $id, false, 'MONITOR', POLLER_VERBOSITY_HIGH);
			return false;
		}

		$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];

		if ($host['status'] == 3 && array_key_exists($host['id'], $thold_hosts)) {
			$host['status'] = 4;
			$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=true&status=1&host_id=' . $host['id'];
		}

		if ($host['availability_method'] == 0) {
			$host['status'] = 6;
		}

		$host['real_status'] = get_host_status($host, true);
		$host['status'] = get_host_status($host);

		if (cacti_sizeof($host)) {
			if (api_plugin_user_realm_auth('host.php')) {
				$host_link = html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']);
			}

			// Get the number of graphs
			$graphs = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM graph_local
				WHERE host_id = ?',
				array($host['id']));

			if ($graphs > 0) {
				$graph_link = html_escape($config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id']);
			}

			// Get the number of thresholds
			if (api_plugin_is_enabled('thold')) {
				$tholds = db_fetch_cell_prepared('SELECT count(*)
					FROM thold_data
					WHERE host_id = ?',
					array($host['id']));

				if ($tholds) {
					$thold_link = html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=true&status=1&host_id=' . $host['id']);
				}
			}

			// Get the number of syslogs
			if (api_plugin_is_enabled('syslog') && api_plugin_user_realm_auth('syslog.php')) {
				include($config['base_path'] . '/plugins/syslog/config.php');
				include_once($config['base_path'] . '/plugins/syslog/functions.php');

				$syslog_logs = syslog_db_fetch_cell_prepared('SELECT count(*)
					FROM syslog_logs
					WHERE host = ?',
					array($host['hostname']));

				$syslog_host = syslog_db_fetch_cell_prepared('SELECT host_id
					FROM syslog_hosts
					WHERE host = ?',
					array($host['hostname']));

				if ($syslog_logs && $syslog_host) {
					$syslog_log_link = html_escape($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=alerts&host_id=' . $syslog_host);
				}

				if ($syslog_host) {
					$syslog_link = html_escape($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=syslog&host_id=' . $syslog_host);
				}
			} else {
				$syslog_logs  = 0;
				$syslog_host  = 0;
			}

			$links = '';
			if (isset($host_link)) {
				$links .= '<div><a class="pic hyperLink monitorLink" href="' . $host_link . '"><i class="fas fa-pen-square deviceUp monitorLinkIcon"></i></a></div>';
			}

			if (isset($graph_link)) {
				$links .= '<div><a class="pic hyperLink monitorLink" href="' . $graph_link . '"><i class="fa fa-chart-line deviceUp monitorLinkIcon"></i></a></div>';
			}

			if (isset($thold_link)) {
				$links .= '<div><a class="pic hyperLink monitorLink" href="' . $thold_link . '"><i class="fas fa-tasks deviceRecovering monitorLinkIcon"></i></a></div>';
			}

			if (isset($syslog_log_link)) {
				$links .= '<div><a class="pic hyperLink monitorLink" href="' . $syslog_log_link . '"><i class="fas fa-life-ring deviceDown monitorLinkIcon"></i></a></div>';
			}

			if (isset($syslog_link)) {
				$links .= '<div><a class="pic hyperLink monitorLink" href="' . $syslog_link . '"><i class="fas fa-life-ring deviceUp monitorLinkIcon"></i></a></div>';
			}

			$iclass   = $iclasses[$host['status']];
			$sdisplay = get_host_status_description($host['real_status']);

			print "<table class='monitorHover $size'>
				<tr class='tableHeader'>
					<th class='left' colspan='2'>" . __('Device Status Information', 'monitor') . '</th>
				</tr>
				<tr>
					<td>' . __('Device:', 'monitor') . "</td>
					<td><a class='pic hyperLink monitorLink' href='" . html_escape($host['anchor']) . "'>" . html_escape($host['description']) . '</a></td>
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
					<td>' . __('%d ms', $host['cur_time'], 'monitor') . ' / ' .  __('%d ms', $host['avg_time'], 'monitor') . '</td>
				</tr>' : '') . (isset($host['monitor_warn']) && ($host['monitor_warn'] > 0 || $host['monitor_alert'] > 0) ? "
				<tr>
					<td class='nowrap'>" . __('Warn/Alert:', 'monitor') . '</td>
					<td>' . __('%0.2d ms', $host['monitor_warn'], 'monitor') . ' / ' . __('%0.2d ms', $host['monitor_alert'], 'monitor') . '</td>
				</tr>' : '') . '
				<tr>
					<td>' . __('Last Fail:', 'monitor') . '</td>
					<td>' . ($host['status_fail_date'] == '0000-00-00 00:00:00' ? __('Never', 'monitor') : $host['status_fail_date']) . '</td>
				</tr>
				<tr>
					<td>' . __('Time In State:', 'monitor') . '</td>
					<td>' . get_timeinstate($host) . '</td>
				</tr>
				<tr>
					<td>' . __('Availability:', 'monitor') . '</td>
					<td>' . round($host['availability'],2) . ' %</td>
				</tr>' . ($host['snmp_version'] > 0 && ($host['status'] == 3 || $host['status'] == 2) ? '
				<tr>
					<td>' . __('Agent Uptime:', 'monitor') . '</td>
					<td>' . ($host['status'] == 3 || $host['status'] == 5 ? monitor_print_host_time($host['snmp_sysUpTimeInstance']) : __('N/A', 'monitor')) . "</td>
				</tr>
				<tr>
					<td class='nowrap'>" . __('Sys Description:', 'monitor') . '</td>
					<td>' . html_escape(monitor_trim($host['snmp_sysDescr'])) . '</td>
				</tr>
				<tr>
					<td>' . __('Location:', 'monitor') . '</td>
					<td>' . html_escape(monitor_trim($host['snmp_sysLocation'])) . '</td>
				</tr>
				<tr>
					<td>' . __('Contact:', 'monitor') . '</td>
					<td>' . html_escape(monitor_trim($host['snmp_sysContact'])) . '</td>
				</tr>' : '') . ($host['notes'] != '' ? '
				<tr>
					<td>' . __('Notes:', 'monitor') . '</td>
					<td>' . html_escape($host['notes']) . '</td>
				</tr>' : '') . "
				<tr><td colspan='2' style='width:100%'><hr></td></tr>
				<tr><td colspan='2' style='width:100%'><div style='display:flex;justify-content:space-around;'>$links</div></td></tr>
				</table>";
		}
	}
}

function monitor_trim($string) {
	return trim($string, "\"'\\ \n\t\r");
}

function render_header_default($hosts) {
	return "<div class='monitorTable monitor'><div class='monitor_container'>";
}

function render_header_tiles($hosts) {
	return render_header_default($hosts);
}

function render_header_tilesadt($hosts) {
	return render_header_default($hosts);
}

function render_header_list($hosts) {
	$display_text = array(
		'hostname'    => array('display' => __('Hostname', 'monitor'),         'align' => 'left', 'tip' => __('Hostname of device', 'monitor')),
		'description' => array('display' => __('Description', 'monitor'),      'align' => 'left'),
		'site_name'   => array('display' => __('Site', 'monitor'),             'align' => 'left'),
		'criticality' => array('display' => __('Criticality', 'monitor'),      'align' => 'left'),
		'avail'       => array('display' => __('Availability', 'monitor'),     'align' => 'right'),
		'status'      => array('display' => __('Status', 'monitor'),           'align' => 'center'),
		'duration'    => array('display' => __('Length in status', 'monitor'), 'align' => 'center'),
		'average'     => array('display' => __('Averages', 'monitor'),         'align' => 'left'),
		'warnings'    => array('display' => __('Warning', 'monitor'),          'align' => 'left'),
		'lastfail'    => array('display' => __('Last Fail', 'monitor'),        'align' => 'left'),
		'admin'       => array('display' => __('Admin', 'monitor'),            'align' => 'left'),
		'notes'       => array('display' => __('Notes', 'monitor'),            'align' => 'left')
	);

	$output  = html_start_box(__('Monitored Devices', 'monitor'), '100%', '', '3', 'center', '');
	$output .= html_nav_bar('monitor.php', 1, 1, sizeof($hosts), sizeof($hosts), cacti_sizeof($display_text), __('Devices', 'monitor'));
	$output .= html_header($display_text, '', '', false);

	return $output;
}

function render_suppressgroups_list($hosts) {
	return true;
}

function render_footer_default($hosts) {
	return '</div></div>';
}

function render_footer_tiles($hosts) {
	return render_footer_default($hosts);
}

function render_footer_tilesadt($hosts) {
	return render_footer_default($hosts);
}

function render_footer_list($hosts) {
	html_end_box(false);
}

function render_host_list($host) {
	global $criticalities;

	if (!is_device_allowed($host['id'])) {
		return ;
	}

	if ($host['status'] < 2 || $host['status'] == 5) {
		$dt = get_timeinstate($host);
	} elseif ($host['status_rec_date'] != '0000-00-00 00:00:00') {
		$dt = get_timeinstate($host);
	} else {
		$dt = __('Never', 'monitor');
	}

	if ($host['status'] < 3 || $host['status'] == 5 ) {
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
		$host_address = $host['hostname'];
		$host_avg     =	__('%d ms', $host['cur_time'], 'monitor') . ' / ' .  __('%d ms', $host['avg_time'], 'monitor');
	} else {
		$host_address = '';
		$host_avg     = __('N/A', 'monitor');
	}

	if (isset($host['monitor_warn']) && ($host['monitor_warn'] > 0 || $host['monitor_alert'] > 0)) {
		$host_warn = __('%0.2d ms', $host['monitor_warn'], 'monitor') . ' / ' . __('%0.2d ms', $host['monitor_alert'], 'monitor');
	} else {
		$host_warn = '';
	}

	$host_datefail = $host['status_fail_date'] == '0000-00-00 00:00:00' ? __('Never', 'monitor'):$host['status_fail_date'];

	$sdisplay = get_host_status_description($host['real_status']);

	$result = form_alternate_row('line' . $host['id'], true);
	$result .= form_selectable_cell('<a class="pic hyperLink linkEditMain" href="' . html_escape($host['anchor']) . '">' . $host['hostname'] .'</a>', $host['id']);
	$result .= form_selectable_cell($host['description'], $host['id']);
	$result .= form_selectable_cell($host['site_name'], $host['id']);
	$result .= form_selectable_cell($host_crit, $host['id']);
	$result .= form_selectable_cell(round($host['availability'],2) . " %", $host['id'], '', 'text-align:right;');
	$result .= form_selectable_cell($sdisplay, $host['id'], '', 'text-align:center;');
	$result .= form_selectable_cell($dt, $host['id'], '', 'text-align: center;');
	$result .= form_selectable_cell($host_avg, $host['id']);
	$result .= form_selectable_cell($host_warn, $host['id']);
	$result .= form_selectable_cell($host_datefail, $host['id']);
	$result .= form_selectable_cell($host_admin, $host['id']);
	$result .= form_selectable_cell($host['notes'], $host['id']);
	$result .= form_end_row();

	return $result;
}

function render_host_tiles($host, $maxlen = 10) {
	$class  = get_status_icon($host['status']);
	$fclass = get_request_var('size');

	if (!is_device_allowed($host['id'])) {
		return;
	}

	$result = "<div class='${fclass}_tiles monitor_device_frame'><a class='pic hyperLink textSubHeaderDark' href='" . html_escape($host['anchor']) . "'><i id='" . $host['id'] . "' class='$class " . $host['iclass'] . "'></i></a></div>";

	return $result;
}

function render_host_tilesadt($host, $maxlen = 10) {
	$tis = '';

	if (!is_device_allowed($host['id'])) {
		return;
	}

	$class  = get_status_icon($host['status']);
	$fclass = get_request_var('size');

	if ($host['status'] < 2 || $host['status'] == 5) {
		$tis = get_timeinstate($host);

		$result = "<div class='${fclass}_tilesadt monitor_device_frame'><a class='pic hyperLink textSubHeaderDark' href='" . html_escape($host['anchor']) . "'><i id='" . $host['id'] . "' class='$class " . $host['iclass'] . "'></i><br><span class='monitor_device_${fclass} deviceDown'>$tis</span></a></div>";

		return $result;
	} else {
		$tis = get_uptime($host);

		$result = "<div class='${fclass}_tilesadt monitor_device_frame'><a class='pic hyperLink textSubHeaderDark' href='" . html_escape($host['anchor']) . "'><i id='" . $host['id'] . "' class='$class " . $host['iclass'] . "'></i><br><span class='monitor_device_${fclass} deviceUp'>$tis</span></a></div>";

		return $result;
	}
}

function get_hosts_down_or_triggered_by_permission($prescan) {
	global $render_style;
	$PreScanValue=2;
	if ($prescan) {
		$PreScanValue=3;
	}

	$result = array();

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
				array(get_request_var('tree')));

			render_group_concat($sql_add_where, ' OR ', 'h.id', $devices,'AND h.status < 2');
		}
	}

	if (get_request_var('status') > 0) {
		$triggered = db_fetch_cell('SELECT GROUP_CONCAT(DISTINCT host_id) AS hosts
			FROM host AS h
			INNER JOIN thold_data AS td
			ON td.host_id = h.id
			WHERE ' . get_thold_where() . '
			AND h.deleted = ""');

		render_group_concat($sql_add_where, ' OR ', 'h.id', $triggered, 'AND h.status > 1');

		$_SESSION['monitor_triggered'] = array_rekey(
			db_fetch_assoc('SELECT td.host_id, COUNT(DISTINCT td.id) AS triggered
				FROM thold_data AS td
				INNER JOIN host AS h
				ON td.host_id = h.id
				WHERE ' . get_thold_where() . '
				AND h.deleted = ""
				GROUP BY td.host_id'),
			'host_id', 'triggered'
		);
	}

	$sql_where = "h.monitor = 'on'
		AND h.disabled = ''
		AND h.deleted = ''
		AND ((h.status < " . $PreScanValue . " AND (h.availability_method > 0 OR h.snmp_version > 0)) " .
		($sql_add_where != '' ? ' OR (' . $sql_add_where . '))':')');

	// do a quick loop through to pull the hosts that are down
	$hosts = get_allowed_devices($sql_where);
	if (cacti_sizeof($hosts)) {
		foreach($hosts as $host) {
			$result[] = $host['id'];
			sort($result);
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

	$heirarchy = db_fetch_assoc("SELECT DISTINCT
		h.*, gti.title, gti.host_id, gti.host_grouping_type, gti.graph_tree_id
		FROM host AS h
		LEFT JOIN graph_tree_items AS gti
		ON h.id=gti.host_id
		$sql_join
		$sql_where
		AND gti.graph_tree_id IS NULL
		ORDER BY h.description");

	if (cacti_sizeof($heirarchy) > 0) {
		$leafs = array();
		$branchleafs = 0;

		foreach ($heirarchy as $leaf) {
			$leafs[$branchleafs] = $leaf;
			$branchleafs++;
		}
	}

	return $leafs;
}

function get_monitor_trim_length($fieldlen) {
	global $maxchars;

	if (get_request_var('view') == 'default') {
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

