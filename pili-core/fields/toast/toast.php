<?php

namespace Pili\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field: toast
 *
 * 可复用 SaaS Toast（只读展示壳，不参与存储）。
 * - 默认左侧 PiliBot 表情（可 bot=>false 回退 Remix 图标）
 * - JS API：window.PiliXunToast.show / success / error / warning / info / dismiss / clear
 */
class PILI_Field_toast extends PILI_Fields {

	/**
	 * @return array<string,string>
	 */
	public static function type_map() {
		return array(
			'success' => 'pili-toast--success',
			'error'   => 'pili-toast--error',
			'warning' => 'pili-toast--warning',
			'info'    => 'pili-toast--info',
		);
	}

	/**
	 * 语义别名 → type。
	 *
	 * @param string $type Type or alias.
	 * @return string
	 */
	public static function normalize_type( $type ) {
		$key     = sanitize_key( (string) $type );
		$aliases = array(
			'ok'      => 'success',
			'done'    => 'success',
			'danger'  => 'error',
			'fail'    => 'error',
			'warn'    => 'warning',
			'caution' => 'warning',
			'default' => 'info',
			'neutral' => 'info',
			'primary' => 'info',
		);
		if ( isset( $aliases[ $key ] ) ) {
			$key = $aliases[ $key ];
		}
		$map = self::type_map();
		return isset( $map[ $key ] ) ? $key : 'info';
	}

	/**
	 * 默认图标（Remix Icon class）—— bot 关闭时的回退。
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function default_icon( $type ) {
		$type = self::normalize_type( $type );
		$map  = array(
			'success' => 'ri-checkbox-circle-fill',
			'error'   => 'ri-error-warning-fill',
			'warning' => 'ri-alert-fill',
			'info'    => 'ri-information-fill',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : $map['info'];
	}

	/**
	 * Toast type → PiliBot 状态。
	 *
	 * @return array<string,string>
	 */
	public static function bot_state_map() {
		if ( class_exists( __NAMESPACE__ . '\\PILI_Field_pilibot' ) ) {
			$map = PILI_Field_pilibot::toast_state_map();
		} else {
			$map = array(
				'success'   => 'success',
				'error'     => 'error',
				'warning'   => 'rate_limit',
				'info'      => 'streaming',
				'thinking'  => 'thinking',
				'working'   => 'working',
				'streaming' => 'streaming',
				'searching' => 'searching',
				'listening' => 'listening',
				'approval'  => 'approval',
				'idle'      => 'idle',
			);
		}
		// Toast 加载提示统一弹跳，避免 thinking 三点脸
		$map['loading']  = 'bounce';
		$map['thinking'] = 'bounce';
		$map['working']  = 'bounce';
		$map['bounce']   = 'bounce';
		return $map;
	}

