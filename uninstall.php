<?php
/**
 * Uninstall Apogee Watcher for WordPress: remove options and transients.
 *
 * @package ApogeeWatcher
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'apogee_wp_watcher_settings' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_apogee_wp_watcher_%' OR option_name LIKE '_transient_timeout_apogee_wp_watcher_%'"
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'apogee_wp_watcher_alerts_dismissed'"
);
