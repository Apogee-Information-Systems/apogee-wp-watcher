<?php
/**
 * Admin: Test results (pages + latest mobile/desktop) and recent alerts.
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

		echo '<div class="wrap apogee-wp-watcher-wrap apogee-wp-watcher-wrap--wide">';
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

		$alerts = get_transient( 'apogee_wp_watcher_alerts_recent' );
		if ( false === $alerts ) {
			// Last 20 by id (open and resolved) — separate cache from open-only admin notices.
			$alerts = $this->api->get_alerts( 20, false );
			if ( ! is_wp_error( $alerts ) ) {
				set_transient( 'apogee_wp_watcher_alerts_recent', $alerts, 10 * MINUTE_IN_SECONDS );
			}
		}

		if ( is_wp_error( $pages ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $pages->get_error_message() ) );
		}

		echo '<div class="apogee-wp-watcher-results-grid">';
		echo '<div class="apogee-wp-watcher-results-col apogee-wp-watcher-results-col--pages">';
		echo '<h2 class="apogee-wp-watcher-results-heading">' . esc_html__( 'Pages', 'apogee-wp-watcher' ) . '</h2>';
		$this->render_pages_panel( is_wp_error( $pages ) ? null : $pages );
		echo '</div>';

		echo '<div class="apogee-wp-watcher-results-col apogee-wp-watcher-results-col--alerts">';
		echo '<h2 class="apogee-wp-watcher-results-heading">' . esc_html__( 'Alerts', 'apogee-wp-watcher' ) . '</h2>';
		$this->render_alerts_panel( $alerts );
		echo '</div>';
		echo '</div>';

		echo '<p class="apogee-wp-watcher-muted">' . esc_html__( 'Lab times are in seconds (Watcher units). Page titles open the page in Watcher; timestamps open that test result. Alerts show the latest 20 for this site.', 'apogee-wp-watcher' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Left column: monitored pages table.
	 *
	 * @param array<string,mixed>|null $pages Pages API payload.
	 */
	private function render_pages_panel( ?array $pages ): void {
		if ( null === $pages ) {
			echo '<p class="apogee-wp-watcher-muted">' . esc_html__( 'Could not load pages.', 'apogee-wp-watcher' ) . '</p>';
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
			return;
		}

		echo '<table class="widefat striped apogee-wp-watcher-table">';
		echo '<thead><tr>';
		foreach (
			array(
				__( 'Page', 'apogee-wp-watcher' ),
				__( 'Tested', 'apogee-wp-watcher' ),
				__( 'Mobile', 'apogee-wp-watcher' ),
				__( 'Desktop', 'apogee-wp-watcher' ),
				__( 'LCP (m)', 'apogee-wp-watcher' ),
				__( 'INP (m)', 'apogee-wp-watcher' ),
				__( 'CLS (m)', 'apogee-wp-watcher' ),
			) as $h
		) {
			printf( '<th>%s</th>', esc_html( $h ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$mobile  = is_array( $row['latest']['mobile'] ?? null ) ? $row['latest']['mobile'] : null;
			$desktop = is_array( $row['latest']['desktop'] ?? null ) ? $row['latest']['desktop'] : null;
			$latest  = $this->pick_latest_result( $mobile, $desktop );

			echo '<tr>';
			echo '<td>' . $this->page_cell( $row ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper.
			echo '<td>' . $this->tested_cell( $latest ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper.
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
	}

	/**
	 * Right column: recent alerts table.
	 *
	 * @param array<string,mixed>|\WP_Error $alerts Alerts API payload or error.
	 */
	private function render_alerts_panel( $alerts ): void {
		if ( is_wp_error( $alerts ) ) {
			printf(
				'<p class="apogee-wp-watcher-muted">%s</p>',
				esc_html( $alerts->get_error_message() )
			);
			return;
		}

		$rows = isset( $alerts['data'] ) && is_array( $alerts['data'] ) ? $alerts['data'] : array();
		if ( ! $rows ) {
			echo '<p class="apogee-wp-watcher-muted">' . esc_html__( 'No alerts for this site yet.', 'apogee-wp-watcher' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped apogee-wp-watcher-table apogee-wp-watcher-alerts-table">';
		echo '<thead><tr>';
		foreach (
			array(
				__( 'When', 'apogee-wp-watcher' ),
				__( 'Metric', 'apogee-wp-watcher' ),
				__( 'Strategy', 'apogee-wp-watcher' ),
				__( 'Actual', 'apogee-wp-watcher' ),
				__( 'Status', 'apogee-wp-watcher' ),
			) as $h
		) {
			printf( '<th>%s</th>', esc_html( $h ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$when     = ! empty( $row['created_at'] ) ? $this->format_tested_at( (string) $row['created_at'] ) : '—';
			$metric   = isset( $row['metric_name'] ) ? (string) $row['metric_name'] : '—';
			$strategy = isset( $row['strategy'] ) ? (string) $row['strategy'] : '—';
			$actual   = array_key_exists( 'actual_value', $row ) && null !== $row['actual_value']
				? (string) $row['actual_value']
				: '—';
			$resolved = ! empty( $row['resolved'] );
			$status   = $resolved
				? __( 'Resolved', 'apogee-wp-watcher' )
				: __( 'Open', 'apogee-wp-watcher' );
			$href     = $this->alert_watcher_url( $row );

			echo '<tr>';
			if ( $href ) {
				printf(
					'<td><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></td>',
					esc_url( $href ),
					esc_html( $when )
				);
			} else {
				printf( '<td>%s</td>', esc_html( $when ) );
			}
			printf( '<td>%s</td>', esc_html( $metric ) );
			printf( '<td>%s</td>', esc_html( $strategy ) );
			printf( '<td>%s</td>', esc_html( $actual ) );
			printf(
				'<td><span class="apogee-wp-watcher-alert-status %s">%s</span></td>',
				$resolved ? 'is-resolved' : 'is-open',
				esc_html( $status )
			);
			echo '</tr>';
		}
		echo '</tbody></table>';

		printf(
			'<p class="apogee-wp-watcher-muted"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
			esc_url( $this->connection->api_base() . '/admin/watcher-alerts' ),
			esc_html__( 'View all alerts in Watcher', 'apogee-wp-watcher' )
		);
	}

	/**
	 * Deep link to an alert in Watcher admin.
	 *
	 * @param array<string,mixed> $row Alert row.
	 */
	private function alert_watcher_url( array $row ): string {
		$alert_id = isset( $row['id'] ) ? (string) $row['id'] : '';
		if ( $alert_id === '' ) {
			return '';
		}

		return $this->connection->api_base() . '/admin/watcher-alerts/' . rawurlencode( $alert_id );
	}

	/**
	 * Page title linking to Watcher page view, with URL as secondary text.
	 *
	 * @param array<string,mixed> $row Page row from API.
	 */
	private function page_cell( array $row ): string {
		$title    = $this->page_title( $row );
		$page_url = $this->page_watcher_url( $row );
		$url      = (string) ( $row['url'] ?? '' );

		$title_html = $page_url
			? sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $page_url ),
				esc_html( $title )
			)
			: esc_html( $title );

		$secondary = '';
		if ( $url !== '' && $url !== $title ) {
			$secondary = sprintf(
				'<div class="apogee-wp-watcher-muted"><code>%s</code></div>',
				esc_html( $url )
			);
		}

		return '<div class="apogee-wp-watcher-page-cell">' . $title_html . $secondary . '</div>';
	}

	/**
	 * Timestamp linking to the Watcher result view.
	 *
	 * @param array<string,mixed>|null $result Latest result summary.
	 */
	private function tested_cell( ?array $result ): string {
		if ( ! $result || empty( $result['tested_at'] ) ) {
			return esc_html( '—' );
		}

		$label = $this->format_tested_at( (string) $result['tested_at'] );
		$href  = $this->result_watcher_url( $result );

		if ( ! $href ) {
			return esc_html( $label );
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $href ),
			esc_html( $label )
		);
	}

	/**
	 * Prefer the more recently completed strategy result.
	 *
	 * @param array<string,mixed>|null $mobile  Mobile summary.
	 * @param array<string,mixed>|null $desktop Desktop summary.
	 * @return array<string,mixed>|null
	 */
	private function pick_latest_result( ?array $mobile, ?array $desktop ): ?array {
		if ( ! $mobile ) {
			return $desktop;
		}
		if ( ! $desktop ) {
			return $mobile;
		}

		$m = strtotime( (string) ( $mobile['tested_at'] ?? '' ) ) ?: 0;
		$d = strtotime( (string) ( $desktop['tested_at'] ?? '' ) ) ?: 0;

		return $d > $m ? $desktop : $mobile;
	}

	/**
	 * @param array<string,mixed> $row Page row.
	 */
	private function page_title( array $row ): string {
		$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
		if ( $name !== '' ) {
			return $name;
		}

		$url = (string) ( $row['url'] ?? '' );
		if ( $url === '' ) {
			return __( 'Untitled page', 'apogee-wp-watcher' );
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( is_string( $path ) && $path !== '' && $path !== '/' ) {
			$slug = trim( $path, '/' );
			$slug = $slug !== '' ? $slug : $path;
			return $slug;
		}

		return $url;
	}

	/**
	 * Deep link to the page in Watcher admin.
	 *
	 * @param array<string,mixed> $row Page row.
	 */
	private function page_watcher_url( array $row ): string {
		if ( ! empty( $row['page_url'] ) && is_string( $row['page_url'] ) ) {
			return $row['page_url'];
		}

		$page_id = isset( $row['id'] ) ? (string) $row['id'] : '';
		if ( $page_id === '' ) {
			return '';
		}

		return $this->connection->api_base() . '/admin/watcher-site-pages/' . rawurlencode( $page_id );
	}

	/**
	 * Deep link to a test result in Watcher admin.
	 *
	 * @param array<string,mixed> $result Result summary.
	 */
	private function result_watcher_url( array $result ): string {
		if ( ! empty( $result['result_url'] ) && is_string( $result['result_url'] ) ) {
			return $result['result_url'];
		}

		$result_id = isset( $result['id'] ) ? (string) $result['id'] : '';
		if ( $result_id === '' ) {
			return '';
		}

		return $this->connection->api_base() . '/admin/watcher-site-test-results/' . rawurlencode( $result_id );
	}

	/**
	 * Format ISO timestamp for wp-admin (site timezone).
	 *
	 * @param string $iso ISO-8601 datetime.
	 */
	private function format_tested_at( string $iso ): string {
		$ts = strtotime( $iso );
		if ( ! $ts ) {
			return $iso;
		}

		return wp_date(
			sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) ),
			$ts
		);
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
