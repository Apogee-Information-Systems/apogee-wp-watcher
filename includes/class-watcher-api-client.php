<?php
/**
 * Watcher HTTP client (OAuth + site-scoped REST).
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Api_Client
 */
class Apogee_Watcher_Api_Client {

	public const CLIENT_ID = 'apogee-watcher-wordpress';

	/**
	 * @var Apogee_Watcher_Connection
	 */
	private $connection;

	/**
	 * @param Apogee_Watcher_Connection $connection Connection store.
	 */
	public function __construct( Apogee_Watcher_Connection $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Start RFC 8628 device authorisation.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function start_device_code() {
		$user  = wp_get_current_user();
		$email = ( $user instanceof WP_User && $user->user_email ) ? $user->user_email : '';

		$body = array(
			'client_id'        => self::CLIENT_ID,
			'site_url'         => $this->connection->site_url_for_pairing(),
			'plugin_version'   => APOGEE_WP_WATCHER_VERSION,
			'scope'            => 'site:read site:alerts:read',
			'suggested_email'  => $email,
		);

		return $this->request(
			'POST',
			'/oauth/device/code',
			$body,
			array(
				'auth'    => false,
				'timeout' => 20,
			)
		);
	}

	/**
	 * Poll or exchange device code / refresh token.
	 *
	 * @param array<string, string> $form Form body.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function oauth_token( array $form ) {
		$form['client_id'] = self::CLIENT_ID;

		return $this->request(
			'POST',
			'/oauth/token',
			$form,
			array(
				'auth'         => false,
				'content_type' => 'application/x-www-form-urlencoded',
				'timeout'      => 20,
			)
		);
	}

	/**
	 * Revoke refresh token on Watcher.
	 *
	 * @return array<string, mixed>|\WP_Error|true
	 */
	public function revoke() {
		$refresh = (string) $this->connection->get( 'refresh_token', '' );
		if ( $refresh === '' ) {
			return true;
		}

		return $this->request(
			'POST',
			'/oauth/revoke',
			array(
				'client_id' => self::CLIENT_ID,
				'token'     => $refresh,
			),
			array(
				'auth'         => false,
				'content_type' => 'application/x-www-form-urlencoded',
				'timeout'      => 15,
			)
		);
	}

	/**
	 * GET /api/v1/integration
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_integration() {
		return $this->authenticated_get( '/api/v1/integration' );
	}

	/**
	 * GET /api/v1/sites/{id}/overview
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_overview() {
		$site_id = (string) $this->connection->get( 'site_id', '' );
		if ( $site_id === '' ) {
			return new WP_Error( 'apogee_wp_watcher_no_site', __( 'No Watcher site is linked.', 'apogee-wp-watcher' ) );
		}
		return $this->authenticated_get( '/api/v1/sites/' . rawurlencode( $site_id ) . '/overview' );
	}

	/**
	 * GET /api/v1/sites/{id}/pages
	 *
	 * @param string|null $cursor Cursor.
	 * @param int         $limit  Page size.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_pages( ?string $cursor = null, int $limit = 50 ) {
		$site_id = (string) $this->connection->get( 'site_id', '' );
		if ( $site_id === '' ) {
			return new WP_Error( 'apogee_wp_watcher_no_site', __( 'No Watcher site is linked.', 'apogee-wp-watcher' ) );
		}
		$query = array( 'limit' => max( 1, min( 100, $limit ) ) );
		if ( is_string( $cursor ) && $cursor !== '' ) {
			$query['cursor'] = $cursor;
		}
		$path = '/api/v1/sites/' . rawurlencode( $site_id ) . '/pages?' . http_build_query( $query );
		return $this->authenticated_get( $path );
	}

	/**
	 * GET /api/v1/sites/{id}/alerts
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_alerts() {
		$site_id = (string) $this->connection->get( 'site_id', '' );
		if ( $site_id === '' ) {
			return new WP_Error( 'apogee_wp_watcher_no_site', __( 'No Watcher site is linked.', 'apogee-wp-watcher' ) );
		}
		return $this->authenticated_get( '/api/v1/sites/' . rawurlencode( $site_id ) . '/alerts?open=1&limit=20' );
	}

	/**
	 * Authenticated GET with refresh retry.
	 *
	 * @param string $path Path starting with /.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function authenticated_get( string $path ) {
		$refreshed = $this->ensure_fresh_access_token();
		if ( is_wp_error( $refreshed ) ) {
			return $refreshed;
		}

		$response = $this->request( 'GET', $path, null, array( 'auth' => true ) );
		if ( ! is_wp_error( $response ) ) {
			return $response;
		}

		$err_meta = $response->get_error_data();
		if ( is_array( $err_meta ) && (int) ( $err_meta['status'] ?? 0 ) === 401 ) {
			$rotate = $this->refresh_access_token();
			if ( is_wp_error( $rotate ) ) {
				return $rotate;
			}
			return $this->request( 'GET', $path, null, array( 'auth' => true ) );
		}

		return $response;
	}

	/**
	 * Refresh if near expiry.
	 *
	 * @return true|\WP_Error
	 */
	private function ensure_fresh_access_token() {
		$expires = (int) $this->connection->get( 'token_expires_at', 0 );
		if ( $expires > 0 && $expires > time() ) {
			return true;
		}
		return $this->refresh_access_token();
	}

	/**
	 * Rotate refresh token.
	 *
	 * @return true|\WP_Error
	 */
	public function refresh_access_token() {
		$refresh = (string) $this->connection->get( 'refresh_token', '' );
		if ( $refresh === '' ) {
			return new WP_Error( 'apogee_wp_watcher_no_refresh', __( 'Missing refresh token. Reconnect to Watcher.', 'apogee-wp-watcher' ) );
		}

		$result = $this->oauth_token(
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['access_token'] ) ) {
			return new WP_Error( 'apogee_wp_watcher_refresh_failed', __( 'Could not refresh the Watcher token.', 'apogee-wp-watcher' ) );
		}

		$this->connection->store_tokens( $result );
		return true;
	}

