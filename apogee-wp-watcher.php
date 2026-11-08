<?php
/**
 * Plugin Name:       Apogee Watcher for WordPress
 * Plugin URI:        https://apogeewatcher.com/?utm_source=wordpress&utm_medium=plugin&utm_campaign=plugin_header
 * Description:       Connect your WordPress site to Apogee Watcher. See Core Web Vitals and Lighthouse scores in the dashboard and get notified when performance drops.
 * Version:           0.1.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Apogee Information Systems
 * Author URI:        https://apogee.lu/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       apogee-wp-watcher
 * Domain Path:       /languages
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

define( 'APOGEE_WP_WATCHER_VERSION', '0.1.1' );
define( 'APOGEE_WP_WATCHER_FILE', __FILE__ );
define( 'APOGEE_WP_WATCHER_PATH', plugin_dir_path( __FILE__ ) );
define( 'APOGEE_WP_WATCHER_URL', plugin_dir_url( __FILE__ ) );
define( 'APOGEE_WP_WATCHER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Fixed production API origin (no trailing slash). Not overridable via wp-config.
 */
define( 'APOGEE_WP_WATCHER_API_BASE', 'https://apogeewatcher.com' );

require_once APOGEE_WP_WATCHER_PATH . 'includes/class-apogee-watcher-autoloader.php';
Apogee_Watcher_Autoloader::register();

/**
 * Bootstrap the plugin.
 */
function apogee_wp_watcher(): Apogee_Watcher_Plugin {
	return Apogee_Watcher_Plugin::instance();
}

add_action(
	'plugins_loaded',
	static function () {
		apogee_wp_watcher()->init();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		Apogee_Watcher_Plugin::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		Apogee_Watcher_Plugin::deactivate();
	}
);
