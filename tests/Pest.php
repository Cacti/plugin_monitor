<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

uses()->beforeEach(function () {
	$GLOBALS['__test_sql']      = [];
	$GLOBALS['__test_settings'] = [];
	test_set_request([]);
})->in(__DIR__);
