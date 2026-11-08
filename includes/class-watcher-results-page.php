<?php
/**
 * Admin: Test results (pages + latest mobile/desktop).
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Results_Page
 */
class Apogee_Watcher_Results_Page {

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
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
	}

	/**
	 * Submenu.
	 */
	public function menu(): void {
		add_submenu_page(
			'apogee-wp-watcher',
			__( 'Test results', 'apogee-wp-watcher' ),
			__( 'Test results', 'apogee-wp-watcher' ),
			'manage_options',
			'apogee-wp-watcher-results',
			array( $this, 'render' )
		);
	}

	/**
	 * Page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap apogee-wp-watcher-wrap">';
		echo '<h1>' . esc_html__( 'Test results', 'apogee-wp-watcher' ) . '</h1>';

		if ( ! $this->connection->is_connected() ) {
			printf(
				'<p>%s</p><p><a class="button button-primary" href="%s">%s</a></p>',
				esc_html__( 'Connect to Watcher to load test results.', 'apogee-wp-watcher' ),
				esc_url( admin_url( 'admin.php?page=apogee-wp-watcher' ) ),
				esc_html__( 'Open Connection', 'apogee-wp-watcher' )
			);
			echo '</div>';
			return;
		}

		$cursor = isset( $_GET['cursor'] ) ? sanitize_text_field( wp_unslash( $_GET['cursor'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pages  = get_transient( 'apogee_wp_watcher_pages_' . md5( (string) $cursor ) );
		if ( false === $pages ) {
			$pages = $this->api->get_pages( $cursor, 50 );
			if ( ! is_wp_error( $pages ) ) {
				set_transient( 'apogee_wp_watcher_pages_' . md5( (string) $cursor ), $pages, 10 * MINUTE_IN_SECONDS );
			}
		}

		if ( is_wp_error( $pages ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $pages->get_error_message() ) );
			echo '</div>';
			return;
		}

		$rows = isset( $pages['data'] ) && is_array( $pages['data'] ) ? $pages['data'] : array();
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No monitored pages yet. Add pages in the Watcher dashboard, then return here.', 'apogee-wp-watcher' ) . '</p>';
			printf(
				'<p><a class="button" href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				esc_url( $this->connection->site_admin_url() ),
				esc_html__( 'Open Watcher', 'apogee-wp-watcher' )
			);
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped apogee-wp-watcher-table">';
		echo '<thead><tr>';
		foreach ( array( 'URL', 'Mobile', 'Desktop', 'LCP (m)', 'INP (m)', 'CLS (m)' ) as $h ) {
			printf( '<th>%s</th>', esc_html( $h ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$url     = (string) ( $row['url'] ?? '' );
			$mobile  = is_array( $row['latest']['mobile'] ?? null ) ? $row['latest']['mobile'] : null;
			$desktop = is_array( $row['latest']['desktop'] ?? null ) ? $row['latest']['desktop'] : null;
			echo '<tr>';
			printf( '<td><code>%s</code></td>', esc_html( $url ) );
			printf( '<td>%s</td>', esc_html( $this->score_cell( $mobile ) ) );
			printf( '<td>%s</td>', esc_html( $this->score_cell( $desktop ) ) );
			printf( '<td>%s</td>', esc_html( $this->metric( $mobile, 'lcp' ) ) );
			printf( '<td>%s</td>', esc_html( $this->metric( $mobile, 'inp' ) ) );
			printf( '<td>%s</td>', esc_html( $this->metric( $mobile, 'cls' ) ) );
			echo '</tr>';
		}
		echo '</tbody></table>';

		$next = $pages['next_cursor'] ?? null;
		if ( is_string( $next ) && $next !== '' ) {
			printf(
				'<p><a class="button" href="%s">%s</a></p>',
				esc_url(
					add_query_arg(
						array(
							'page'   => 'apogee-wp-watcher-results',
							'cursor' => $next,
						),
						admin_url( 'admin.php' )
					)
				),
				esc_html__( 'Next page', 'apogee-wp-watcher' )
			);
		}

		echo '<p class="apogee-wp-watcher-muted">' . esc_html__( 'Lab times are in seconds (Watcher units).', 'apogee-wp-watcher' ) . '</p>';
		echo '</div>';
	}

	/**
	 * @param array<string,mixed>|null $result Result summary.
	 */
	private function score_cell( ?array $result ): string {
		if ( ! $result ) {
			return '—';
		}
		$score = $result['performance_score'] ?? null;
		return null !== $score ? (string) (int) $score : '—';
	}

	/**
	 * @param array<string,mixed>|null $result Result.
	 * @param string                   $key    Metric.
	 */
	private function metric( ?array $result, string $key ): string {
		if ( ! $result || ! isset( $result[ $key ] ) || null === $result[ $key ] ) {
			return '—';
		}
		return (string) $result[ $key ];
	}
}
