<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Stubs the Cacti framework so plugin code loads without a full install,   |
 | following the pattern in plugin_thold.                                   |
 +-------------------------------------------------------------------------+
*/

$GLOBALS['config'] = [
	'base_path'     => dirname(__DIR__),
	'url_path'      => '/cacti/',
	'cacti_version' => '1.2.999',
];

$GLOBALS['__test_request']  = [];
$GLOBALS['__test_settings'] = [];
$GLOBALS['__test_sql']      = [];

if (!function_exists('get_request_var')) {
	function get_request_var($name, $default = '') {
		return $GLOBALS['__test_request'][$name] ?? $default;
	}
}

if (!function_exists('get_nfilter_request_var')) {
	function get_nfilter_request_var($name, $default = '') {
		return get_request_var($name, $default);
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name, $force = false) {
		return $GLOBALS['__test_settings'][$name] ?? '';
	}
}

if (!function_exists('read_user_setting')) {
	function read_user_setting($name, $default = '', $force = false) {
		return $GLOBALS['__test_settings'][$name] ?? $default;
	}
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($a) { return is_array($a) ? count($a) : 0; }
}

if (!function_exists('db_qstr')) {
	function db_qstr($s, $db_conn = false) {
		return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $s) . "'";
	}
}

if (!function_exists('db_qstr_rlike')) {
	/* Mirrors Cacti core: cap the length, strip alternation and bounded
	 * repeats, then quote. */
	function db_qstr_rlike($s, $db_conn = false) {
		$s = (string) $s;

		if (strlen($s) > 255) {
			$s = substr($s, 0, 255);
		}

		$s = str_replace(["\0", '|', '{', '}'], '', $s);

		return 'RLIKE ' . db_qstr($s, $db_conn);
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = [], $col = '', $log = true, $db_conn = false) {
		$GLOBALS['__test_sql'][] = ['sql' => $sql, 'params' => $params];

		return $GLOBALS['__test_db_cell'] ?? '';
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql, $col = '', $log = true, $db_conn = false) {
		$GLOBALS['__test_sql'][] = ['sql' => $sql, 'params' => []];

		return $GLOBALS['__test_db_cell'] ?? '';
	}
}

if (!function_exists('__')) {
	function __($format, ...$args) {
		if (count($args) > 1) { array_pop($args); }

		return $args === [] ? $format : vsprintf($format, $args);
	}
}

function test_set_request(array $vars): void {
	$GLOBALS['__test_request'] = $vars + [
		'crit' => 0, 'site' => 0, 'tree' => 0, 'grouping' => '', 'rfilter' => '', 'status' => '0',
	];
}
