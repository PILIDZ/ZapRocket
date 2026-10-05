<?php
/**
 * Asset handle / i18n helpers (instance-aware).
 *
 * @package PILI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current text domain from Config.
 *
 * @return string
 */
function pili_text_domain() {
	$d = 'pili';
	if ( class_exists( 'PILI_Config', false ) ) {
		$cfg = PILI_Config::get( 'text_domain', 'pili' );
		if ( is_string( $cfg ) && '' !== $cfg ) {
			$d = $cfg;
		}
	}
	return $d;
}

/**
 * Translate with instance text domain (msgid = 中文，见 docs/15).
 *
 * Uses Translations::translate() instead of __() so WordPress.org Plugin Check
 * does not flag NonSingularStringLiteralText / NonSingularStringLiteralDomain.
 * Call sites still pass Chinese string literals into pili__().
 *
 * @param string $text Text (Chinese msgid).
 * @return string
 */
function pili__( $text ) {
	$text = (string) $text;
	if ( '' === $text || ! function_exists( 'get_translations_for_domain' ) ) {
		return $text;
	}
	$domain       = pili_text_domain();
	$translations = get_translations_for_domain( $domain );
	$translation  = $translations->translate( $text );
	/**
	 * Filters text with its translation (same hooks as core translate()).
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 */
	$translation = apply_filters( 'gettext', $translation, $text, $domain );
	/**
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 */
	$translation = apply_filters( "gettext_{$domain}", $translation, $text, $domain );
	return $translation;
}

/**
 * @param string $text Text.
 * @return string
 */
function pili_esc_html__( $text ) {
	return esc_html( pili__( $text ) );
}

/**
 * @param string $text Text.
 * @return string
 */
function pili_esc_attr__( $text ) {
	return esc_attr( pili__( $text ) );
}

/**
 * Script/style handle: {asset_prefix}-{suffix}.
 *
 * @param string $suffix E.g. framework, dialog, field-chart.
 * @return string
 */
function pili_asset_handle( $suffix ) {
	$prefix = 'pili';
	if ( class_exists( 'PILI_Config', false ) ) {
		$cfg = PILI_Config::get( 'asset_prefix', 'pili' );
		if ( is_string( $cfg ) && '' !== sanitize_key( $cfg ) ) {
			$prefix = sanitize_key( $cfg );
		}
	}
	$suffix = sanitize_key( (string) $suffix );
	return $prefix . ( '' !== $suffix ? '-' . $suffix : '' );
}

/**
 * Migrate status option name per instance prefix.
 *
 * @return string
 */
function pili_migrate_status_option() {
	$prefix = 'pili__';
	if ( class_exists( 'PILI_Config', false ) ) {
		$cfg = PILI_Config::get( 'option_prefix', 'pili__' );
		if ( is_string( $cfg ) && '' !== $cfg ) {
			$prefix = $cfg;
		}
	}
	return $prefix . 'migrate_status';
}

/**
 * Dual-write flag option name.
 *
 * @return string
 */
function pili_migrate_dual_option() {
	$prefix = 'pili__';
	if ( class_exists( 'PILI_Config', false ) ) {
		$cfg = PILI_Config::get( 'option_prefix', 'pili__' );
		if ( is_string( $cfg ) && '' !== $cfg ) {
			$prefix = $cfg;
		}
	}
	return $prefix . 'migrate_dual_write';
}

/**
 * CamelCase object name from asset_prefix + PascalCase suffix.
 * e.g. asset_prefix=pili-demo + DialogDefaults → piliDemoDialogDefaults
 *
 * @param string $suffix PascalCase suffix (DialogDefaults, Repeater, …).
 * @return string
 */
function pili_localize_object_name( $suffix ) {
	$prefix = 'pili';
	if ( class_exists( 'PILI_Config', false ) ) {
		$cfg = PILI_Config::get( 'asset_prefix', 'pili' );
		if ( is_string( $cfg ) && '' !== sanitize_key( $cfg ) ) {
			$prefix = sanitize_key( $cfg );
		}
	}
	$parts = explode( '-', $prefix );
	$camel = array_shift( $parts );
	foreach ( $parts as $p ) {
		$camel .= ucfirst( $p );
	}
	$suffix = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $suffix );
	return $camel . $suffix;
}

/**
 * Host JS gettext runtime (window.pilipost__ / window.pili__).
 * ZapRocket and pilipost each implement enqueue_js_runtime(); framework JS 读 pilipost__。
 *
 * @return string Script handle, or empty.
 */
