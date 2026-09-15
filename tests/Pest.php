<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Pest configuration file. The bootstrap is loaded via phpunit.xml's
 * bootstrap attribute (tests/bootstrap-unit.php), which requires Cacti's
 * own Composer-managed vendor tree checked out by the CI workflow.
 */

uses()->beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
	test_set_request(array());
})->in(__DIR__);
