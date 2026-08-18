<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Exercises db_functions.php through the Cacti stubs in bootstrap.php.    |
 | These load and run plugin code, unlike the source-matching rules in     |
 | tests/Integration.                                                      |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/db_functions.php';

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
