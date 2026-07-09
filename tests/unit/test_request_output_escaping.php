<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$payload = '" autofocus onfocus="alert(1)';
$escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

if (strpos($escaped, '"') === false && strpos($escaped, '&quot;') !== false) {
	print "OK\n";
	exit(0);
}

fwrite(STDERR, "Expected request values to be escaped for hidden inputs\n");
exit(1);
