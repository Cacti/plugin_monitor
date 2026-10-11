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

it('returns no service-check hosts when the plugin is disabled', function () {
	monitor_test_reset_db_mocks();

	expect(checkServchecks())->toBe([]);
});

it('returns no service-check hosts without the servcheck realm', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);

	expect(checkServchecks())->toBe([]);
});

it('returns no service-check hosts when the servcheck table is absent', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);
	monitor_test_mock_db('api_plugin_user_realm_auth', 'servcheck_test.php', true);

	expect(checkServchecks())->toBe([]);
});

it('maps failing checks to host ids using the v0.4 predicate', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);
	monitor_test_mock_db('api_plugin_user_realm_auth', 'servcheck_test.php', true);
	monitor_test_mock_db('db_table_exists', 'plugin_servcheck_test', true);
	monitor_test_mock_db('db_column_exists', fn ($key) => $key === 'plugin_servcheck_test.last_check', true);
	monitor_test_mock_db('db_column_exists', fn ($key) => $key === 'plugin_servcheck_test.last_result', true);

	$seen = null;
	monitor_test_mock_db('db_fetch_assoc', fn ($sql) => str_contains($sql, 'plugin_servcheck_test'), function ($sql) use (&$seen) {
		$seen = $sql;

		return [['id' => 5], ['id' => 9]];
	});

	expect(checkServchecks())->toBe([5 => 5, 9 => 9]);
	expect($seen)->toContain('sct.last_check > 0')
		->and($seen)->toContain("sct.last_result != 'ok'");
});

it('falls back to the v0.3 predicate for the service-check host set', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);
	monitor_test_mock_db('api_plugin_user_realm_auth', 'servcheck_test.php', true);
	monitor_test_mock_db('db_table_exists', 'plugin_servcheck_test', true);

	$seen = null;
	monitor_test_mock_db('db_fetch_assoc', fn ($sql) => str_contains($sql, 'plugin_servcheck_test'), function ($sql) use (&$seen) {
		$seen = $sql;

		return [];
	});

	expect(checkServchecks())->toBe([]);
	expect($seen)->toContain('sct.lastcheck > 0')
		->and($seen)->toContain('sct.failures > 0')
		->and($seen)->not->toContain('last_result');
});

it('builds no event predicate when none are selected', function () {
	$GLOBALS['thold_hosts']     = [1 => 1];
	$GLOBALS['servcheck_hosts'] = [2 => 2];

	expect(monitorEventFilterSql([]))->toBe('');

	unset($GLOBALS['thold_hosts'], $GLOBALS['servcheck_hosts']);
});

it('ORs the selected event predicates together', function () {
	$GLOBALS['thold_hosts']            = [4 => 4, 7 => 7];
	$GLOBALS['servcheck_hosts']        = [8 => 8];
	$_SESSION['monitor_muted_hosts']   = [9 => 9];

	$sql = monitorEventFilterSql(['down', 'triggered', 'alert_ping', 'failed_svc']);

	expect($sql)->toStartWith('(')->and($sql)->toEndWith(')')
		->and($sql)->toContain(' OR ')
		->and($sql)->toContain('h.status = 1 AND h.id NOT IN (9)')
		->and($sql)->toContain('h.id IN (4,7) AND h.id NOT IN (9)')
		->and($sql)->toContain('h.cur_time > h.monitor_alert')
		->and($sql)->toContain('h.id IN (8)');

	unset($GLOBALS['thold_hosts'], $GLOBALS['servcheck_hosts'], $_SESSION['monitor_muted_hosts']);
});

it('uses empty id sets for muted/thold/servcheck events when none exist', function () {
	unset($GLOBALS['thold_hosts'], $GLOBALS['servcheck_hosts'], $_SESSION['monitor_muted_hosts']);

	$sql = monitorEventFilterSql(['triggered', 'failed_svc', 'down_ma']);

	expect($sql)->toContain('h.id IN (0)')
		->and($sql)->toContain('h.id NOT IN (0)');
});

it('ignores unknown event keys', function () {
	expect(monitorEventFilterSql(['bogus', 'nope']))->toBe('');
});

