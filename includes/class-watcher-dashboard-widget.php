<?php
/**
 * Dashboard widget: latest overview scores.
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Dashboard_Widget
 */
class Apogee_Watcher_Dashboard_Widget {

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
		add_action( 'wp_dashboard_setup', array( $this, 'register' ) );
	}

	/**
	 * Register widget.
	 */
	public function register(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'apogee_wp_watcher_overview',
			__( 'Apogee Watcher for WordPress', 'apogee-wp-watcher' ),
			array( $this, 'render' )
		);
	}

	/**
	 * Widget body.
	 */
	public function render(): void {
		if ( ! $this->connection->is_connected() ) {
			printf(
				'<p>%s</p><p><a class="button button-primary" href="%s">%s</a></p>',
				esc_html__( 'Connect this site to Apogee Watcher to see Core Web Vitals here.', 'apogee-wp-watcher' ),
				esc_url( admin_url( 'admin.php?page=apogee-wp-watcher' ) ),
				esc_html__( 'Connect', 'apogee-wp-watcher' )
			);
			return;
		}

		$overview = get_transient( 'apogee_wp_watcher_overview' );
		if ( false === $overview ) {
			$overview = $this->api->get_overview();
			if ( ! is_wp_error( $overview ) ) {
				set_transient( 'apogee_wp_watcher_overview', $overview, 10 * MINUTE_IN_SECONDS );
			}
		}

		if ( is_wp_error( $overview ) ) {
			printf(
				'<p class="apogee-wp-watcher-error">%s</p>',
				esc_html( $overview->get_error_message() )
			);
			return;
		}

		$latest = $overview['latest_test'] ?? null;
		$afs    = $overview['attention_free_score'] ?? null;
		$open   = isset( $overview['open_alerts'] ) ? (int) $overview['open_alerts'] : 0;
		$domain = $overview['site']['domain'] ?? '';

		echo '<div class="apogee-wp-watcher-widget">';
		if ( $domain ) {
			printf( '<p class="apogee-wp-watcher-muted">%s</p>', esc_html( $domain ) );
		}

		if ( is_array( $latest ) ) {
			$score = $latest['performance_score'] ?? null;
			printf(
				'<p class="apogee-wp-watcher-score"><span class="apogee-wp-watcher-score-value">%s</span> <span class="apogee-wp-watcher-muted">%s · %s</span></p>',
				esc_html( null !== $score ? (string) (int) $score : '—' ),
				esc_html__( 'Performance', 'apogee-wp-watcher' ),
				esc_html( (string) ( $latest['strategy'] ?? '' ) )
			);
			echo '<ul class="apogee-wp-watcher-cwv">';
			foreach ( array( 'lcp' => 'LCP', 'inp' => 'INP', 'cls' => 'CLS' ) as $key => $label ) {
				$val = $latest[ $key ] ?? null;
				printf(
					'<li><strong>%s</strong> %s</li>',
					esc_html( $label ),
					esc_html( null !== $val && '' !== $val ? (string) $val : '—' )
				);
			}
			echo '</ul>';
			if ( ! empty( $latest['tested_at'] ) ) {
				printf(
					'<p class="apogee-wp-watcher-muted">%s %s</p>',
					esc_html__( 'Last test:', 'apogee-wp-watcher' ),
					esc_html( (string) $latest['tested_at'] )
				);
			}
		} else {
			echo '<p>' . esc_html__( 'No completed tests yet for this site.', 'apogee-wp-watcher' ) . '</p>';
		}

		if ( is_array( $afs ) && isset( $afs['percent'] ) && null !== $afs['percent'] ) {
			printf(
				'<p>%s <strong>%d%%</strong> <span class="apogee-wp-watcher-muted">(%d/%d)</span></p>',
				esc_html__( 'Attention-free:', 'apogee-wp-watcher' ),
				(int) $afs['percent'],
				(int) ( $afs['clear'] ?? 0 ),
				(int) ( $afs['total'] ?? 0 )
			);
		}

		if ( $open > 0 ) {
			printf(
				'<p class="apogee-wp-watcher-alert-count">%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: open alert count */
						_n( '%d open alert', '%d open alerts', $open, 'apogee-wp-watcher' ),
						$open
					)
				)
			);
		}

		printf(
			'<p><a href="%s">%s</a> · <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=apogee-wp-watcher-results' ) ),
			esc_html__( 'Test results', 'apogee-wp-watcher' ),
			esc_url( $this->connection->site_admin_url() ),
			esc_html__( 'View in Watcher', 'apogee-wp-watcher' )
		);
		echo '</div>';
	}
}
