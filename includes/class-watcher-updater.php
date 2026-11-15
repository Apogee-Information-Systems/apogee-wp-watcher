<?php
/**
 * Self-hosted plugin updates (Update URI + update_plugins_{hostname}).
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Updater
 */
class Apogee_Watcher_Updater {

	/**
	 * Must match the Update URI host in the main plugin file header.
	 */
	public const UPDATE_FILTER = 'update_plugins_apogeewatcher.com';

	/**
	 * Transient for successful latest JSON.
	 */
	public const TRANSIENT_LATEST = 'apogee_wp_watcher_update_latest';

	/**
	 * Transient for failed fetch (short negative cache).
	 */
	public const TRANSIENT_FAIL = 'apogee_wp_watcher_update_fail';

	/**
	 * Hooks — register outside is_admin() so cron update checks work.
	 */
	public function hooks(): void {
		add_filter( self::UPDATE_FILTER, array( $this, 'provide_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
	}

	/**
	 * Return update payload for WordPress when a newer version exists.
	 *
	 * @param array|false $update      Default false.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @param string[]    $locales     Installed locales.
	 * @return array|false
	 */
	public function provide_update( $update, $plugin_data, $plugin_file, $locales ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( APOGEE_WP_WATCHER_BASENAME !== $plugin_file ) {
			return $update;
		}

		$remote = $this->fetch_latest();
		if ( is_wp_error( $remote ) || ! is_array( $remote ) ) {
			return $update;
		}

		$remote_version = isset( $remote['version'] ) ? (string) $remote['version'] : '';
		if ( $remote_version === '' || ! version_compare( $remote_version, APOGEE_WP_WATCHER_VERSION, '>' ) ) {
			return $update;
		}

		$package = isset( $remote['package'] ) ? (string) $remote['package'] : '';
		if ( $package === '' ) {
			return $update;
		}

		return array(
			'id'            => 'https://apogeewatcher.com/tools/wordpress',
			'slug'          => 'apogee-wp-watcher',
			'version'       => $remote_version,
			'url'           => ! empty( $remote['url'] ) ? (string) $remote['url'] : 'https://apogeewatcher.com/tools/wordpress',
			'package'       => $package,
			'tested'        => isset( $remote['tested'] ) ? (string) $remote['tested'] : '',
			'requires_php'  => isset( $remote['requires_php'] ) ? (string) $remote['requires_php'] : '',
			'requires'      => isset( $remote['requires'] ) ? (string) $remote['requires'] : '',
		);
	}

	/**
	 * “View details” for this plugin on the Plugins / Updates screens.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action Action.
	 * @param object             $args   Args.
	 * @return false|object|array
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! is_object( $args ) || empty( $args->slug ) || 'apogee-wp-watcher' !== $args->slug ) {
			return $result;
		}

		$remote = $this->fetch_latest();
		if ( is_wp_error( $remote ) || ! is_array( $remote ) ) {
			return $result;
		}

		$changelog = $this->fetch_changelog();
		$sections  = array(
			'description' => __(
				'Connect your WordPress site to Apogee Watcher. See Core Web Vitals and Lighthouse scores in the dashboard without a Google PageSpeed API key in WordPress.',
				'apogee-wp-watcher'
			),
		);
		if ( is_string( $changelog ) && $changelog !== '' ) {
			$sections['changelog'] = nl2br( esc_html( $changelog ) );
		}

		return (object) array(
			'name'          => isset( $remote['name'] ) ? (string) $remote['name'] : 'Apogee Watcher for WordPress',
			'slug'          => 'apogee-wp-watcher',
			'version'       => isset( $remote['version'] ) ? (string) $remote['version'] : APOGEE_WP_WATCHER_VERSION,
			'author'        => '<a href="https://apogee.lu/">Apogee Information Systems</a>',
			'homepage'      => ! empty( $remote['url'] ) ? (string) $remote['url'] : 'https://apogeewatcher.com/tools/wordpress',
			'requires'      => isset( $remote['requires'] ) ? (string) $remote['requires'] : '5.8',
			'requires_php'  => isset( $remote['requires_php'] ) ? (string) $remote['requires_php'] : '7.4',
			'tested'        => isset( $remote['tested'] ) ? (string) $remote['tested'] : '',
			'download_link' => isset( $remote['package'] ) ? (string) $remote['package'] : '',
			'sections'      => $sections,
		);
	}

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	private function fetch_latest() {
		$cached = get_transient( self::TRANSIENT_LATEST );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( get_transient( self::TRANSIENT_FAIL ) ) {
			return new WP_Error( 'apogee_wp_watcher_update_fail', 'Update check recently failed.' );
		}

		$url      = APOGEE_WP_WATCHER_API_BASE . '/api/plugin/wordpress/latest';
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'ApogeeWpWatcher-WordPress/' . APOGEE_WP_WATCHER_VERSION . '; ' . home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			set_transient( self::TRANSIENT_FAIL, 1, 15 * MINUTE_IN_SECONDS );
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) || empty( $data['version'] ) ) {
			set_transient( self::TRANSIENT_FAIL, 1, 15 * MINUTE_IN_SECONDS );
			return new WP_Error( 'apogee_wp_watcher_update_http', 'Invalid update response.' );
		}

		set_transient( self::TRANSIENT_LATEST, $data, 6 * HOUR_IN_SECONDS );
		delete_transient( self::TRANSIENT_FAIL );

		return $data;
	}

	/**
	 * @return string|null
	 */
	private function fetch_changelog() {
		$url      = APOGEE_WP_WATCHER_API_BASE . '/api/plugin/wordpress/changelog';
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'ApogeeWpWatcher-WordPress/' . APOGEE_WP_WATCHER_VERSION . '; ' . home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['changelog'] ) || ! is_string( $data['changelog'] ) ) {
			return null;
		}

		return $data['changelog'];
	}
}
