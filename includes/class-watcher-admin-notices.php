<?php
/**
 * Admin notices for open Watcher alerts.
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Admin_Notices
 */
class Apogee_Watcher_Admin_Notices {

	/**
	 * @var Apogee_Watcher_Connection
	 */
	private $connection;

	/**
	 * @var Apogee_Watcher_Api_Client
	 */
	private $api;

	/**
	 * @param Apogee_Watcher_Connection $connection Connection.
	 * @param Apogee_Watcher_Api_Client $api        API.
	 */
	public function __construct( Apogee_Watcher_Connection $connection, Apogee_Watcher_Api_Client $api ) {
		$this->connection = $connection;
		$this->api        = $api;
	}

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'wp_ajax_apogee_wp_watcher_dismiss_alerts', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Show a compact notice when open alerts exist.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) || ! $this->connection->is_connected() ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), 'apogee_wp_watcher_alerts_dismissed', true ) === '1' ) {
			return;
		}

		$alerts = get_transient( 'apogee_wp_watcher_alerts' );
		if ( false === $alerts ) {
			$alerts = $this->api->get_alerts();
			if ( ! is_wp_error( $alerts ) ) {
				set_transient( 'apogee_wp_watcher_alerts', $alerts, 15 * MINUTE_IN_SECONDS );
			}
		}

		if ( is_wp_error( $alerts ) ) {
			return;
		}

		$data  = isset( $alerts['data'] ) && is_array( $alerts['data'] ) ? $alerts['data'] : array();
		$count = count( $data );
		if ( $count < 1 ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, 'apogee-wp-watcher' ) ) {
			// Still show on plugin pages — useful context.
		}

		$first = $data[0];
		$metric = isset( $first['metric_name'] ) ? (string) $first['metric_name'] : 'metric';
		$strategy = isset( $first['strategy'] ) ? (string) $first['strategy'] : '';

		printf(
			'<div class="notice notice-warning is-dismissible apogee-wp-watcher-notice" data-dismiss-nonce="%s"><p><strong>%s</strong> %s <a href="%s">%s</a> · <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p></div>',
			esc_attr( wp_create_nonce( 'apogee_wp_watcher_admin' ) ),
			esc_html__( 'Apogee Watcher:', 'apogee-wp-watcher' ),
			esc_html(
				sprintf(
					/* translators: 1: alert count, 2: metric, 3: strategy */
					_n(
						'%1$d open alert (%2$s · %3$s).',
						'%1$d open alerts (e.g. %2$s · %3$s).',
						$count,
						'apogee-wp-watcher'
					),
					$count,
					$metric,
					$strategy
				)
			),
			esc_url( admin_url( 'admin.php?page=apogee-wp-watcher-results' ) ),
			esc_html__( 'Results', 'apogee-wp-watcher' ),
			esc_url( $this->connection->api_base() . '/admin' ),
			esc_html__( 'Investigate in Watcher', 'apogee-wp-watcher' )
		);
	}

	/**
	 * Dismiss for this user until they reconnect or clear meta.
	 */
	public function ajax_dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'apogee_wp_watcher_admin', 'nonce' );
		update_user_meta( get_current_user_id(), 'apogee_wp_watcher_alerts_dismissed', '1' );
		wp_send_json_success();
	}
}
