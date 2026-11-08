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
		load_plugin_textdomain( 'apogee-wp-watcher', false, dirname( APOGEE_WP_WATCHER_BASENAME ) . '/languages' );

		$this->connection = new Apogee_Watcher_Connection();
		$this->api        = new Apogee_Watcher_Api_Client( $this->connection );

		if ( is_admin() ) {
			( new Apogee_Watcher_Settings( $this->connection, $this->api ) )->hooks();
			( new Apogee_Watcher_Dashboard_Widget( $this->connection, $this->api ) )->hooks();
			( new Apogee_Watcher_Results_Page( $this->connection, $this->api ) )->hooks();
			( new Apogee_Watcher_Admin_Notices( $this->connection, $this->api ) )->hooks();
			( new Apogee_Watcher_OAuth( $this->connection, $this->api ) )->hooks();
		}

		add_filter( 'plugin_action_links_' . APOGEE_WP_WATCHER_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Settings link on Plugins screen.
	 *
	 * @param array<string, string> $links Existing links.
	 * @return array<string, string>
	 */
	public function action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=apogee-wp-watcher' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'apogee-wp-watcher' )
			)
		);
		return $links;
	}

	/**
	 * Activation.
	 */
	public static function activate(): void {
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
	 * Deactivation — keep connection; clear short-lived caches.
	 */
	public static function deactivate(): void {
		delete_transient( 'apogee_wp_watcher_overview' );
		delete_transient( 'apogee_wp_watcher_alerts' );
		delete_transient( 'apogee_wp_watcher_pages' );
		delete_transient( 'apogee_wp_watcher_device_pending' );
	}
}