it('appends the OR-combined event filter to the scope query', function () {
	test_set_request(['status' => '-1', 'crit' => '-1', 'grouping' => 'default', 'rfilter' => '', 'tree' => '-1', 'site' => '-1', 'events' => 'down,recovering']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('h.monitor = "on"')
		->and($where)->toContain('(h.status = 1')
		->and($where)->toContain('(h.status = 2)')
		->and($where)->toContain(' OR ');
});

it('accepts the events filter as an array (native multi-select submit)', function () {
	test_set_request(['status' => '-1', 'crit' => '-1', 'grouping' => 'default', 'rfilter' => '', 'tree' => '-1', 'site' => '-1', 'events' => ['warn_ping']]);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('h.cur_time > h.monitor_warn');
});

it('filters to unset criticality for the Not Set selection (crit 0)', function () {
	test_set_request(['status' => '-2', 'crit' => '0', 'grouping' => 'default', 'rfilter' => '', 'tree' => '-1', 'site' => '-1']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('h.monitor_criticality = 0');
});

it('selects the all-devices scope for status -2', function () {
	test_set_request(['status' => '-2', 'crit' => '-1', 'grouping' => 'default', 'rfilter' => '', 'tree' => '-1', 'site' => '-1']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('h.disabled = ""')->and($where)->not->toContain('h.monitor = "on"');
});

it('selects the not-monitored scope for status -3', function () {
	test_set_request(['status' => '-3', 'crit' => '-1', 'grouping' => 'default', 'rfilter' => '', 'tree' => '-1', 'site' => '-1']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('h.monitor = ""');
});

it('selects the without-thresholds scope for status -4', function () {
	test_set_request(['status' => '-4', 'crit' => '-1', 'grouping' => 'default', 'rfilter' => '', 'tree' => '-1', 'site' => '-1']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('td.id IS NULL');
});

it('selects the without-service-checks scope for status -5', function () {
	monitor_test_mock_db('db_table_exists', 'plugin_servcheck_test', true);

	test_set_request(['status' => '-5', 'crit' => '-1', 'grouping' => 'default', 'rfilter' => '', 'tree' => '-1', 'site' => '-1']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('NOT EXISTS')
		->and($where)->toContain('plugin_servcheck_test');
});

it('filters by selected device templates for the template grouping', function () {
	test_set_request(['status' => '-1', 'crit' => '-1', 'grouping' => 'template', 'template' => '3,5', 'rfilter' => '', 'tree' => '-1', 'site' => '-1']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('h.host_template_id IN (3,5)');
});

it('includes non-templated devices (template 0) in the template filter', function () {
	test_set_request(['status' => '-1', 'crit' => '-1', 'grouping' => 'template', 'template' => ['0'], 'rfilter' => '', 'tree' => '-1', 'site' => '-1']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('h.host_template_id IN (0)');
});

it('applies no template filter when the template selection is empty', function () {
	test_set_request(['status' => '-1', 'crit' => '-1', 'grouping' => 'template', 'template' => '', 'rfilter' => '', 'tree' => '-1', 'site' => '-1']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->not->toContain('h.host_template_id');
});

it('defaults to the monitored scope for status -1', function () {
	test_set_request(['status' => '-1', 'crit' => '-1', 'grouping' => 'default', 'rfilter' => '', 'tree' => '-1', 'site' => '-1']);

	$where = '';
	$join  = '';
	renderWhereJoin($where, $join);

	expect($where)->toContain('h.monitor = "on"')->and($where)->toContain('td.thold_enabled');
});

it('returns empty threshold counts when thold is disabled', function () {
	monitor_test_reset_db_mocks();

	expect(monitorTholdCounts())->toBe([]);
});

it('maps per-host threshold counts', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'thold', true);
	monitor_test_mock_db('db_table_exists', 'thold_data', true);

	$seen = null;
	monitor_test_mock_db('db_fetch_assoc', fn ($sql) => str_contains($sql, 'thold_data'), function ($sql) use (&$seen) {
		$seen = $sql;

		return [
			['host_id' => 4, 'total' => 5, 'triggered' => 2, 'breached' => 1],
		];
	});

	expect(monitorTholdCounts())->toBe([4 => ['total' => 5, 'triggered' => 2, 'breached' => 1]]);
	expect($seen)->toContain('thold_data');
});

it('returns empty service-check counts when servcheck is disabled', function () {
	monitor_test_reset_db_mocks();

	expect(monitorServcheckCounts())->toBe([]);
});

it('returns empty service-check counts without the servcheck realm', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);

	expect(monitorServcheckCounts())->toBe([]);
});

it('returns empty service-check counts when the table is absent', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);
	monitor_test_mock_db('api_plugin_user_realm_auth', 'servcheck_test.php', true);

	expect(monitorServcheckCounts())->toBe([]);
});

it('maps per-host service-check counts with the v0.4 predicate', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);
	monitor_test_mock_db('api_plugin_user_realm_auth', 'servcheck_test.php', true);
	monitor_test_mock_db('db_table_exists', 'plugin_servcheck_test', true);
	monitor_test_mock_db('db_column_exists', fn ($key) => $key === 'plugin_servcheck_test.last_check', true);
	monitor_test_mock_db('db_column_exists', fn ($key) => $key === 'plugin_servcheck_test.last_result', true);

	$seen = null;
	monitor_test_mock_db('db_fetch_assoc', fn ($sql) => str_contains($sql, 'plugin_servcheck_test'), function ($sql) use (&$seen) {
		$seen = $sql;

		return [['host_id' => 2, 'total' => 3, 'failed' => 1]];
	});

	expect(monitorServcheckCounts())->toBe([2 => ['total' => 3, 'failed' => 1]]);
	expect($seen)->toContain("last_result != 'ok'");
});

it('falls back to the v0.3 predicate for service-check counts', function () {
	monitor_test_reset_db_mocks();
	monitor_test_mock_db('api_plugin_is_enabled', 'servcheck', true);
	monitor_test_mock_db('api_plugin_user_realm_auth', 'servcheck_test.php', true);
	monitor_test_mock_db('db_table_exists', 'plugin_servcheck_test', true);

	$seen = null;
	monitor_test_mock_db('db_fetch_assoc', fn ($sql) => str_contains($sql, 'plugin_servcheck_test'), function ($sql) use (&$seen) {
		$seen = $sql;

		return [];
	});

	expect(monitorServcheckCounts())->toBe([]);
	expect($seen)->toContain('sct.lastcheck > 0')
		->and($seen)->toContain('sct.failures > 0');
});
