<?php
/**
 * WordPress privacy exporters / erasers (GDPR Tools → Export / Erase Personal Data).
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Privacy
 */
class Apogee_Watcher_Privacy {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['apogee-watcher'] = array(
			'exporter_friendly_name' => __( 'Apogee Watcher: Automated PageSpeed Monitoring', 'apogee-watcher' ),
			'callback'             => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['apogee-watcher'] = array(
			'eraser_friendly_name' => __( 'Apogee Watcher: Automated PageSpeed Monitoring', 'apogee-watcher' ),
			'callback'            => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Export per-user plugin data. Site OAuth tokens stay on the site option (not exported as secrets).
	 *
	 * @param string $email_address Email.
	 * @param int    $page          Page (unused; single page).
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$items   = array();
		$dismiss = get_user_meta( $user->ID, 'apogee_watcher_alerts_dismissed', true );
		if ( $dismiss !== '' && $dismiss !== false ) {
			$items[] = array(
				'name'  => __( 'Alert notice dismissed', 'apogee-watcher' ),
				'value' => ( '1' === (string) $dismiss ) ? __( 'Yes', 'apogee-watcher' ) : (string) $dismiss,
			);
		}

		$connection = new Apogee_Watcher_Connection();
		if ( $connection->is_connected() && user_can( $user, 'manage_options' ) ) {
			$items[] = array(
				'name'  => __( 'Watcher site host (connected)', 'apogee-watcher' ),
				'value' => (string) $connection->get( 'site_host', '' ),
			);
			$items[] = array(
				'name'  => __( 'Watcher site ID', 'apogee-watcher' ),
				'value' => (string) $connection->get( 'site_id', '' ),
			);
			$connected_at = (string) $connection->get( 'connected_at', '' );
			if ( $connected_at !== '' ) {
				$items[] = array(
					'name'  => __( 'Connected at (UTC)', 'apogee-watcher' ),
					'value' => $connected_at,
				);
			}
			$scopes = $connection->get( 'scopes', array() );
			if ( is_array( $scopes ) && $scopes !== array() ) {
				$items[] = array(
					'name'  => __( 'OAuth scopes', 'apogee-watcher' ),
					'value' => implode( ', ', array_map( 'strval', $scopes ) ),
				);
			}
			$items[] = array(
				'name'  => __( 'Access token stored', 'apogee-watcher' ),
				'value' => ( (string) $connection->get( 'access_token', '' ) !== '' ) ? __( 'Yes (value not exported)', 'apogee-watcher' ) : __( 'No', 'apogee-watcher' ),
			);
			$items[] = array(
				'name'  => __( 'Note', 'apogee-watcher' ),
				'value' => __( 'During Connect, this site URL and the WordPress admin email may be sent to Apogee Watcher as a suggested account address. Account data on apogeewatcher.com is governed by Watcher’s privacy policy.', 'apogee-watcher' ),
			);
		}

		if ( $items === array() ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		return array(
			'data' => array(
				array(
					'group_id'          => 'apogee-watcher',
					'group_label'       => __( 'Apogee Watcher: Automated PageSpeed Monitoring', 'apogee-watcher' ),
					'group_description' => __( 'Connection and notice preferences for the Apogee Watcher plugin.', 'apogee-watcher' ),
					'item_id'           => 'apogee-watcher-' . $user->ID,
					'data'              => $items,
				),
			),
			'done' => true,
		);
	}

	/**
	 * Erase per-user plugin data. Does not disconnect the site (shared site option).
	 *
	 * @param string $email_address Email.
	 * @param int    $page          Page (unused).
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$had = get_user_meta( $user->ID, 'apogee_watcher_alerts_dismissed', true );
		delete_user_meta( $user->ID, 'apogee_watcher_alerts_dismissed' );

		$messages = array();
		if ( $had !== '' && $had !== false ) {
			$messages[] = __( 'Removed Apogee Watcher alert-dismiss preference for this user.', 'apogee-watcher' );
		}

		$connection = new Apogee_Watcher_Connection();
		if ( $connection->is_connected() && user_can( $user, 'manage_options' ) ) {
			$messages[] = __( 'Site connection tokens were retained (site-level settings). Disconnect under Watcher → Connection if you also want this WordPress site unbound from Apogee Watcher.', 'apogee-watcher' );
		}

		return array(
			'items_removed'  => ( $had !== '' && $had !== false ),
			'items_retained' => $connection->is_connected() && user_can( $user, 'manage_options' ),
			'messages'       => $messages,
			'done'           => true,
		);
	}
}
