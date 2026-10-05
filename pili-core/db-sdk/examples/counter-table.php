<?php
/**
 * Example: daily / keyed counter table.
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
	'pili_example_counters',
	array(
		'version' => 1,
		'primary' => 'id',
		'columns' => array(
			'id'         => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
			'counter_key'=> 'varchar(191) NOT NULL',
			'day_ymd'    => 'char(8) NOT NULL',
			'hits'       => 'bigint(20) unsigned NOT NULL DEFAULT 0',
			'updated_at' => 'datetime NOT NULL',
		),
		'indexes' => array(
			'uniq_key_day' => array(
				'unique'  => true,
				'columns' => array( 'counter_key', 'day_ymd' ),
			),
		),
	)
);