	/**
	 * Low-level HTTP.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $path    Path.
	 * @param array<string,mixed>|null $body Body.
	 * @param array<string,mixed>  $args    Options.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function request( string $method, string $path, $body = null, array $args = array() ) {
		$url = $this->connection->api_base() . $path;

		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => 'ApogeeWpWatcher-WordPress/' . APOGEE_WP_WATCHER_VERSION . '; ' . home_url( '/' ),
		);

		$content_type = $args['content_type'] ?? 'application/json';
		$timeout      = isset( $args['timeout'] ) ? (int) $args['timeout'] : 20;
		$use_auth     = ! empty( $args['auth'] );

		if ( $use_auth ) {
			$token = (string) $this->connection->get( 'access_token', '' );
			if ( $token === '' ) {
				return new WP_Error( 'apogee_wp_watcher_no_token', __( 'Not connected to Watcher.', 'apogee-wp-watcher' ) );
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$request_args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => $headers,
		);

		if ( null !== $body ) {
			if ( 'application/x-www-form-urlencoded' === $content_type ) {
				$headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
				$request_args['headers'] = $headers;
				$request_args['body']    = $body;
			} else {
				$headers['Content-Type'] = 'application/json; charset=utf-8';
				$request_args['headers'] = $headers;
				$request_args['body']    = wp_json_encode( $body );
			}
		}

		$response = wp_remote_request( $url, $request_args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( $status >= 200 && $status < 300 ) {
			return $data;
		}

		$message = '';
		if ( ! empty( $data['message'] ) && is_string( $data['message'] ) ) {
			$message = $data['message'];
		} elseif ( ! empty( $data['error_description'] ) && is_string( $data['error_description'] ) ) {
			$message = $data['error_description'];
		} elseif ( ! empty( $data['error'] ) && is_string( $data['error'] ) ) {
			$message = $data['error'];
		} else {
			$message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Watcher API error (HTTP %d).', 'apogee-wp-watcher' ),
				$status
			);
		}

		return new WP_Error(
			'apogee_wp_watcher_http_' . $status,
			$message,
			array(
				'status'      => $status,
				'body'        => $data,
				'oauth_error' => ( ! empty( $data['error'] ) && is_string( $data['error'] ) ) ? $data['error'] : null,
			)
		);
	}
}
