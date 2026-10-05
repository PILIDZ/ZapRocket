<?php
/**
 * Unified logger. Channels persist locally; hook reserved for a global log UI.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured log writer.
 */
final class ZapRocket_Logger {

	const CHANNEL_OSS_MIGRATE = 'oss_migrate';

	/**
	 * @param string               $channel Channel slug.
	 * @param string               $level   debug|info|warn|error.
	 * @param string               $message User-facing text (already translated).
	 * @param array<string,mixed>  $context Extra (never secrets).
	 * @return void
	 */
	public static function write( $channel, $level, $message, array $context = array() ) {
		$channel = sanitize_key( (string) $channel );
		$level   = sanitize_key( (string) $level );
		if ( ! in_array( $level, array( 'debug', 'info', 'warn', 'error' ), true ) ) {
			$level = 'info';
		}
		$message = wp_strip_all_tags( (string) $message );
		if ( '' === $channel || '' === $message ) {
			return;
		}
		/**
		 * Global log sink (future admin page).
		 *
		 * @param string               $channel Channel.
		 * @param string               $level   Level.
		 * @param string               $message Message.
		 * @param array<string,mixed>  $context Context.
		 */
		do_action( 'zaprocket_log', $channel, $level, $message, $context );

		if ( self::CHANNEL_OSS_MIGRATE === $channel && class_exists( 'ZapRocket_Oss_Migrate', false ) ) {
			ZapRocket_Oss_Migrate::persist_log( $level, $message, $context );
		}
	}
}
