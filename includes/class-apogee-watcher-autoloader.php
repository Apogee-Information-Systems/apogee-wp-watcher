<?php
/**
 * Simple PSR-4-ish autoloader for Apogee_Watcher_* classes.
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Autoloader
 */
class Apogee_Watcher_Autoloader {

	/**
	 * Register autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class Class name.
	 */
	public static function load( string $class ): void {
		if ( 0 !== strpos( $class, 'Apogee_Watcher_' ) ) {
			return;
		}

		$relative = strtolower( str_replace( '_', '-', substr( $class, strlen( 'Apogee_' ) ) ) );
		$file     = APOGEE_WP_WATCHER_PATH . 'includes/class-' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
