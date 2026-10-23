<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/includes/controller.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];
	$GLOBALS['__test_messages'] = [];
	monitor_test_reset_db_mocks();
	$_SESSION['sess_user_id'] = 5;
});

afterEach(function () {
	unset($_SESSION['sess_user_id']);
});

function monitor_test_dashboard_saves(): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'sql_save'));
}

it('refuses to save over a dashboard owned by another user', function () {
	monitor_test_mock_db('db_fetch_cell_prepared', 'SELECT user_id', '2');
	test_set_request(['dashboard' => 7, 'name' => 'Mine']);

	saveSettings();

	expect(monitor_test_dashboard_saves())->toBe([])
		->and(get_request_var('dashboard'))->toBe('0')
		->and($GLOBALS['__test_messages'])->toBe([
			['id' => 'monitornotsaved', 'text' => 'Dashboard \'%s\' could not be Saved!', 'level' => MESSAGE_LEVEL_ERROR],
		]);
});

it('refuses to save over a shared dashboard', function () {
	monitor_test_mock_db('db_fetch_cell_prepared', 'SELECT user_id', '0');
	test_set_request(['dashboard' => 3, 'name' => 'Shared']);

	saveSettings();

	expect(monitor_test_dashboard_saves())->toBe([]);
});

it('saves a dashboard the user owns', function () {
	monitor_test_mock_db('db_fetch_cell_prepared', 'SELECT user_id', '5');
	test_set_request(['dashboard' => 7, 'name' => 'Mine']);

	saveSettings();

	$saves = monitor_test_dashboard_saves();

	expect($saves)->toHaveCount(1)
		->and($saves[0]['table'])->toBe('plugin_monitor_dashboards')
		->and($saves[0]['data']['id'])->toBe(7)
		->and($saves[0]['data']['user_id'])->toBe(5)
		->and(array_column($GLOBALS['__test_messages'], 'level'))->toBe([MESSAGE_LEVEL_INFO]);
});

it('ignores a forged user when creating a dashboard', function () {
	test_set_request(['dashboard' => -1, 'name' => 'New', 'user' => 2]);

	saveSettings();

	$saves = monitor_test_dashboard_saves();

	expect($saves)->toHaveCount(1)
		->and($saves[0]['data']['user_id'])->toBe(5);
});

it('ignores a forged user when updating an owned dashboard', function () {
	monitor_test_mock_db('db_fetch_cell_prepared', 'SELECT user_id', '5');
	test_set_request(['dashboard' => 7, 'name' => 'Mine', 'user' => 0]);

	saveSettings();

	$saves = monitor_test_dashboard_saves();

	expect($saves)->toHaveCount(1)
		->and($saves[0]['data']['user_id'])->toBe(5);
});
