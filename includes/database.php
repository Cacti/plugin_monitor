<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * The plugin_monitor_notify_history table definition (one row per
 * notification event). Shared by the create and upgrade paths so both
 * stay in sync from a single source.
 *
 * @return array<string,mixed> Table definition consumed by
 *                             api_plugin_db_table_create()/db_update_table().
 */
function monitor_notify_history_table_data(): array {
	$data                  = [];
	$data['columns'][]     = ['name' => 'id',                'type' => 'int(10)',      'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][]     = ['name' => 'host_id',           'type' => 'int(10)',      'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][]     = ['name' => 'notify_type',       'type' => 'tinyint(3)',   'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][]     = ['name' => 'ping_time',         'type' => 'double',       'NULL' => true, 'default' => null];
	$data['columns'][]     = ['name' => 'ping_threshold',    'type' => 'int(10)',      'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][]     = ['name' => 'notification_time', 'type' => 'timestamp',    'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][]     = ['name' => 'notes',             'type' => 'varchar(255)', 'NULL' => true, 'default' => null];
	$data['primary']       = ['id'];
	$data['unique_keys'][] = ['name' => 'unique_key', 'columns' => ['host_id', 'notify_type', 'notification_time']];
	$data['type']          = 'InnoDB';
	$data['comment']       = 'Stores Notification Event History';

	return $data;
}

/**
 * The plugin_monitor_reboot_history table definition (one row per detected
 * device reboot).
 *
 * @return array<string,mixed> Table definition.
 */
function monitor_reboot_history_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id',          'type' => 'int(10)',   'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'host_id',     'type' => 'int(10)',   'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'reboot_time', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'log_time',    'type' => 'timestamp', 'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'host_id',     'columns' => ['host_id']];
	$data['keys'][]    = ['name' => 'log_time',    'columns' => ['log_time']];
	$data['keys'][]    = ['name' => 'reboot_time', 'columns' => ['reboot_time']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Keeps Track of Device Reboot Times';

	return $data;
}

/**
 * The plugin_monitor_uptime table definition (last known uptime per
 * device). Uses the current column layout (uptime NOT NULL).
 *
 * @return array<string,mixed> Table definition.
 */
function monitor_uptime_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'host_id', 'type' => 'int(10)',    'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'uptime',  'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['primary']   = ['host_id'];
	$data['keys'][]    = ['name' => 'uptime', 'columns' => ['uptime']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Keeps Track of the Devices last uptime to track agent restarts and reboots';

	return $data;
}

/**
 * The plugin_monitor_dashboards table definition (per-user predefined
 * dashboards).
 *
 * @return array<string,mixed> Table definition.
 */
function monitor_dashboards_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id',      'type' => 'int(10)',       'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'user_id', 'type' => 'int(10)',       'unsigned' => true, 'NULL' => true, 'default' => 0];
	$data['columns'][] = ['name' => 'name',    'type' => 'varchar(128)',  'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'url',     'type' => 'varchar(1024)', 'NULL' => true, 'default' => ''];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'user_id', 'columns' => ['user_id']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Stores predefined dashboard information for a user or users';

	return $data;
}

/**
 * Creates this plugin's own tables through Cacti's tracked plugin table
 * API and ensures the core host table carries the monitoring columns this
 * plugin relies on. Called from plugin_monitor_install() and (idempotently)
 * from monitor_check_upgrade().
 *
 * @return void
 */
function monitor_setup_table() {
	api_plugin_db_table_create('monitor', 'plugin_monitor_notify_history', monitor_notify_history_table_data());
	api_plugin_db_table_create('monitor', 'plugin_monitor_reboot_history', monitor_reboot_history_table_data());
	api_plugin_db_table_create('monitor', 'plugin_monitor_uptime', monitor_uptime_table_data());
	api_plugin_db_table_create('monitor', 'plugin_monitor_dashboards', monitor_dashboards_table_data());

	if (db_table_exists('host')) {
		$row_format = db_fetch_cell("SELECT ROW_FORMAT
			FROM information_schema.tables
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'host'");

		if (strtoupper((string) $row_format) !== 'DYNAMIC') {
			db_execute('ALTER TABLE host ROW_FORMAT=DYNAMIC');
		}
	}

	db_execute('SET SESSION innodb_strict_mode=0');

	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor', 'type' => 'char(3)', 'NULL' => true, 'default' => 'on']);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_text', 'type' => 'text', 'NULL' => false]);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_criticality', 'type' => 'tinyint', 'unsigned' => true, 'NULL' => false, 'default' => '0']);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_warn', 'type' => 'double', 'NULL' => false, 'default' => '0']);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_alert', 'type' => 'double', 'NULL' => false, 'default' => '0']);
	api_plugin_db_add_column('monitor', 'host', ['name' => 'monitor_icon', 'type' => 'varchar(30)', 'NULL' => false, 'default' => '']);

	db_execute('SET SESSION innodb_strict_mode=1');
}

/**
 * Refreshes this plugin's own tables to their current definition on
 * upgrade: db_update_table() diffs the live schema against the definition
 * and issues the exact ALTER when the table already exists, otherwise the
 * table is created outright. Called from monitor_check_upgrade().
 *
 * @return void
 */
function monitor_upgrade_tables() {
	$tables = [
		'plugin_monitor_notify_history' => monitor_notify_history_table_data(),
		'plugin_monitor_reboot_history' => monitor_reboot_history_table_data(),
		'plugin_monitor_uptime'         => monitor_uptime_table_data(),
		'plugin_monitor_dashboards'     => monitor_dashboards_table_data(),
	];

	foreach ($tables as $table => $table_data) {
		if (db_table_exists($table)) {
			db_update_table($table, $table_data);
		} else {
			api_plugin_db_table_create('monitor', $table, $table_data);
		}
	}
}

/**
 * Drops the tables this plugin owns that hold transient monitoring state
 * (notification/reboot history and uptime). The user-defined dashboards
 * table is intentionally left in place. Called from
 * plugin_monitor_uninstall().
 *
 * @return void
 */
function monitor_drop_tables() {
	db_execute('DROP TABLE IF EXISTS plugin_monitor_notify_history');
	db_execute('DROP TABLE IF EXISTS plugin_monitor_reboot_history');
	db_execute('DROP TABLE IF EXISTS plugin_monitor_uptime');
}
