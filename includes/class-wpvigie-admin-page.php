<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Admin_Page {

	private string          $page_hook = '';
	private WPVigie_History $history;

	public function __construct( WPVigie_History $history ) {
		$this->history = $history;
	}

	public function register_menu(): void {
		$hook = add_management_page(
			__( 'WP Vigie — Health Check', 'wp-vigie' ),
			__( 'WP Vigie', 'wp-vigie' ),
			'manage_options',
			'wp-vigie',
			[ $this, 'render' ]
		);

		$this->page_hook = ( false !== $hook ) ? $hook : '';
	}

	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->page_hook || $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'wpvigie-admin',
			WPVIGIE_URL . 'assets/admin.css',
			[],
			WPVIGIE_VERSION
		);

		wp_enqueue_script(
			'wpvigie-admin',
			WPVIGIE_URL . 'assets/admin.js',
			[],
			WPVIGIE_VERSION,
			true
		);

		wp_localize_script(
			'wpvigie-admin',
			'WPVigieData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpvigie_admin' ),
				'history' => [
					'scans'      => $this->history->get_recent( 30 ),
					'chart_data' => $this->history->get_chart_data( 30 ),
				],
				'i18n'    => [
					'running'       => __( 'Running scan…', 'wp-vigie' ),
					'runScan'       => __( 'Run Scan', 'wp-vigie' ),
					'error'         => __( 'Scan failed. Check error log.', 'wp-vigie' ),
					'noHistory'     => __( 'No scans yet.', 'wp-vigie' ),
					'settingsSaved' => __( 'Settings saved.', 'wp-vigie' ),
					'view'          => __( 'View', 'wp-vigie' ),
					'date'          => __( 'Date', 'wp-vigie' ),
					'score'         => __( 'Score', 'wp-vigie' ),
					'trigger'       => __( 'Trigger', 'wp-vigie' ),
					'lastScan'      => __( 'Last scan', 'wp-vigie' ),
					'viewingFrom'   => __( 'Viewing scan from', 'wp-vigie' ),
					'healthy'       => __( 'Healthy', 'wp-vigie' ),
					'needsAttention'=> __( 'Needs attention', 'wp-vigie' ),
					'critical'      => __( 'Critical', 'wp-vigie' ),
					'showAll'       => __( 'Show all', 'wp-vigie' ),
				],
			]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = WPVigie_Settings::get_all();
		?>
		<div class="wrap wpvigie-wrap">

			<?php /* ── Header ── */ ?>
			<div class="wpvigie-header">
				<div>
					<h1><?php esc_html_e( 'WP Vigie', 'wp-vigie' ); ?></h1>
					<p class="wpvigie-header__sub">
						<?php esc_html_e( '10-point health audit for your WordPress site.', 'wp-vigie' ); ?>
					</p>
				</div>
				<button id="wpvigie-run-scan" class="wpvigie-btn" type="button">
					<?php esc_html_e( 'Run Scan', 'wp-vigie' ); ?>
				</button>
			</div>

			<?php /* ── Tabs ── */ ?>
			<div class="wpvigie-tabs" role="tablist"
			     aria-label="<?php esc_attr_e( 'WP Vigie sections', 'wp-vigie' ); ?>">
				<button role="tab" aria-selected="true"  data-tab="dashboard">
					<?php esc_html_e( 'Dashboard', 'wp-vigie' ); ?>
				</button>
				<button role="tab" aria-selected="false" data-tab="settings">
					<?php esc_html_e( 'Settings', 'wp-vigie' ); ?>
				</button>
			</div>

			<?php /* ── Dashboard tab ── */ ?>
			<div id="wpvigie-tab-dashboard" class="wpvigie-tab-panel" role="tabpanel">

				<?php /* Score ring */ ?>
				<div class="wpvigie-score-wrap">
					<div class="wpvigie-score">
						<svg class="wpvigie-score__ring" viewBox="0 0 120 120"
						     role="img"
						     aria-labelledby="wpv-ring-title wpv-ring-desc">
							<title id="wpv-ring-title">
								<?php esc_html_e( 'Health Score', 'wp-vigie' ); ?>
							</title>
							<desc id="wpv-ring-desc">
								<?php esc_html_e( 'Animated ring showing site health score out of 100.', 'wp-vigie' ); ?>
							</desc>
							<circle cx="60" cy="60" r="54"
							        fill="none"
							        stroke="#e2e8f0"
							        stroke-width="10" />
							<circle class="wpvigie-score__progress"
							        cx="60" cy="60" r="54"
							        fill="none"
							        stroke="transparent"
							        stroke-width="10"
							        stroke-linecap="round"
							        stroke-dasharray="339.292"
							        stroke-dashoffset="339.292"
							        transform="rotate(-90 60 60)" />
						</svg>
						<div class="wpvigie-score__center">
							<div class="wpvigie-score__number">—</div>
							<div class="wpvigie-score__label">/ 100</div>
						</div>
					</div>
					<div class="wpvigie-score__meta">
						<div class="wpvigie-score__status" id="wpvigie-score-status">
							<?php esc_html_e( 'Run a scan to check your site health.', 'wp-vigie' ); ?>
						</div>
						<div class="wpvigie-score__time" id="wpvigie-score-time"></div>
					</div>
				</div>

				<?php /* Aria live region for screen readers */ ?>
				<div id="wpvigie-sr-live"
				     class="screen-reader-text"
				     aria-live="polite"
				     aria-atomic="true"></div>

				<?php /* Card grid — populated by JS after scan */ ?>
				<div class="wpvigie-cards" id="wpvigie-cards"></div>

				<?php /* History section */ ?>
				<section class="wpvigie-history"
				         aria-label="<?php esc_attr_e( 'Scan history', 'wp-vigie' ); ?>">
					<h2 class="wpvigie-section-title">
						<?php esc_html_e( 'History (last 30 days)', 'wp-vigie' ); ?>
					</h2>
					<div class="wpvigie-chart-wrap">
						<svg id="wpvigie-chart" class="wpvigie-chart" aria-hidden="true"></svg>
					</div>
					<div class="wpvigie-table-wrap" id="wpvigie-history-table"></div>
				</section>

			</div><?php /* end dashboard tab */ ?>

			<?php /* ── Settings tab ── */ ?>
			<div id="wpvigie-tab-settings" class="wpvigie-tab-panel" role="tabpanel" hidden>

				<form id="wpvigie-settings-form" class="wpvigie-settings">

					<div class="wpvigie-settings-card">
						<h3><?php esc_html_e( 'Scheduled Scans', 'wp-vigie' ); ?></h3>

						<div class="wpvigie-field">
							<label class="wpvigie-toggle">
								<input type="checkbox"
								       name="daily_scan_enabled"
								       value="1"
								       <?php checked( true, (bool) $settings['daily_scan_enabled'] ); ?>>
								<?php esc_html_e( 'Enable daily automatic scans', 'wp-vigie' ); ?>
							</label>
							<p class="wpvigie-field__desc">
								<?php esc_html_e( 'Run a scan automatically once per day and keep a history of your scores.', 'wp-vigie' ); ?>
							</p>
						</div>

						<div class="wpvigie-field">
							<label for="wpvigie-scan-hour">
								<?php esc_html_e( 'Scan time (server hour)', 'wp-vigie' ); ?>
							</label>
							<select id="wpvigie-scan-hour" name="scan_time_hour">
								<?php for ( $h = 0; $h <= 23; $h++ ) : ?>
									<option value="<?php echo esc_attr( $h ); ?>"
									        <?php selected( (int) $settings['scan_time_hour'], $h ); ?>>
										<?php echo esc_html( sprintf( '%02d:00', $h ) ); ?>
									</option>
								<?php endfor; ?>
							</select>
							<p class="wpvigie-field__desc">
								<?php esc_html_e( 'Server local time. Choose an off-peak hour.', 'wp-vigie' ); ?>
							</p>
						</div>
					</div>

					<div class="wpvigie-settings-card">
						<h3><?php esc_html_e( 'History', 'wp-vigie' ); ?></h3>

						<div class="wpvigie-field">
							<label for="wpvigie-retention">
								<?php esc_html_e( 'Keep history for (days)', 'wp-vigie' ); ?>
							</label>
							<input type="number"
							       id="wpvigie-retention"
							       name="history_retention_days"
							       value="<?php echo esc_attr( $settings['history_retention_days'] ); ?>"
							       min="1" max="365" step="1">
							<p class="wpvigie-field__desc">
								<?php esc_html_e( 'Scans older than this are automatically deleted.', 'wp-vigie' ); ?>
							</p>
						</div>
					</div>

					<div class="wpvigie-settings-actions">
						<button type="submit" class="wpvigie-btn">
							<?php esc_html_e( 'Save Settings', 'wp-vigie' ); ?>
						</button>
						<span id="wpvigie-settings-msg" class="wpvigie-settings-msg"></span>
					</div>

				</form>
			</div><?php /* end settings tab */ ?>

			<?php /* ── Footer (v0.1 bug fixed: no Markdown, proper <a> tags) ── */ ?>
			<footer class="wpvigie-footer">
				<?php
				printf(
					wp_kses(
						/* translators: 1: ODACOM agency link, 2: WPSentinel Cloud link */
						__( 'Built by %1$s. Need continuous monitoring? Try %2$s.', 'wp-vigie' ),
						[
							'a' => [
								'href'   => [],
								'target' => [],
								'rel'    => [],
							],
						]
					),
					'<a href="https://odacom.com" target="_blank" rel="noopener noreferrer">ODACOM</a>',
					'<a href="https://wpsentinel.net" target="_blank" rel="noopener noreferrer">WPSentinel Cloud</a>'
				);
				?>
			</footer>

		</div><?php /* end .wpvigie-wrap */ ?>
		<?php
	}

	public function handle_ajax_scan(): void {
		check_ajax_referer( 'wpvigie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'wp-vigie' ) ], 403 );
		}

		$checker = new WPVigie_Checker();
		$results = $checker->run_all_checks();
		$score   = WPVigie_Scorer::score( $results );
		$scan_id = $this->history->save( $score, $results, 'manual' );

		wp_send_json_success(
			[
				'score'   => $score,
				'results' => $results,
				'scan_id' => $scan_id,
			]
		);
	}
}
