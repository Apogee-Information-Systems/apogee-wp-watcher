<?php
/**
 * Device OAuth AJAX handlers (Connect / poll / disconnect).
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_OAuth
 */
class Apogee_Watcher_OAuth {

	public const TRANSIENT_PENDING = 'apogee_wp_watcher_device_pending';

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
		add_action( 'wp_ajax_apogee_wp_watcher_start_connect', array( $this, 'ajax_start_connect' ) );
		add_action( 'wp_ajax_apogee_wp_watcher_poll_token', array( $this, 'ajax_poll_token' ) );
		add_action( 'wp_ajax_apogee_wp_watcher_disconnect', array( $this, 'ajax_disconnect' ) );
	}

	/**
	 * Start device pairing.
	 */
	public function ajax_start_connect(): void {
		$this->guard();

		if ( $this->connection->is_connected() ) {
			wp_send_json_error(
				array( 'message' => __( 'Already connected. Disconnect first to pair again.', 'apogee-wp-watcher' ) ),
				400
			);
		}

		$result = $this->api->start_device_code();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$verify          = $this->connection->sanitise_verification_uri( (string) ( $result['verification_uri'] ?? '' ) );
		$verify_complete = $this->connection->sanitise_verification_uri( (string) ( $result['verification_uri_complete'] ?? '' ) );
		if ( $verify === '' && $verify_complete === '' ) {
			wp_send_json_error(
				array( 'message' => __( 'Watcher returned an invalid verification URL.', 'apogee-wp-watcher' ) ),
				400
			);
		}

		set_transient(
			self::TRANSIENT_PENDING,
			array(
				'device_code' => (string) ( $result['device_code'] ?? '' ),
				'user_code'   => (string) ( $result['user_code'] ?? '' ),
				'interval'    => (int) ( $result['interval'] ?? 5 ),
				'expires_at'  => time() + (int) ( $result['expires_in'] ?? 600 ),
			),
			(int) ( $result['expires_in'] ?? 600 )
		);

		wp_send_json_success(
			array(
				'user_code'                 => (string) ( $result['user_code'] ?? '' ),
				'verification_uri'          => $verify,
				'verification_uri_complete' => $verify_complete,
				'expires_in'                => (int) ( $result['expires_in'] ?? 600 ),
				'interval'                  => (int) ( $result['interval'] ?? 5 ),
			)
		);
	}

	/**
	 * Poll token endpoint until approved.
	 */
	public function ajax_poll_token(): void {
		$this->guard();

		$pending = get_transient( self::TRANSIENT_PENDING );
		if ( ! is_array( $pending ) || empty( $pending['device_code'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No pairing in progress. Start Connect again.', 'apogee-wp-watcher' ),
					'error'   => 'expired_token',
				),
				400
			);
		}

		$result = $this->api->oauth_token(
			array(
				'grant_type'  => 'urn:ietf:params:oauth:grant-type:device_code',
				'device_code' => (string) $pending['device_code'],
			)
		);

		if ( is_wp_error( $result ) ) {
			$err_data    = $result->get_error_data();
			$oauth_error = is_array( $err_data ) ? ( $err_data['oauth_error'] ?? null ) : null;
			if ( ! $oauth_error && is_array( $err_data ) && ! empty( $err_data['body']['error'] ) ) {
				$oauth_error = $err_data['body']['error'];
			}

			if ( in_array( $oauth_error, array( 'authorization_pending', 'slow_down' ), true ) ) {
				wp_send_json_success(
					array(
						'status'  => $oauth_error,
						'message' => __( 'Waiting for approval in Watcher…', 'apogee-wp-watcher' ),
					)
				);
			}

			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'error'   => is_string( $oauth_error ) ? $oauth_error : 'error',
				),
				400
			);
		}

		if ( empty( $result['access_token'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unexpected token response.', 'apogee-wp-watcher' ) ), 400 );
		}

		$this->connection->store_tokens( $result );
		delete_transient( self::TRANSIENT_PENDING );
		delete_transient( 'apogee_wp_watcher_overview' );

		$integration = $this->api->get_integration();
		if ( ! is_wp_error( $integration ) ) {
			$this->connection->update(
				array(
					'site_host' => (string) ( $integration['site_host'] ?? $this->connection->get( 'site_host' ) ),
					'scopes'    => $integration['scopes'] ?? $this->connection->get( 'scopes' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'status'  => 'connected',
				'message' => __( 'Connected to Apogee Watcher.', 'apogee-wp-watcher' ),
				'site_id' => (string) $this->connection->get( 'site_id' ),
			)
		);
	}

	/**
	 * Disconnect and revoke.
	 */
	public function ajax_disconnect(): void {
		$this->guard();

		$this->api->revoke();
		$this->connection->disconnect();

		wp_send_json_success(
			array(
				'message' => __( 'Disconnected from Watcher.', 'apogee-wp-watcher' ),
			)
		);
	}

	/**
	 * Capability + nonce.
	 */
	private function guard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'apogee-wp-watcher' ) ), 403 );
		}
		check_ajax_referer( 'apogee_wp_watcher_admin', 'nonce' );
	}
}
