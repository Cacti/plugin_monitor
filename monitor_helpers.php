<?php

declare(strict_types = 1);

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 */

if (!function_exists('monitorRunActionAndRender')) {
	/**
	 * Execute one monitor action callback and then render the monitor page.
	 *
	 * @param callable $action Action callback with no arguments.
	 *
	 * @return void
	 */
	function monitorRunActionAndRender(callable $action): void {
		call_user_func($action);
		drawPage();
	}
}
