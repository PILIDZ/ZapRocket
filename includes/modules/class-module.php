<?php
/**
 * Abstract frontend/admin feature module.
 *
 * @package ZapRocket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base module: P1/P2/P3 subclasses hook via hooks().
 */
abstract class ZapRocket_Module {

	/**
	 * Module id (slim / speed / database …).
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Whether this module should register hooks for the current request.
	 *
	 * @return bool
	 */
	public function enabled() {
		if ( ZapRocket_Context::should_skip() ) {
			return false;
		}
		return true;
	}

	/**
	 * Register WordPress hooks. Override in subclasses.
	 *
	 * @return void
	 */
	abstract public function hooks();

	/**
	 * Boot if enabled.
	 *
	 * @return void
	 */
	public function boot() {
		if ( ! $this->enabled() ) {
			return;
		}
		$this->hooks();
	}
}