function pili_enqueue_js_i18n_runtime() {
	$classes = array( '\\Pilipost_I18n', 'ZapRocket_I18n' );
	foreach ( $classes as $class ) {
		if ( ! class_exists( $class, false ) || ! is_callable( array( $class, 'enqueue_js_runtime' ) ) ) {
			continue;
		}
		try {
			$handle = call_user_func( array( $class, 'enqueue_js_runtime' ) );
		} catch ( \Throwable $e ) {
			continue;
		}
		if ( is_string( $handle ) && '' !== $handle ) {
			return $handle;
		}
	}
	return '';
}

/**
 * Localize a bag onto PILI.setBag (no window.xun* globals).
 * Passes Config instance_id so dual-enqueue does not mis-bucket via overwritten piliRuntime.
 *
 * @param string               $handle  Script handle.
 * @param string               $bag_key Bag key for PILI.bag().
 * @param array<string,mixed>  $data    Data.
 * @return void
 */
function pili_localize_bag( $handle, $bag_key, $data ) {
	// Preserve camelCase (dateL10n / dialogDefaults). sanitize_key() lowercases and breaks PILI.bag().
	$bag_key = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $bag_key );
	if ( '' === $bag_key ) {
		return;
	}
	$tmp = '_piliBag_' . $bag_key;
	wp_localize_script( $handle, $tmp, $data );
	$instance_id = 'default';
	if ( class_exists( 'PILI_Config', false ) ) {
		$cfg = PILI_Config::get( 'instance_id', 'default' );
		if ( is_string( $cfg ) && '' !== $cfg ) {
			$instance_id = $cfg;
		}
	}
	$js = sprintf(
		'window.PILI&&PILI.setBag&&PILI.setBag(%s,window.%s,%s);',
		wp_json_encode( $bag_key ),
		$tmp,
		wp_json_encode( $instance_id )
	);
	wp_add_inline_script( $handle, $js, 'after' );
}

/**
 * Options-page runtime localize: write optionId buckets + single-instance aliases.
 *
 * @param string                    $handle   Script handle.
 * @param string                    $option_id Option unique / optionId.
 * @param array<string,mixed>       $ajax     piliAjax payload.
 * @param array<string,mixed>       $runtime  piliRuntime payload.
 * @param array<string,mixed>|null  $lazy     Optional { sections: ?, assets: ? }.
 * @return void
 */
function pili_localize_options_runtime( $handle, $option_id, array $ajax, array $runtime, $lazy = null ) {
	$option_id = sanitize_key( (string) $option_id );
	if ( '' === $option_id ) {
		return;
	}

	wp_localize_script( $handle, 'piliAjax', $ajax );
	wp_localize_script( $handle, 'piliRuntime', $runtime );

	$lazy_payload = array(
		'sections' => null,
		'assets'   => null,
	);
	if ( is_array( $lazy ) ) {
		if ( isset( $lazy['sections'] ) && is_array( $lazy['sections'] ) ) {
			$lazy_payload['sections'] = $lazy['sections'];
			wp_localize_script( $handle, 'piliLazySections', $lazy['sections'] );
		}
		if ( isset( $lazy['assets'] ) && is_array( $lazy['assets'] ) ) {
			$lazy_payload['assets'] = $lazy['assets'];
			wp_localize_script( $handle, 'piliLazyAssets', $lazy['assets'] );
		}
	}

	$js = sprintf(
		'(function(P){P.ajaxById=P.ajaxById||{};P.runtimeById=P.runtimeById||{};P.lazyById=P.lazyById||{};var id=%s;P.ajaxById[id]=%s;P.runtimeById[id]=%s;P.lazyById[id]=%s;})(window.PILI=window.PILI||{});',
		wp_json_encode( $option_id ),
		wp_json_encode( $ajax ),
		wp_json_encode( $runtime ),
		wp_json_encode( $lazy_payload )
	);
	wp_add_inline_script( $handle, $js, 'after' );
}

/**
 * Whether to skip global wp_enqueue_media on PILI options pages (weak-host).
 * Gallery/media fields still call wp_enqueue_media() in their enqueue().
 *
 * @return bool
 */
function pili_skip_options_page_media_enqueue() {
	/**
	 * Skip framework-page media preload.
	 *
	 * @param bool $skip Skip.
	 */
	return (bool) apply_filters( 'pili_skip_options_page_media_enqueue', false );
}
