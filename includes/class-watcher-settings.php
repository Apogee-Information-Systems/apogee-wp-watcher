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
	 * @var Apogee_Watcher_Tracked_Pages
	 */
	private $tracked_pages;

	/**
	 * @param Apogee_Watcher_Connection    $connection    Connection.
	 * @param Apogee_Watcher_Api_Client    $api           API.
	 * @param Apogee_Watcher_Tracked_Pages $tracked_pages Tracked pages UI.
	 */
	public function __construct(
		Apogee_Watcher_Connection $connection,
		Apogee_Watcher_Api_Client $api,
		Apogee_Watcher_Tracked_Pages $tracked_pages
	) {
		$this->connection    = $connection;
		$this->api           = $api;
		$this->tracked_pages = $tracked_pages;
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
			__( 'Apogee Watcher: Automated PageSpeed Monitoring', 'apogee-watcher' ),
			__( 'Watcher', 'apogee-watcher' ),
			'manage_options',
			'apogee-watcher',
			array( $this, 'render_settings' ),
			'dashicons-performance',
			58
		);

		add_submenu_page(
			'apogee-watcher',
			__( 'Connection', 'apogee-watcher' ),
			__( 'Connection', 'apogee-watcher' ),
			'manage_options',
			'apogee-watcher',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Assets on plugin screens.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'apogee-watcher' ) && 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'apogee-watcher-admin',
			APOGEE_WATCHER_URL . 'assets/css/admin.css',
			array(),
			APOGEE_WATCHER_VERSION
		);

		if ( false !== strpos( $hook, 'apogee-watcher' ) ) {
			wp_enqueue_script(
				'apogee-watcher-admin',
				APOGEE_WATCHER_URL . 'assets/js/admin.js',
				array(),
				APOGEE_WATCHER_VERSION,
				true
			);
			wp_localize_script(
				'apogee-watcher-admin',
				'apogeeWpWatcher',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'apogee_watcher_admin' ),
					'apiBase' => $this->connection->api_base(),
					'i18n'    => array(
						'connecting'   => __( 'Starting pairing…', 'apogee-watcher' ),
						'waiting'      => __( 'Waiting for approval in Watcher…', 'apogee-watcher' ),
						'connected'    => __( 'Connected.', 'apogee-watcher' ),
						'disconnecting'=> __( 'Disconnecting…', 'apogee-watcher' ),
						'error'        => __( 'Something went wrong.', 'apogee-watcher' ),
						'confirmDisc'  => __( 'Disconnect this site from Apogee Watcher?', 'apogee-watcher' ),
						'bulkNeed'     => __( 'Select at least one page and a bulk action.', 'apogee-watcher' ),
						'bulkWorking'  => __( 'Updating Watcher…', 'apogee-watcher' ),
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
		$dashboard = $this->connection->site_admin_url();
		$signup    = $api_base . '/sign-up?utm_source=wordpress&utm_medium=plugin&utm_campaign=settings';
		?>
		<div class="wrap apogee-watcher-wrap">
			<h1><?php echo esc_html__( 'Apogee Watcher: Automated PageSpeed Monitoring', 'apogee-watcher' ); ?></h1>
			<p class="apogee-watcher-lead">
				<?php
				echo esc_html__(
					'Monitor this site’s Core Web Vitals with Apogee Watcher. No Google PageSpeed API key required in WordPress.',
					'apogee-watcher'
				);
				?>
			</p>

			<div class="apogee-watcher-card" id="apogee-watcher-connection">
				<h2><?php echo esc_html__( 'Connection', 'apogee-watcher' ); ?></h2>

				<?php if ( $connected ) : ?>
					<p class="apogee-watcher-status is-connected">
						<strong><?php echo esc_html__( 'Connected', 'apogee-watcher' ); ?></strong>
						<?php if ( $host ) : ?>
							— <?php echo esc_html( $host ); ?>
						<?php endif; ?>
						<?php if ( $site_id ) : ?>
							<span class="apogee-watcher-muted">(site #<?php echo esc_html( $site_id ); ?>)</span>
						<?php endif; ?>
					</p>
					<p>
						<a class="button" href="<?php echo esc_url( $dashboard ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html__( 'Open Watcher dashboard', 'apogee-watcher' ); ?>
						</a>
						<button type="button" class="button button-secondary" id="apogee-watcher-disconnect">
							<?php echo esc_html__( 'Disconnect', 'apogee-watcher' ); ?>
						</button>
					</p>
				<?php else : ?>
					<p class="apogee-watcher-status is-disconnected">
						<?php echo esc_html__( 'Not connected.', 'apogee-watcher' ); ?>
					</p>
					<p>
						<?php
						echo esc_html__(
							'Connect opens Apogee Watcher so you can create a free account (using your WordPress admin email) or sign in, then approve this site.',
							'apogee-watcher'
						);
						?>
					</p>
					<p>
						<button type="button" class="button button-primary" id="apogee-watcher-connect">
							<?php echo esc_html__( 'Connect to Watcher', 'apogee-watcher' ); ?>
						</button>
						<a class="button button-link" href="<?php echo esc_url( $signup ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html__( 'Create free account on Watcher', 'apogee-watcher' ); ?>
						</a>
					</p>
					<div id="apogee-watcher-pairing" class="apogee-watcher-pairing" hidden>
						<p>
							<?php echo esc_html__( 'Pairing code:', 'apogee-watcher' ); ?>
							<code id="apogee-watcher-user-code" class="apogee-watcher-user-code"></code>
						</p>
						<p>
							<a
								id="apogee-watcher-verify-link"
								class="button button-primary"
								href="#"
								target="_blank"
								rel="noopener noreferrer"
								aria-label="<?php echo esc_attr__( 'Open Watcher to approve', 'apogee-watcher' ); ?>"
							><?php echo esc_html__( 'Open Watcher to approve', 'apogee-watcher' ); ?></a>
						</p>
						<p id="apogee-watcher-pairing-status" class="apogee-watcher-muted"></p>
					</div>
				<?php endif; ?>

				<p id="apogee-watcher-flash" class="apogee-watcher-flash" role="status" aria-live="polite"></p>
			</div>

			<?php
			if ( $connected ) {
				$this->tracked_pages->render();
			}
			?>

			<div class="apogee-watcher-card">
				<h2><?php echo esc_html__( 'About this service', 'apogee-watcher' ); ?></h2>
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: apogeewatcher.com link */
							__( 'This plugin connects to %s. When you click Connect, the site URL and your admin email (as a suggested account address) are sent to Watcher so you can approve pairing. Tokens are stored in WordPress options. See Watcher’s terms and privacy on the Watcher site.', 'apogee-watcher' ),
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
