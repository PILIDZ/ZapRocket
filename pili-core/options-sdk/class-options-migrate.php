<?php
/**
 * 旧单行 options → 按域拆分迁移（PILI options-sdk 模板）。
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Options migrate.
 */
final class PILI_Options_Migrate {

	const BACKUP_OPTION = 'pili_old_backup';
	const LOCK_KEY      = 'pili_migrate_lock';

	/**
	 * @return string
	 */
	public static function status_option_key() {
		return function_exists( 'pili_migrate_status_option' ) ? pili_migrate_status_option() : 'pili__migrate_status';
	}

	/**
	 * @return string
	 */
	public static function dual_option_key() {
		return function_exists( 'pili_migrate_dual_option' ) ? pili_migrate_dual_option() : 'pili__migrate_dual_write';
	}

	/**
	 * Resolve options unique (same logic for register / run / notice).
	 *
	 * Order: PILI_DEMO_OPTION_ID → PILI_THEME_DEMO_OPTION_ID → filter.
	 * LOCK/BACKUP instance keys stay global (Batch C/E).
	 *
	 * @return string
	 */
	public static function resolve_option_unique() {
		$unique = '';
		if ( defined( 'PILI_DEMO_OPTION_ID' ) ) {
			$unique = (string) PILI_DEMO_OPTION_ID;
		} elseif ( defined( 'PILI_THEME_DEMO_OPTION_ID' ) ) {
			$unique = (string) PILI_THEME_DEMO_OPTION_ID;
		}
		/**
		 * Options unique id that Path A migrates for this instance.
		 *
		 * @param string $unique Unique.
		 */
		return (string) apply_filters( 'pili_migrate_option_unique', $unique );
	}

	/**
	 * Menu page slug for migrate failure notice (derived from unique).
	 *
	 * @return string
	 */
	public static function resolve_notice_page() {
		$unique = self::resolve_option_unique();
		$page   = '' !== $unique ? sanitize_key( str_replace( '_options', '', $unique ) ) : '';
		/**
		 * Admin page slug that shows migrate failure notice.
		 *
		 * @param string $page   Page slug.
		 * @param string $unique Options unique.
		 */
		return (string) apply_filters( 'pili_migrate_notice_page', $page, $unique );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_admin_notice' ) );

		$unique = self::resolve_option_unique();
		if ( '' !== $unique ) {
			add_action( 'load-toplevel_page_' . sanitize_key( str_replace( '_options', '', $unique ) ), array( __CLASS__, 'maybe_run' ), 5 );
			add_filter( 'pre_option_' . $unique, array( __CLASS__, 'filter_pre_option' ), 10, 1 );
			add_filter( 'pre_update_option_' . $unique, array( __CLASS__, 'filter_pre_update' ), 10, 2 );
		}
	}

	/**
	 * @return string pending|running|done|failed
	 */
	public static function status() {
		$s = (string) get_option( self::status_option_key(), 'pending' );
		// Legacy global key (pre-H4).
		if ( 'pending' === $s ) {
			$legacy = (string) get_option( 'pili_migrate_status', '' );
			if ( in_array( $legacy, array( 'pending', 'running', 'done', 'failed' ), true ) && 'pending' !== $legacy ) {
				$s = $legacy;
			}
		}
		return in_array( $s, array( 'pending', 'running', 'done', 'failed' ), true ) ? $s : 'pending';
	}

	/**
	 * @return bool
	 */
	public static function is_done() {
		return 'done' === self::status();
	}

	/**
	 * 是否仍双写旧行。
	 *
	 * @return bool
	 */
	public static function dual_write_enabled() {
		if ( ! self::is_done() ) {
			return false;
		}
		// 0-用户首发：默认关闭运行时双写；开发自测可把 option 设为 1 或 define PILIPOST_MIGRATE_DUAL_WRITE。
		if ( defined( 'PILIPOST_MIGRATE_DUAL_WRITE' ) ) {
			return (bool) PILIPOST_MIGRATE_DUAL_WRITE;
		}
		$flag = get_option( self::dual_option_key(), '0' );
		return ( '1' === (string) $flag || true === $flag || 1 === $flag );
	}

