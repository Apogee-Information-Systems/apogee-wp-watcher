<?php
/**
 * Watcher settings / Connect screen.
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Settings
 */
class Apogee_Watcher_Settings {

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
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Admin menu.
	 */
	public function menu(): void {
		add_menu_page(
			__( 'Apogee Watcher for WordPress', 'apogee-wp-watcher' ),
			__( 'Watcher', 'apogee-wp-watcher' ),
			'manage_options',
			'apogee-wp-watcher',
			array( $this, 'render_settings' ),
			'dashicons-performance',
			58
		);

		add_submenu_page(
			'apogee-wp-watcher',
			__( 'Connection', 'apogee-wp-watcher' ),
			__( 'Connection', 'apogee-wp-watcher' ),
			'manage_options',
			'apogee-wp-watcher',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Assets on plugin screens.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'apogee-wp-watcher' ) && 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'apogee-wp-watcher-admin',
			APOGEE_WP_WATCHER_URL . 'assets/css/admin.css',
			array(),
			APOGEE_WP_WATCHER_VERSION
		);

		if ( false !== strpos( $hook, 'apogee-wp-watcher' ) ) {
			wp_enqueue_script(
				'apogee-wp-watcher-admin',
				APOGEE_WP_WATCHER_URL . 'assets/js/admin.js',
				array(),
				APOGEE_WP_WATCHER_VERSION,
				true
			);
			wp_localize_script(
				'apogee-wp-watcher-admin',
				'apogeeWpWatcher',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'apogee_wp_watcher_admin' ),
					'apiBase' => $this->connection->api_base(),
					'i18n'    => array(
						'connecting'   => __( 'Starting pairing…', 'apogee-wp-watcher' ),
						'waiting'      => __( 'Waiting for approval in Watcher…', 'apogee-wp-watcher' ),
						'connected'    => __( 'Connected.', 'apogee-wp-watcher' ),
						'disconnecting'=> __( 'Disconnecting…', 'apogee-wp-watcher' ),
						'error'        => __( 'Something went wrong.', 'apogee-wp-watcher' ),
						'confirmDisc'  => __( 'Disconnect this site from Apogee Watcher?', 'apogee-wp-watcher' ),
					),
				)
			);
		}
	}

	/**
	 * Settings page markup.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connected = $this->connection->is_connected();
		$site_id   = (string) $this->connection->get( 'site_id', '' );
		$host      = (string) $this->connection->get( 'site_host', '' );
		$api_base  = $this->connection->api_base();
		$dashboard = $api_base . '/admin';
		$signup    = $api_base . '/sign-up?utm_source=wordpress&utm_medium=plugin&utm_campaign=settings';
		?>
		<div class="wrap apogee-wp-watcher-wrap">
			<h1><?php echo esc_html__( 'Apogee Watcher for WordPress', 'apogee-wp-watcher' ); ?></h1>
			<p class="apogee-wp-watcher-lead">
				<?php
				echo esc_html__(
					'Monitor this site’s Core Web Vitals with Apogee Watcher. No Google PageSpeed API key required in WordPress.',
					'apogee-wp-watcher'
				);
				?>
			</p>

			<div class="apogee-wp-watcher-card" id="apogee-wp-watcher-connection">
				<h2><?php echo esc_html__( 'Connection', 'apogee-wp-watcher' ); ?></h2>

				<?php if ( $connected ) : ?>
					<p class="apogee-wp-watcher-status is-connected">
						<strong><?php echo esc_html__( 'Connected', 'apogee-wp-watcher' ); ?></strong>
						<?php if ( $host ) : ?>
							— <?php echo esc_html( $host ); ?>
						<?php endif; ?>
						<?php if ( $site_id ) : ?>
							<span class="apogee-wp-watcher-muted">(site #<?php echo esc_html( $site_id ); ?>)</span>
						<?php endif; ?>
					</p>
					<p>
						<a class="button" href="<?php echo esc_url( $dashboard ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html__( 'Open Watcher dashboard', 'apogee-wp-watcher' ); ?>
						</a>
						<button type="button" class="button button-secondary" id="apogee-wp-watcher-disconnect">
							<?php echo esc_html__( 'Disconnect', 'apogee-wp-watcher' ); ?>
						</button>
					</p>
				<?php else : ?>
					<p class="apogee-wp-watcher-status is-disconnected">
						<?php echo esc_html__( 'Not connected.', 'apogee-wp-watcher' ); ?>
					</p>
					<p>
						<?php
						echo esc_html__(
							'Connect opens Apogee Watcher so you can create a free account (using your WordPress admin email) or sign in, then approve this site.',
							'apogee-wp-watcher'
						);
						?>
					</p>
					<p>
						<button type="button" class="button button-primary" id="apogee-wp-watcher-connect">
							<?php echo esc_html__( 'Connect to Watcher', 'apogee-wp-watcher' ); ?>
						</button>
						<a class="button button-link" href="<?php echo esc_url( $signup ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html__( 'Create free account on Watcher', 'apogee-wp-watcher' ); ?>
						</a>
					</p>
					<div id="apogee-wp-watcher-pairing" class="apogee-wp-watcher-pairing" hidden>
						<p>
							<?php echo esc_html__( 'Pairing code:', 'apogee-wp-watcher' ); ?>
							<code id="apogee-wp-watcher-user-code" class="apogee-wp-watcher-user-code"></code>
						</p>
						<p>
							<a id="apogee-wp-watcher-verify-link" class="button button-primary" href="#" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html__( 'Open Watcher to approve', 'apogee-wp-watcher' ); ?>
							</a>
						</p>
						<p id="apogee-wp-watcher-pairing-status" class="apogee-wp-watcher-muted"></p>
					</div>
				<?php endif; ?>

				<p id="apogee-wp-watcher-flash" class="apogee-wp-watcher-flash" role="status" aria-live="polite"></p>
			</div>

			<div class="apogee-wp-watcher-card">
				<h2><?php echo esc_html__( 'About this service', 'apogee-wp-watcher' ); ?></h2>
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: apogeewatcher.com link */
							__( 'This plugin connects to %s. When you click Connect, the site URL and your admin email (as a suggested account address) are sent to Watcher so you can approve pairing. Tokens are stored in WordPress options. See Watcher’s terms and privacy on the Watcher site.', 'apogee-wp-watcher' ),
							'<a href="' . esc_url( $api_base ) . '" target="_blank" rel="noopener noreferrer">apogeewatcher.com</a>'
						)
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
