<?php
/**
 * Main plugin orchestrator.
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Plugin
 */
class Apogee_Watcher_Plugin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Connection / tokens store.
	 *
	 * @var Apogee_Watcher_Connection
	 */
	public $connection;

	/**
	 * HTTP API client.
	 *
	 * @var Apogee_Watcher_Api_Client
	 */
	public $api;

	/**
	 * Instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot hooks.
	 */
	public function init(): void {
		self::migrate_from_legacy_slug();
		$this->maybe_load_textdomain();

		$this->connection = new Apogee_Watcher_Connection();
		$this->api        = new Apogee_Watcher_Api_Client( $this->connection );

		( new Apogee_Watcher_Privacy() )->hooks();

		// Outside is_admin(): cron / background update checks must see this filter.
		// WordPress.org builds omit Update URI so core will not call this updater.
		( new Apogee_Watcher_Updater() )->hooks();

		if ( is_admin() ) {
			$tracked = new Apogee_Watcher_Tracked_Pages( $this->connection, $this->api );
			$tracked->hooks();
			( new Apogee_Watcher_Settings( $this->connection, $this->api, $tracked ) )->hooks();
			( new Apogee_Watcher_Dashboard_Widget( $this->connection, $this->api ) )->hooks();
			( new Apogee_Watcher_Results_Page( $this->connection, $this->api ) )->hooks();
			( new Apogee_Watcher_Admin_Notices( $this->connection, $this->api ) )->hooks();
			( new Apogee_Watcher_OAuth( $this->connection, $this->api ) )->hooks();
		}

		add_filter( 'plugin_action_links_' . APOGEE_WATCHER_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Load bundled translations for self-hosted installs (Update URI present).
	 * WordPress.org-hosted copies omit Update URI and get language packs automatically.
	 */
	private function maybe_load_textdomain(): void {
		$headers = get_file_data(
			APOGEE_WATCHER_FILE,
			array(
				'UpdateURI' => 'Update URI',
			)
		);
		if ( empty( $headers['UpdateURI'] ) ) {
			return;
		}
		load_plugin_textdomain( 'apogee-watcher', false, dirname( APOGEE_WATCHER_BASENAME ) . '/languages' );
	}

	/**
	 * Settings link on Plugins screen.
	 *
	 * @param array<string, string> $links Existing links.
	 * @return array<string, string>
	 */
	public function action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=apogee-watcher' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'apogee-watcher' )
			)
		);
		return $links;
	}

	/**
	 * Activation.
	 */
	public static function activate(): void {
		self::migrate_from_legacy_slug();

		if ( ! get_option( Apogee_Watcher_Connection::OPTION_KEY ) ) {
			add_option(
				Apogee_Watcher_Connection::OPTION_KEY,
				Apogee_Watcher_Connection::defaults(),
				'',
				'no'
			);
		}
	}

	/**
	 * Copy connection + dismiss meta from pre-0.3.1 slug (apogee-wp-watcher / apogee_wp_watcher_*).
	 */
	public static function migrate_from_legacy_slug(): void {
		$legacy_option = 'apogee_wp_watcher_settings';
		$new_option    = Apogee_Watcher_Connection::OPTION_KEY;

		if ( ! get_option( $new_option ) && get_option( $legacy_option ) ) {
			$old = get_option( $legacy_option );
			if ( is_array( $old ) ) {
				update_option( $new_option, $old, false );
			}
			delete_option( $legacy_option );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"UPDATE {$wpdb->usermeta} SET meta_key = 'apogee_watcher_alerts_dismissed' WHERE meta_key = 'apogee_wp_watcher_alerts_dismissed'"
		);

		$legacy_transients = array(
			'apogee_wp_watcher_overview',
			'apogee_wp_watcher_alerts',
			'apogee_wp_watcher_alerts_recent',
			'apogee_wp_watcher_pages',
			'apogee_wp_watcher_device_pending',
			'apogee_wp_watcher_update_latest',
			'apogee_wp_watcher_update_fail',
		);
		foreach ( $legacy_transients as $key ) {
			delete_transient( $key );
		}
	}

	/**
	 * Deactivation — keep connection; clear short-lived caches.
	 */
	public static function deactivate(): void {
		delete_transient( 'apogee_watcher_overview' );
		delete_transient( 'apogee_watcher_alerts' );
		delete_transient( 'apogee_watcher_alerts_recent' );
		delete_transient( 'apogee_watcher_pages' );
		delete_transient( 'apogee_watcher_device_pending' );
		delete_transient( Apogee_Watcher_Updater::TRANSIENT_LATEST );
		delete_transient( Apogee_Watcher_Updater::TRANSIENT_FAIL );
	}
}
