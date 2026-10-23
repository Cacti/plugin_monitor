<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/includes/controller.php';

dataset('state_changing_actions', [
	'save',
	'saveDb',
	'remove',
	'ajax_mute_all',
	'ajax_unmute_all',
	'ajax_saveorder',
]);

afterEach(function () {
	unset($_SERVER['REQUEST_METHOD']);
});

it('drops a state-changing action sent by GET', function (string $action) {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	test_set_request(['action' => $action]);

	expect(monitorRequestAction())->toBe('');
})->with('state_changing_actions');

it('drops a state-changing action when there is no request method', function (string $action) {
	test_set_request(['action' => $action]);

	expect(monitorRequestAction())->toBe('');
})->with('state_changing_actions');

it('dispatches a state-changing action sent by POST', function (string $action) {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	test_set_request(['action' => $action]);

	expect(monitorRequestAction())->toBe($action);
})->with('state_changing_actions');

it('still dispatches read-only actions sent by GET', function () {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	test_set_request(['action' => 'ajax_status']);

	expect(monitorRequestAction())->toBe('ajax_status');
});

it('ignores an action that is not a string', function () {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	test_set_request(['action' => ['remove']]);

	expect(monitorRequestAction())->toBe('');
});

it('routes the monitor.php dispatcher through the method check', function () {
	$contents = file_get_contents(dirname(__DIR__, 2) . '/monitor.php');

	expect($contents)->toContain('switch (monitorRequestAction()) {')
		->and($contents)->not->toContain("switch (get_nfilter_request_var('action'))");
});