	/**
	 * 进入设置页时按需迁移。
	 *
	 * @return void
	 */
	public static function maybe_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = self::status();
		if ( 'done' === $status ) {
			return;
		}
		if ( 'running' === $status && get_transient( self::LOCK_KEY ) ) {
			return;
		}
		self::run();
	}

	/**
	 * 执行一次迁移尝试。
	 *
	 * @return bool
	 */
	public static function run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$unique = self::resolve_option_unique();
		if ( '' === $unique ) {
			update_option( self::status_option_key(), 'failed', false );
			return false;
		}
		if ( get_transient( self::LOCK_KEY ) ) {
			return false;
		}
		set_transient( self::LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );
		update_option( self::status_option_key(), 'running', false );

		$backup = get_option( $unique, array() );
		if ( ! is_array( $backup ) ) {
			$backup = array();
		}
		update_option( self::BACKUP_OPTION, $backup, false );

		PILI_Options_Domain_Store::flush_cache();
		PILI_Options_Domain_Store::split_and_save( $backup, true );
		$merged = PILI_Options_Domain_Store::merge_all();

		if ( PILI_Options_Domain_Store::normalize( $merged ) !== PILI_Options_Domain_Store::normalize( $backup ) ) {
			update_option( self::status_option_key(), 'failed', false );
			delete_transient( self::LOCK_KEY );
			return false;
		}

		update_option( self::dual_option_key(), '1', false );
		update_option( self::status_option_key(), 'done', false );
		delete_transient( self::LOCK_KEY );
		return true;
	}

	/**
	 * 迁移完成后 get_option(legacy) 返回合并域。
	 *
	 * @param mixed $pre Pre.
	 * @return mixed
	 */
	public static function filter_pre_option( $pre ) {
		if ( ! self::is_done() ) {
			return $pre;
		}
		// 避免递归：读域 option 时不要再进本过滤。
		return PILI_Options_Domain_Store::merge_all();
	}

	/**
	 * Runtime update_option → split 时是否整包替换（空域也写入）。
	 *
	 * 默认 false（T-SAVE-1 半包防护）。重置当前页 / 导入等须删键或空域覆盖时，
	 * 在 save_options 前设 `$GLOBALS['pili_options_split_replace_all']=true`。
	 * Migrate::run() 仍直接 `split_and_save(…, true)`，不经本旗。
	 *
	 * @return bool
	 */
	public static function consume_replace_all_flag() {
		$flag = ! empty( $GLOBALS['pili_options_split_replace_all'] );
		unset( $GLOBALS['pili_options_split_replace_all'] );
		return $flag;
	}

	/**
	 * 迁移完成后写拆分；双写期同时回写旧行。
	 *
	 * @param mixed $value     New value.
	 * @param mixed $old_value Old value.
	 * @return mixed
	 */
	public static function filter_pre_update( $value, $old_value ) {
		if ( ! self::is_done() ) {
			return $value;
		}
		if ( ! is_array( $value ) ) {
			$value = array();
		}
		PILI_Options_Domain_Store::flush_cache();
		// 日常 AJAX 分区/脏字段保存：半包不得清空未触及域（T-SAVE-1）。
		$replace_all = self::consume_replace_all_flag();
		$ok          = PILI_Options_Domain_Store::split_and_save( $value, $replace_all );

		if ( class_exists( 'PILI_Options_Save_Budget' ) && PILI_Options_Save_Budget::tripped() ) {
			$GLOBALS[ pili_options_write_ok_key() ] = false;
			return is_array( $old_value ) ? $old_value : array();
		}
		if ( ! $ok ) {
			$GLOBALS[ pili_options_write_ok_key() ] = false;
			return is_array( $old_value ) ? $old_value : array();
		}

		$GLOBALS[ pili_options_write_ok_key() ] = true;

		if ( self::dual_write_enabled() ) {
			return $value;
		}
		// 结束双写后：保持旧行不变（返回旧值使 update_option 视为无变化）。
		return is_array( $old_value ) ? $old_value : array();
	}

	/**
	 * 后台提示迁移失败。
	 *
	 * @return void
	 */
	public static function maybe_admin_notice() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$expect = self::resolve_notice_page();
		if ( '' === $expect ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $expect !== $page ) {
			return;
		}
		if ( 'failed' !== self::status() ) {
			return;
		}
		$retry_url = admin_url( 'admin.php?page=' . rawurlencode( $expect ) . '&pili_migrate_retry=1' );
		echo '<div class="notice notice-error"><p>';
		echo function_exists( 'pili_esc_html__' ) ? pili_esc_html__( '配置迁移失败，请重试。' ) : esc_html( '配置迁移失败，请重试。' );
		$retry_label = function_exists( 'pili_esc_html__' ) ? pili_esc_html__( '重试' ) : esc_html( '重试' );
		echo ' <a href="' . esc_url( $retry_url ) . '">' . $retry_label . '</a>';
		echo '</p></div>';

		if ( ! empty( $_GET['pili_migrate_retry'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option( self::status_option_key(), 'pending', false );
			self::run();
		}
	}
}
