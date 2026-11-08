<?php
/**
 * Stored connection state (site-scoped OAuth tokens).
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Connection
 */
class Apogee_Watcher_Connection {

	public const OPTION_KEY = 'apogee_wp_watcher_settings';

	/**
	 * Default option shape (tokens only — API base is fixed in code).
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'access_token'     => '',
			'refresh_token'    => '',
			'token_expires_at' => 0,
			'site_id'          => '',
			'integration_id'   => '',
			'site_host'        => '',
			'scopes'           => array(),
			'connected_at'     => '',
		);
	}

	/**
	 * All settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		// Drop legacy api_base if present from older builds.
		unset( $stored['api_base'] );
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Persist settings (merge).
	 *
	 * @param array<string, mixed> $patch Fields to update.
	 */
	public function update( array $patch ): void {
		unset( $patch['api_base'] );
		$next = array_merge( $this->all(), $patch );
		unset( $next['api_base'] );
		update_option( self::OPTION_KEY, $next, false );
	}

	/**
	 * Clear tokens and site binding.
	 */
	public function disconnect(): void {
		update_option( self::OPTION_KEY, self::defaults(), false );
		delete_transient( 'apogee_wp_watcher_overview' );
		delete_transient( 'apogee_wp_watcher_alerts' );
		delete_transient( 'apogee_wp_watcher_pages' );
		delete_transient( 'apogee_wp_watcher_device_pending' );
	}

	/**
	 * Single setting.
	 *
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Whether a usable connection exists.
	 */
	public function is_connected(): bool {
		$token = (string) $this->get( 'access_token', '' );
		$site  = (string) $this->get( 'site_id', '' );
		return $token !== '' && $site !== '';
	}

	/**
	 * Production Watcher API origin (no trailing slash).
	 */
	public function api_base(): string {
		return APOGEE_WP_WATCHER_API_BASE;
	}

	/**
	 * Store tokens from OAuth token response.
	 *
	 * @param array<string, mixed> $token_payload Token endpoint JSON.
	 */
	public function store_tokens( array $token_payload ): void {
		$expires_in = isset( $token_payload['expires_in'] ) ? (int) $token_payload['expires_in'] : 3600;
		$refresh    = array_key_exists( 'refresh_token', $token_payload )
			? (string) $token_payload['refresh_token']
			: (string) $this->get( 'refresh_token', '' );

		$this->update(
			array(
				'access_token'     => (string) ( $token_payload['access_token'] ?? '' ),
				'refresh_token'    => $refresh,
				'token_expires_at' => time() + max( 60, $expires_in ) - 30,
				'site_id'          => (string) ( $token_payload['site_id'] ?? $this->get( 'site_id', '' ) ),
				'integration_id'   => (string) ( $token_payload['integration_id'] ?? $this->get( 'integration_id', '' ) ),
				'scopes'           => isset( $token_payload['scope'] )
					? preg_split( '/\s+/', trim( (string) $token_payload['scope'] ) )
					: $this->get( 'scopes', array() ),
				'connected_at'     => gmdate( 'c' ),
				'site_host'        => $this->normalise_host( home_url() ),
			)
		);
	}

	/**
	 * Hostname for this WordPress install.
	 */
	public function site_url_for_pairing(): string {
		return home_url( '/' );
	}

	/**
	 * Normalise host like Watcher WebsiteHostname (strip scheme/www/path).
	 *
	 * @param string $url URL or host.
	 */
	public function normalise_host( string $url ): string {
		$host = preg_replace( '#^https?://#i', '', trim( $url ) );
		$host = preg_replace( '#^www\.#i', '', (string) $host );
		$host = strtok( (string) $host, '/' ) ?: (string) $host;
		return strtolower( trim( $host ) );
	}
}
