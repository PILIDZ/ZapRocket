<?php
/**
 * Example: job / queue table (expires_at optional for timeout claims).
 *
 * Host Cron may purge completed rows older than expires_at / completed_at.
 * Framework does not ship a cleaner Cron.
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
	'pili_example_jobs',
	array(
		'version' => 1,
		'primary' => 'id',
		'columns' => array(
			'id'           => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
			'status'       => "varchar(20) NOT NULL DEFAULT 'pending'",
			'payload'      => 'longtext NULL',
			'attempts'     => 'smallint(5) unsigned NOT NULL DEFAULT 0',
			'created_at'   => 'datetime NOT NULL',
			'updated_at'   => 'datetime NOT NULL',
			'expires_at'   => 'datetime NULL',
			'completed_at' => 'datetime NULL',
		),
		'indexes' => array(
			'idx_status_created' => array(
				'columns' => array( 'status', 'created_at' ),
			),
			'idx_expires'        => array( 'expires_at' ),
		),
	)
);
