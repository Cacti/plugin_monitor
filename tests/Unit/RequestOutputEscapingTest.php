<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

it('escapes request values used in hidden inputs', function () {
	$payload = '" autofocus onfocus="alert(1)';
	$escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

	expect($escaped)->not->toContain('"');
	expect($escaped)->toContain('&quot;');
});
