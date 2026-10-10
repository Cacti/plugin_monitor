<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Exercises includes/functions.php through the Cacti stubs in bootstrap-unit.php.|
 | These load and run plugin code, unlike the source-matching rules in     |
 | tests/Integration.                                                      |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/includes/functions.php';

it('selects the breached threshold clause for status 2', function () {
	test_set_request(['status' => '2']);

	expect(getTholdWhere())->toContain('td.thold_alert != 0 OR td.bl_alert > 0');
});

it('selects the triggered clause for any other status', function () {
	test_set_request(['status' => '0']);

	expect(getTholdWhere())->toContain('thold_fail_count >= td.thold_fail_trigger');
});

it('builds an IN clause from a concatenated id list', function () {
	$where = '';
	renderGroupConcat($where, ' AND ', 'h.id', '4,9,17');

	expect($where)->toBe('(h.id IN(4,9,17) )');
});

it('joins onto an existing clause rather than replacing it', function () {
	$where = 'h.disabled = ""';
	renderGroupConcat($where, ' AND ', 'h.id', '4');

	expect($where)->toStartWith('h.disabled = ""')->and($where)->toContain(' AND ');
});

it('adds nothing when the id list is empty', function () {
	$where = '';
	renderGroupConcat($where, ' AND ', 'h.id', '');

	expect($where)->toBe('');
});

it('collapses the doubled commas GROUP_CONCAT can produce', function () {
	$where = '';
	renderGroupConcat($where, ' AND ', 'h.id', ',,4,,9,,');

	expect($where)->toBe('(h.id IN(4,9) )');
});

it('appends the optional suffix', function () {
	$where = '';
	renderGroupConcat($where, ' AND ', 'h.id', '4', 'OR h.id IS NULL');

	expect($where)->toContain('OR h.id IS NULL');
});

it('returns no service checks when the servcheck plugin is disabled', function () {
	monitor_test_reset_db_mocks();

	expect(getHostTriggeredServchecks(['hostname' => 'dev1.example.com']))->toBe([]);
});

it('returns no service checks when the host has no hostname', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);

	expect(getHostTriggeredServchecks(['hostname' => '']))->toBe([]);
	expect(getHostTriggeredServchecks([]))->toBe([]);
});

it('returns no service checks when the servcheck table is absent', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);

	expect(getHostTriggeredServchecks(['hostname' => 'dev1.example.com']))->toBe([]);
});

it('binds the hostname twice and uses the v0.4 result predicate', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);
	monitor_test_mock_db('db_table_exists', 'plugin_servcheck_test', true);
	monitor_test_mock_db('db_column_exists', fn ($key) => $key === 'plugin_servcheck_test.last_check', true);
	monitor_test_mock_db('db_column_exists', fn ($key) => $key === 'plugin_servcheck_test.last_result', true);

	$seen = null;
	monitor_test_mock_db('db_fetch_assoc_prepared', fn ($sql) => str_contains($sql, 'plugin_servcheck_test'), function ($sql, $params) use (&$seen) {
		$seen = ['sql' => $sql, 'params' => $params];

		return [['id' => 7, 'name' => 'HTTP', 'triggered' => 1]];
	});

	$result = getHostTriggeredServchecks(['hostname' => 'dev1.example.com']);

	expect($result)->toBe([['id' => 7, 'name' => 'HTTP', 'triggered' => 1]]);
	expect($seen['params'])->toBe(['dev1.example.com', 'dev1.example.com']);
	expect($seen['sql'])->toContain('last_check > 0')
		->and($seen['sql'])->toContain("last_result != 'ok'")
		->and($seen['sql'])->toContain('last_error');
});

it('falls back to lastcheck and the failures predicate on the v0.3 schema', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);
	monitor_test_mock_db('db_table_exists', 'plugin_servcheck_test', true);

	$seen = null;
	monitor_test_mock_db('db_fetch_assoc_prepared', fn ($sql) => str_contains($sql, 'plugin_servcheck_test'), function ($sql, $params) use (&$seen) {
		$seen = ['sql' => $sql, 'params' => $params];

		return [];
	});

	getHostTriggeredServchecks(['hostname' => 'dev2']);

	expect($seen['sql'])->toContain('lastcheck > 0')
		->and($seen['sql'])->toContain('failures > 0')
		->and($seen['sql'])->not->toContain('last_result');
});
