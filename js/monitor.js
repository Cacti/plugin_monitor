let refreshMSeconds = 99999999;
let myTimer;

const monitorCfg = globalThis.monitorPageConfig || {};
const mbColor = monitorCfg.mbColor || '';
const monitorFont = monitorCfg.monitorFont || '10px';
const dozoomRefresh = Boolean(monitorCfg.doZoomRefresh);
const monitorMessages = monitorCfg.messages || {};
const monitorNewForm = monitorCfg.newForm || '';
const monitorNewTitle = monitorCfg.newTitle || '';

function setZoomErrorBackgrounds() {
	if (mbColor !== '') {
		$('.monitor_container').css('background-color', mbColor);
		$('.cactiConsoleContentArea').css('background-color', mbColor);
	}
}

function setIntervalX(callback, delay, repetitions) {
	let x = 0;
	const intervalID = globalThis.setInterval(() => {
		callback();
		if (++x === repetitions) {
			globalThis.clearInterval(intervalID);
			setZoomErrorBackgrounds();
		}
	}, delay);
}

if (mbColor === '') {
	$('.monitor_container').css('background-color', '');
	$('.cactiConsoleContentArea').css('background-color', '');
} else {
	let monoe = false;

	setZoomErrorBackgrounds();
	$('.monitor_errorzoom_title').css('font-size', monitorFont);

	setIntervalX(() => {
		if (monoe) {
			if (mbColor !== '') {
				$('.monitor_container').css('background-color', '');
				$('.cactiConsoleContentArea').css('background-color', '');
			}

			monoe = false;
		} else {
			setZoomErrorBackgrounds();
			monoe = true;
		}
	}, 600, 8);
}

function timeStep() {
	const value = Number($('#timer').html()) - 1;

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
	} else if ($('#downhosts').val() === 'true') {
		$('audio').each(function() {
			this.play();
		});
	}
}

function closeTip() {
	$(document).tooltip('close');
}

function applyFilter(action = '') {
	clearTimeout(myTimer);
	$('.mon_icon').unbind();

	let strURL;

	if (action === 'dashboard') {
		strURL = `monitor.php?action=dbchange&header=false&dashboard=${$('#dashboard').val()}`;
	} else {
		strURL = 'monitor.php?header=false';

		if (action !== '') {
			strURL += `&action=${action}`;
		}

		strURL += `&refresh=${$('#refresh').val()}`;
		strURL += `&grouping=${$('#grouping').val()}`;
		strURL += `&tree=${$('#tree').val()}`;
		strURL += `&site=${$('#site').val()}`;
		strURL += `&template=${$('#template').val()}`;
		strURL += `&view=${$('#view').val()}`;
		strURL += `&rows=${$('#rows').val()}`;
		strURL += `&crit=${$('#crit').val()}`;
		strURL += `&size=${$('#size').val()}`;
		strURL += `&trim=${$('#trim').val()}`;
		strURL += `&mute=${$('#mute').val()}`;
		strURL += `&rfilter=${base64_encode($('#rfilter').val())}`;
		strURL += `&status=${$('#status').val()}`;
	}

	loadIt(strURL);
}

function saveFilter() {
	const url = 'monitor.php?action=save&header=false';

	const post = {
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
		status: $('#status').val(),
		__csrf_magic: csrfMagicToken
	};

	$.post(url, post).done(() => {
		$('#text').show().text(monitorMessages.filterSaved || '').fadeOut(2000);
	});
}

function saveNewDashboard(action) {
	const dashboard = action === 'new' ? '-1' : $('#dashboard').val();
	const url = 'monitor.php?header=false';

	const post = {
		action: 'saveDb',
		dashboard,
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
	const url = `monitor.php?action=remove&header=false&dashboard=${$('#dashboard').val()}`;
	loadIt(url);
}

function loadIt(url) {
	if (typeof loadUrl === 'undefined') {
		loadPageNoHeader(url);
	} else {
		loadUrl({ url });
	}
}

function postIt(url, post, returnLocation) {
	if (typeof postUrl === 'undefined') {
		loadPageUsingPost(url, post);
	} else {
		postUrl(
			{
				url,
				tabId: returnLocation,
				type: 'loadPageUsingPost'
			},
			post
		);
	}
}

function saveDashboard(action) {
	const btnDialog = {
		Cancel: {
			text: monitorMessages.cancel || 'Cancel',
			id: 'btnCancel',
			click() {
				$(this).dialog('close');
			}
		},
		Save: {
			text: monitorMessages.save || 'Save',
			id: 'btnSave',
			click() {
				saveNewDashboard(action);
			}
		}
	};

	if ($('#newdialog').length === 0) {
		$('body').append(monitorNewForm);
	}

	$('#newdialog').dialog({
		title: monitorNewTitle,
		minHeight: 80,
		minWidth: 500,
		buttons: btnDialog,
		position: { at: 'center top+240px', of: globalThis },
		open() {
			$('#name').val($('#dashboard option:selected').text());
			$('#btnSave').addClass('ui-state-active');
			$('#name').focus();
			$('#new_dashboard')
				.off('submit')
				.on('submit', (event) => {
					event.preventDefault();
					saveNewDashboard('new');
				});
		}
	});
}

$(() => {
	if (dozoomRefresh) {
		applyFilter('refresh');
	}

	const selectmenu = $('#grouping').selectmenu('instance') !== undefined;

	if ($('#view').val() === 'list') {
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

	clearTimeout(myTimer);

	$('#go').click((event) => {
		event.preventDefault();
		applyFilter('go');
	});

	$('#clear').click(() => {
		loadIt('monitor.php?clear=1&header=false');
	});

	$('#sound').click(() => {
		if ($('#mute').val() === 'false') {
			$('#mute').val('true');
			muteUnmuteAudio(true);
			applyFilter('ajax_mute_all');
		} else {
			$('#mute').val('false');
			muteUnmuteAudio(false);
			applyFilter('ajax_unmute_all');
		}
	});

	$('#refresh, #view, #rows, #trim, #crit, #grouping, #size, #status, #tree, #site, #template').change(() => {
		applyFilter('change');
	});

	$('#dashboard').change(() => {
		applyFilter('dashboard');
	});

	$('#save').click(() => {
		saveFilter();
	});

	$('#new').click(() => {
		saveDashboard('new');
	});

	$('#rename').click(() => {
		saveDashboard('rename');
	});

	$('#delete').click(() => {
		removeDashboard();
	});

	$('.monitorFilterForm').submit((event) => {
		event.preventDefault();
		applyFilter('change');
	});

	$('.monitor_device_frame').find('i').tooltip({
		items: '.mon_icon',
		open(event, ui) {
			ajaxAnchors();

			if (event.originalEvent === undefined) {
				return false;
			}
		},
		close(event, ui) {
			ui.tooltip.hover(
				function() {
					$(this).stop(true).fadeTo(400, 1);
				},
				function() {
					$(this).fadeOut('400');
				}
			);
		},
		position: { my: 'left:15 top', at: 'right center' },
		content(callback) {
			const id = $(this).attr('id');
			const size = $('#size').val();
			$.get(`monitor.php?action=ajax_status&size=${size}&id=${id}`, (data) => {
				callback(data);
			});
		}
	});

	myTimer = setTimeout(timeStep, 1000);

	$(globalThis).resize(() => {
		$(document).tooltip('option', 'position', { my: 'left:15 top', at: 'right center' });
	});

	if ($('#mute').val() === 'true') {
		muteUnmuteAudio(true);
	} else {
		muteUnmuteAudio(false);
	}

	$('#main').css('margin-right', '15px');
});
