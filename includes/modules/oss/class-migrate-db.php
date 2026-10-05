<?php
/**
 * One-click migrate tables.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema via pili db-sdk.
 */
final class ZapRocket_Oss_Migrate_Db {

	const TASK = 'zaprocket_oss_mig';
	const ITEM = 'zaprocket_oss_mig_item';
	const BACK = 'zaprocket_oss_mig_backup';
	const LOG  = 'zaprocket_oss_mig_log';

	/**
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'pili_db_register_table' ) ) {
			return;
		}
		pili_db_register_table(
			self::TASK,
			array(
				'version' => 1,
				'primary' => 'id',
				'columns' => array(
					'id'               => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'status'           => "varchar(32) NOT NULL DEFAULT 'queued'",
					'settings_json'    => 'longtext NULL',
					'last_scanned_id'  => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'scanned'          => 'int(11) NOT NULL DEFAULT 0',
					'uploaded'         => 'int(11) NOT NULL DEFAULT 0',
					'replaced'         => 'int(11) NOT NULL DEFAULT 0',
					'failed'           => 'int(11) NOT NULL DEFAULT 0',
					'batch_size'       => 'int(11) NOT NULL DEFAULT 1',
					'last_tick_at'     => 'datetime NULL',
					'last_tick_ms'     => 'int(11) NOT NULL DEFAULT 0',
					'locked_until'     => 'datetime NULL',
					'created_by'       => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'created_at'       => 'datetime NOT NULL',
					'finished_at'      => 'datetime NULL',
				),
				'indexes' => array(
					'idx_status' => array( 'status' ),
				),
			)
		);
		pili_db_register_table(
			self::ITEM,
			array(
				'version' => 1,
				'primary' => 'id',
				'columns' => array(
					'id'                    => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'task_id'               => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'attachment_id'         => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'local_rel'             => "varchar(255) NOT NULL DEFAULT ''",
					'object_key'            => "varchar(500) NOT NULL DEFAULT ''",
					'public_url'            => "varchar(500) NOT NULL DEFAULT ''",
					'status'                => "varchar(32) NOT NULL DEFAULT 'pending'",
					'phase'                 => "varchar(16) NOT NULL DEFAULT 'scan'",
					'error_i18n'            => 'text NULL',
					'processing_started_at' => 'datetime NULL',
					'replace_cursor'        => 'bigint(20) unsigned NOT NULL DEFAULT 0',
				),
				'indexes' => array(
					'idx_task_status' => array( 'task_id', 'status' ),
					'idx_task_att'    => array( 'task_id', 'attachment_id' ),
				),
			)
		);
		pili_db_register_table(
			self::BACK,
			array(
				'version' => 1,
				'primary' => 'id',
				'columns' => array(
					'id'          => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'task_id'     => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'post_id'     => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'field_key'   => "varchar(64) NOT NULL DEFAULT 'post_content'",
					'original'    => 'longtext NOT NULL',
					'replaced_at' => 'datetime NOT NULL',
				),
				'indexes' => array(
					'idx_task_post' => array( 'task_id', 'post_id', 'field_key' ),
				),
			)
		);
		pili_db_register_table(
			self::LOG,
			array(
				'version' => 1,
				'primary' => 'id',
				'columns' => array(
					'id'         => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
					'task_id'    => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'level'      => "varchar(16) NOT NULL DEFAULT 'info'",
					'message'    => 'text NOT NULL',
					'created_at' => 'datetime NOT NULL',
				),
				'indexes' => array(
					'idx_task_id' => array( 'task_id', 'id' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function install() {
		self::register();
		if ( function_exists( 'pili_db_install' ) ) {
			pili_db_install( self::TASK );
			pili_db_install( self::ITEM );
			pili_db_install( self::BACK );
			pili_db_install( self::LOG );
		}
		if ( function_exists( 'pili_db_upgrade' ) ) {
			pili_db_upgrade();
		}
	}

	/**
	 * @param string $slug Slug.
	 * @return string
	 */
	public static function table( $slug ) {
		if ( function_exists( 'pili_db_table' ) ) {
			$name = pili_db_table( $slug );
			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}
		global $wpdb;
		return $wpdb->prefix . $slug;
	}

	/**
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function exists( $slug ) {
		global $wpdb;
		$table = self::table( $slug );
		if ( '' === $table || ! preg_match( '/^[A-Za-z0-9_\.]+$/', $table ) ) {
			return false;
		}
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return is_string( $found ) && $found === $table;
	}
}
