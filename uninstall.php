<?php
/**
 * Uninstall Apogee Watcher: Automated PageSpeed Monitoring.
 *
 * Removes options, update/API transients, and per-user dismiss meta.
 *
 * @package ApogeeWatcher
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'apogee_watcher_settings' );

// Per-user alert dismiss flag (all users).
delete_metadata( 'user', 0, 'apogee_watcher_alerts_dismissed', '', true );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_apogee_watcher_%' OR option_name LIKE '_transient_timeout_apogee_watcher_%'"
);
