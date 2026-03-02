var refreshMSeconds = 99999999;
var myTimer;
var monitorCfg = window.monitorPageConfig || {};
var mbColor = monitorCfg.mbColor || '';
var monitorFont = monitorCfg.monitorFont || '10px';
var dozoomRefresh = !!monitorCfg.doZoomRefresh;
var monitorMessages = monitorCfg.messages || {};
var monitorNewForm = monitorCfg.newForm || '';
var monitorNewTitle = monitorCfg.newTitle || '';

if (mbColor !== '') {
	var monoe = false;

	function setZoomErrorBackgrounds() {
		if (mbColor != '') {
			$('.monitor_container').css('background-color', mbColor);
			$('.cactiConsoleContentArea').css('background-color', mbColor);
		}
	}

	setZoomErrorBackgrounds();
	$('.monitor_errorzoom_title').css('font-size', monitorFont);

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
			if (mbColor != '') {
				$('.monitor_container').css('background-color', '');
				$('.cactiConsoleContentArea').css('background-color', '');
			}

			monoe = false;
		}
	}, 600, 8);
} else {
	$('.monitor_container').css('background-color', '');
	$('.cactiConsoleContentArea').css('background-color', '');
}

function timeStep() {
	value = $('#timer').html() - 1;

	if (value <= 0) {
		applyFilter('refresh');
	} else {
		$('#timer').html(value);
		// What is a second, well if you are an
		// imperial storm tropper, it's just a little more than a second.
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
	$('.mon_icon').unbind();

	if (action != 'dashboard') {
		var strURL  = 'monitor.php?header=false';

		if (action >= '') {
			strURL += '&action=' + action;
		}

		strURL += '&refresh='  + $('#refresh').val();
		strURL += '&grouping=' + $('#grouping').val();
		strURL += '&tree='     + $('#tree').val();
		strURL += '&site='     + $('#site').val();
		strURL += '&template=' + $('#template').val();
		strURL += '&view='     + $('#view').val();
		strURL += '&rows='     + $('#rows').val();
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

	loadIt(strURL);
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
		rows: $('#rows').val(),
		crit: $('#crit').val(),
		rfilter: base64_encode($('#rfilter').val()),
		trim: $('#trim').val(),
		mute: $('#mute').val(),
		size: $('#size').val(),
		trim: $('#trim').val(),
		status: $('#status').val(),
		__csrf_magic: csrfMagicToken
	};

	$.post(url, post).done(function(data) {
		$('#text').show().text(monitorMessages.filterSaved || '').fadeOut(2000);
	});
}

function saveNewDashboard(action) {
	if (action == 'new') {
		var dashboard = '-1';
	} else {
		var dashboard = $('#dashboard').val();
	}

	var url = 'monitor.php?header=false';

	var post = {
		action: 'saveDb',
		dashboard: dashboard,
		name: $('#name').val(),
		refresh: $('#refresh').val(),
		grouping: $('#grouping').val(),
		tree: $('#tree').val(),
		site: $('#site').val(),
		template: $('#template').val(),
		view: $('#view').val(),
		rows: $('#rows').val(),
		crit: $('#crit').val(),
		rfilter: base64_encode($('#rfilter').val()),
		trim: $('#trim').val(),
		size: $('#size').val(),
		mute: $('#mute').val(),
		status: $('#status').val(),
		__csrf_magic: csrfMagicToken
	};

	$('#newdialog').dialog('close');

	postIt(url, post);
}

function removeDashboard() {
	url = 'monitor.php?action=remove&header=false&dashboard=' + $('#dashboard').val();
	loadIt(url);
}

function loadIt(url) {
	if (typeof loadUrl == 'undefined') {
		loadPageNoHeader(url);
	} else {
		loadUrl({url: url});
	}
}

function postIt(url, post, returnLocation) {
	if (typeof postUrl == 'undefined') {
		loadPageUsingPost(url, post);
	} else {
		postUrl({
			url: url,
			tabId: returnLocation,
			type: 'loadPageUsingPost'
		}, post);
	}
}

function saveDashboard(action) {
	var btnDialog = {
		'Cancel': {
			text: monitorMessages.cancel || 'Cancel',
			id: 'btnCancel',
			click: function() {
				$(this).dialog('close');
			}
		},
		'Save': {
			text: monitorMessages.save || 'Save',
			id: 'btnSave',
			click: function() {
				saveNewDashboard(action);
			}
		}
	};

	if ($('#newdialog').length == 0) {
		$('body').append(monitorNewForm);
	}

	$('#newdialog').dialog({
		title: monitorNewTitle,
		minHeight: 80,
		minWidth: 500,
		buttons: btnDialog,
		position: { at: 'center top+240px', of: window },
		open: function() {
			$('#name').val($('#dashboard option:selected').text());
			$('#btnSave').addClass('ui-state-active');
			$('#name').focus();
			$('#new_dashboard').off('submit').on('submit', function(event) {
				event.preventDefault();
				saveNewDashboard('new');
			});
		}
	});
}

$(function() {
	if (dozoomRefresh) {
		applyFilter('refresh');
	}

	var selectmenu = ($('#grouping').selectmenu('instance') !== undefined);

	if ($('#view').val() == 'list') {
		$('#grouping').prop('disabled', true);
		if (selectmenu) {
			$('#grouping').selectmenu('disable');
		}
	} else {
		$('#grouping').prop('disabled', false);
		if (selectmenu) {
			$('#grouping').selectmenu('enable');
		}
	}

	// Clear the timeout to keep countdown accurate
	clearTimeout(myTimer);

	$('#go').click(function(event) {
		event.preventDefault();
		applyFilter('go');
	});

	$('#clear').click(function(event) {
		loadIt('monitor.php?clear=1&header=false');
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

	$('#refresh, #view, #rows, #trim, #crit, #grouping, #size, #status, #tree, #site, #template').change(function() {
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

	$('.monitor_device_frame').find('i').tooltip({
		items: '.mon_icon',
		open: function(event, ui) {
			ajaxAnchors();

			if (typeof(event.originalEvent) == 'undefined') {
				return false;
			}

			var id = $(ui.tooltip).attr('id');
		},
		close: function(event, ui) {
			ui.tooltip.hover(
			function () {
				$(this).stop(true).fadeTo(400, 1);
			},
			function() {
				$(this).fadeOut('400');
			});
		},
		position: {my: 'left:15 top', at: 'right center'},
		content: function(callback) {
			var id = $(this).attr('id');
			var size = $('#size').val();
			$.get('monitor.php?action=ajax_status&size=' + size + '&id=' + id, function(data) {
				callback(data);
			});
		}
	});

	myTimer = setTimeout(timeStep, 1000);

	$(window).resize(function() {
		$(document).tooltip('option', 'position', {my: '1eft:15 top', at: 'right center'});
	});

	if ($('#mute').val() == 'true') {
		muteUnmuteAudio(true);
	} else {
		muteUnmuteAudio(false);
	}
	$('#main').css('margin-right', '15px');
});
