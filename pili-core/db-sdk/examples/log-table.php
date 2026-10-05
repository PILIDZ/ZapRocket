<?php
/**
 * Example: audit / activity log table (retention required).
 *
 * Host: require this file → pili_db_register_table → pili_db_install on activation.
 * Demo does NOT auto-install.
 *
 * Cleanup: host Cron + DELETE … WHERE retain_until < NOW() LIMIT n (framework has no built-in Cron).
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pili_db_register_table' ) ) {
	return;
}

pili_db_register_table(
	'pili_example_logs',
	array(
		'version' => 1,
		'primary' => 'id',
		'columns' => array(
			'id'           => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
			'level'        => "varchar(16) NOT NULL DEFAULT 'info'",
			'message'      => 'text NOT NULL',
			'context_json' => 'longtext NULL',
			'created_at'   => 'datetime NOT NULL',
			'retain_until' => 'datetime NOT NULL',
		),
		'indexes' => array(
			'idx_created' => array( 'created_at' ),
			'idx_retain'  => array( 'retain_until' ),
			'idx_level'   => array( 'level' ),
		),
	)
);