	/**
	 * 挂载容器 HTML（通常由 JS 确保存在；字段声明时可预渲染）。
	 *
	 * @param array<string,mixed> $args position, id, class.
	 * @return string
	 */
	public static function render_host_html( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'       => 'pili-toast-host',
				'position' => 'bottom-center',
				'class'    => '',
			)
		);

		$id = sanitize_html_class( (string) $args['id'] );
		if ( '' === $id ) {
			$id = 'pili-toast-host';
		}
		$position = sanitize_key( (string) $args['position'] );
		$allowed  = array( 'top-right', 'top-left', 'top-center', 'bottom-right', 'bottom-left', 'bottom-center' );
		if ( ! in_array( $position, $allowed, true ) ) {
			$position = 'bottom-center';
		}
		$extra = trim( (string) $args['class'] );
		$cls   = trim( 'pili-toast-host pili-toast-host--' . $position . ' ' . $extra );

		return '<div id="' . esc_attr( $id ) . '" class="' . esc_attr( $cls ) . '" data-pili-toast-host="1" data-position="' . esc_attr( $position ) . '" aria-live="polite" aria-relevant="additions"></div>';
	}

	public function render() {
		$args = wp_parse_args(
			$this->field,
			array(
				'position' => 'bottom-center',
				'mount'    => true,
				'bot'      => true,
			)
		);

		$field_id = isset( $this->field['id'] ) ? sanitize_key( (string) $this->field['id'] ) : 'pili-toast-host';

		echo $this->field_before();

		if ( ! empty( $args['mount'] ) ) {
			echo self::render_host_html(
				array(
					'id'       => $field_id,
					'position' => (string) $args['position'],
				)
			);
		}

		echo $this->field_after();
	}

	public function validate( $value ) {
		return $this->value;
	}

	/**
	 * 确保 PiliBot 核心引擎已入队（toast 默认依赖）。
	 */
	public static function enqueue_bot_core() {
		$pilibot_file = trailingslashit( (string) PILI_Setup::$dir ) . 'fields/pilibot/pilibot.php';
		if ( is_readable( $pilibot_file ) && ! class_exists( __NAMESPACE__ . '\\PILI_Field_pilibot', false ) ) {
			require_once $pilibot_file;
		}
		if ( class_exists( __NAMESPACE__ . '\\PILI_Field_pilibot' ) && method_exists( __NAMESPACE__ . '\\PILI_Field_pilibot', 'enqueue_core' ) ) {
			PILI_Field_pilibot::enqueue_core();
		}
	}

	public function enqueue() {
		$use_bot = ! isset( $this->field['bot'] ) || ! empty( $this->field['bot'] );
		$ver     = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : '1.0';

		if ( $use_bot ) {
			self::enqueue_bot_core();
		}

		$handle         = pili_asset_handle( 'field-toast' );
		$pilibot_style  = pili_asset_handle( 'field-pilibot' );
		$pilibot_engine = pili_asset_handle( 'pilibot-engine' );
		$style_deps     = array();
		if ( $use_bot && wp_style_is( $pilibot_style, 'registered' ) ) {
			$style_deps[] = $pilibot_style;
		}

		wp_enqueue_style(
			$handle,
			PILI_Setup::$url . '/assets/css/fields/toast.css',
			$style_deps,
			$ver
		);

		$script_deps = array( 'jquery' );
		$fw          = pili_asset_handle( 'framework' );
		if ( $fw && ! wp_script_is( $fw, 'registered' ) && ! wp_script_is( $fw, 'enqueued' ) ) {
			$fw_ver = defined( 'PILI_CORE_VERSION' ) ? PILI_CORE_VERSION : $ver;
			$fw_js  = trailingslashit( (string) PILI_Setup::$dir ) . 'assets/js/pili-framework.min.js';
			if ( is_readable( $fw_js ) ) {
				$fw_ver .= '.' . (string) filemtime( $fw_js );
			}
			wp_register_script( $fw, PILI_Setup::$url . '/assets/js/pili-framework.min.js', array( 'jquery' ), $fw_ver, true );
		}
		if ( $fw && ( wp_script_is( $fw, 'registered' ) || wp_script_is( $fw, 'enqueued' ) ) ) {
			$script_deps[] = $fw;
		}
		if ( $use_bot && wp_script_is( $pilibot_engine, 'registered' ) ) {
			$script_deps[] = $pilibot_engine;
		}

		$toast_js  = trailingslashit( (string) PILI_Setup::$dir ) . 'assets/js/fields/toast.js';
		$toast_ver = is_readable( $toast_js ) ? (string) filemtime( $toast_js ) : $ver;

		wp_enqueue_script(
			$handle,
			PILI_Setup::$url . '/assets/js/fields/toast.js',
			$script_deps,
			$toast_ver,
			true
		);

		pili_localize_bag(
			$handle,
			'toast',
			array(
				'defaults' => array(
					'duration'    => 3200,
					'position'    => 'bottom-center',
					'dismissible' => true,
					'max'         => 1,
					'queue'       => true,
					'bot'         => $use_bot,
					'botSize'     => isset( $this->field['bot_size'] ) ? (int) $this->field['bot_size'] : 56,
				),
				'strings'  => array(
					'close' => pili__( '关闭' ),
					'success' => pili__( '成功' ),
					'error' => pili__( '错误' ),
					'warning' => pili__( '警告' ),
					'info' => pili__( '提示' ),
				),
				'icons'       => array(
					'success' => self::default_icon( 'success' ),
					'error'   => self::default_icon( 'error' ),
					'warning' => self::default_icon( 'warning' ),
					'info'    => self::default_icon( 'info' ),
				),
				'botStateMap' => self::bot_state_map(),
			)
		);
	}
}
