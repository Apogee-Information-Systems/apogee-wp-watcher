<?php
/**
 * Connection screen: WordPress pages vs Watcher scheduled tracking.
 *
 * @package ApogeeWatcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Apogee_Watcher_Tracked_Pages
 */
class Apogee_Watcher_Tracked_Pages {

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
		add_action( 'wp_ajax_apogee_watcher_pages_bulk', array( $this, 'ajax_bulk' ) );
	}

	/**
	 * Render the Tracked pages card (call from settings when connected).
	 */
	public function render(): void {
		if ( ! $this->connection->is_connected() ) {
			return;
		}

		$rows = $this->build_rows();
		?>
		<div class="apogee-watcher-card" id="apogee-watcher-tracked-pages">
			<h2><?php echo esc_html__( 'Tracked pages', 'apogee-watcher' ); ?></h2>
			<p class="apogee-watcher-muted">
				<?php
				echo esc_html__(
					'Published WordPress pages and posts. Include adds the URL to Watcher (or resumes scheduling). Pause scheduling leaves the page in Watcher but stops scheduled tests.',
					'apogee-watcher'
				);
				?>
			</p>

			<?php if ( is_wp_error( $rows ) ) : ?>
				<?php
				$err_data   = $rows->get_error_data();
				$err_status = is_array( $err_data ) ? (int) ( $err_data['status'] ?? 0 ) : 0;
				?>
				<p class="apogee-watcher-flash is-error">
					<?php echo esc_html( $rows->get_error_message() ); ?>
					<?php if ( 403 === $err_status ) : ?>
						<?php
						echo ' ' . esc_html__(
							'Disconnect and Connect again so Watcher can grant page-write access.',
							'apogee-watcher'
						);
						?>
					<?php endif; ?>
				</p>
			<?php elseif ( empty( $rows ) ) : ?>
				<p><?php echo esc_html__( 'No published pages or posts found.', 'apogee-watcher' ); ?></p>
			<?php else : ?>
				<form id="apogee-watcher-tracked-form" method="post" action="">
					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<label for="apogee-watcher-bulk" class="screen-reader-text">
								<?php echo esc_html__( 'Select bulk action', 'apogee-watcher' ); ?>
							</label>
							<select name="bulk_action" id="apogee-watcher-bulk">
								<option value=""><?php echo esc_html__( 'Bulk actions', 'apogee-watcher' ); ?></option>
								<option value="include"><?php echo esc_html__( 'Include in scheduled tests', 'apogee-watcher' ); ?></option>
								<option value="pause"><?php echo esc_html__( 'Pause scheduled tests', 'apogee-watcher' ); ?></option>
							</select>
							<button type="button" class="button action" id="apogee-watcher-bulk-apply">
								<?php echo esc_html__( 'Apply', 'apogee-watcher' ); ?>
							</button>
						</div>
					</div>
					<table class="widefat striped apogee-watcher-tracked-table">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column">
									<input
										type="checkbox"
										id="apogee-watcher-tracked-select-all"
										aria-label="<?php echo esc_attr__( 'Select all tracked pages', 'apogee-watcher' ); ?>"
									/>
								</td>
								<th><?php echo esc_html__( 'Title', 'apogee-watcher' ); ?></th>
								<th><?php echo esc_html__( 'URL', 'apogee-watcher' ); ?></th>
								<th><?php echo esc_html__( 'In Watcher', 'apogee-watcher' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<th scope="row" class="check-column">
										<input
											type="checkbox"
											class="apogee-watcher-tracked-cb"
											name="posts[]"
											value="<?php echo esc_attr( (string) $row['external_id'] ); ?>"
											data-url="<?php echo esc_attr( $row['url'] ); ?>"
											data-title="<?php echo esc_attr( $row['title'] ); ?>"
											aria-label="<?php echo esc_attr( sprintf( /* translators: %s: page title */ __( 'Select %s', 'apogee-watcher' ), $row['title'] ) ); ?>"
										/>
									</th>
									<td>
										<strong><?php echo esc_html( $row['title'] ); ?></strong>
										<span class="apogee-watcher-muted"> (<?php echo esc_html( $row['type'] ); ?>)</span>
									</td>
									<td><code class="apogee-watcher-url"><?php echo esc_html( $row['url'] ); ?></code></td>
									<td>
										<?php if ( $row['tracked'] && $row['active'] ) : ?>
											<span class="apogee-watcher-track is-on"><?php echo esc_html__( 'Scheduled', 'apogee-watcher' ); ?></span>
										<?php elseif ( $row['tracked'] ) : ?>
											<span class="apogee-watcher-track is-paused"><?php echo esc_html__( 'Paused', 'apogee-watcher' ); ?></span>
										<?php else : ?>
											<span class="apogee-watcher-track is-off"><?php echo esc_html__( 'Not tracked', 'apogee-watcher' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</form>
				<p id="apogee-watcher-tracked-flash" class="apogee-watcher-flash" role="status" aria-live="polite"></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Bulk include / pause via pages/batch.
	 */
	public function ajax_bulk(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'apogee-watcher' ) ), 403 );
		}
		check_ajax_referer( 'apogee_watcher_admin', 'nonce' );

		if ( ! $this->connection->is_connected() ) {
			wp_send_json_error( array( 'message' => __( 'Not connected to Watcher.', 'apogee-watcher' ) ), 400 );
		}

		$action = isset( $_POST['bulk_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['bulk_action'] ) ) : '';
		if ( ! in_array( $action, array( 'include', 'pause' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a bulk action.', 'apogee-watcher' ) ), 400 );
		}

		$decoded = null;
		if ( isset( $_POST['pages'] ) && is_string( $_POST['pages'] ) ) {
			$decoded = json_decode( sanitize_textarea_field( wp_unslash( $_POST['pages'] ) ), true );
		} elseif ( isset( $_POST['pages'] ) && is_array( $_POST['pages'] ) ) {
			$decoded = map_deep( wp_unslash( $_POST['pages'] ), 'sanitize_text_field' );
		}
		if ( ! is_array( $decoded ) || $decoded === array() ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one page.', 'apogee-watcher' ) ), 400 );
		}

		$pages   = array();
		$include = ( 'include' === $action );
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$url = isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '';
			if ( $url === '' || 0 !== strpos( $url, 'http' ) ) {
				continue;
			}
			$external = isset( $item['external_id'] ) ? sanitize_text_field( (string) $item['external_id'] ) : '';
			$name     = isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '';
			$row      = array(
				'url'      => $url,
				'included' => $include,
			);
			if ( $external !== '' ) {
				$row['external_id'] = $external;
			}
			if ( $name !== '' ) {
				$row['name'] = $name;
			}
			$pages[] = $row;
		}

		if ( $pages === array() ) {
			wp_send_json_error( array( 'message' => __( 'No valid pages selected.', 'apogee-watcher' ) ), 400 );
		}

		$result = $this->api->pages_batch( $pages );
		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 400 );
			$msg    = $result->get_error_message();
			if ( 403 === $status ) {
				$msg = __( 'Watcher denied page updates. Disconnect and Connect again to grant page-write access.', 'apogee-watcher' );
			}
			wp_send_json_error( array( 'message' => $msg ), $status > 0 ? $status : 400 );
		}

		$items = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
		$ok    = 0;
		$bad   = 0;
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['status'] ) && 'rejected' === $item['status'] ) {
				++$bad;
			} else {
				++$ok;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: updated count, 2: rejected count */
					__( 'Updated %1$d page(s); %2$d rejected.', 'apogee-watcher' ),
					$ok,
					$bad
				),
				'reload'  => true,
			)
		);
	}

	/**
	 * @return list<array{external_id: string, title: string, url: string, type: string, tracked: bool, active: bool}>|\WP_Error
	 */
	private function build_rows() {
		$watcher = $this->fetch_all_watcher_pages();
		if ( is_wp_error( $watcher ) ) {
			return $watcher;
		}

		$by_external = array();
		$by_url      = array();
		foreach ( $watcher as $page ) {
			if ( ! empty( $page['external_id'] ) && is_string( $page['external_id'] ) ) {
				$by_external[ $page['external_id'] ] = $page;
			}
			if ( ! empty( $page['url'] ) && is_string( $page['url'] ) ) {
				$by_url[ $this->normalize_url_key( $page['url'] ) ] = $page;
			}
		}

		$posts = get_posts(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$rows = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$permalink = get_permalink( $post );
			if ( ! is_string( $permalink ) || $permalink === '' ) {
				continue;
			}
			$external = $post->post_type . ':' . $post->ID;
			$match    = $by_external[ $external ] ?? $by_url[ $this->normalize_url_key( $permalink ) ] ?? null;
			$rows[]   = array(
				'external_id' => $external,
				'title'       => get_the_title( $post ),
				'url'         => $permalink,
				'type'        => $post->post_type,
				'tracked'     => is_array( $match ),
				'active'      => is_array( $match ) && ! empty( $match['active'] ),
			);
		}

		return $rows;
	}

	/**
	 * @return list<array<string, mixed>>|\WP_Error
	 */
	private function fetch_all_watcher_pages() {
		$all    = array();
		$cursor = null;
		for ( $i = 0; $i < 20; $i++ ) {
			$payload = $this->api->get_pages( $cursor, 100 );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}
			$data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
			foreach ( $data as $row ) {
				if ( is_array( $row ) ) {
					$all[] = $row;
				}
			}
			$next = isset( $payload['next_cursor'] ) ? $payload['next_cursor'] : null;
			if ( ! is_string( $next ) || $next === '' ) {
				break;
			}
			$cursor = $next;
		}
		return $all;
	}

	/**
	 * Loose URL key for matching WP permalinks to Watcher URLs.
	 */
	private function normalize_url_key( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return strtolower( untrailingslashit( $url ) );
		}
		$host = strtolower( (string) $parts['host'] );
		$host = preg_replace( '/^www\./', '', $host );
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$path = untrailingslashit( $path );
		if ( $path === '' ) {
			$path = '/';
		}
		$query = isset( $parts['query'] ) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
		return $host . $path . $query;
	}
}
