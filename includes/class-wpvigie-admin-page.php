<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Admin_Page {

	private string $page_hook = '';

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
				'nonce'   => wp_create_nonce( 'wpvigie_scan' ),
				'i18n'    => [
					'running' => __( 'Running scan…', 'wp-vigie' ),
					'error'   => __( 'Scan failed. Check error log.', 'wp-vigie' ),
				],
			]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap wpvigie-wrap">
			<h1><?php esc_html_e( 'WP Vigie — Health Check', 'wp-vigie' ); ?></h1>
			<p><?php esc_html_e( 'Run a 10-point health audit on this WordPress installation.', 'wp-vigie' ); ?></p>

			<button id="wpvigie-run-scan" class="button button-primary" type="button">
				<?php esc_html_e( 'Run Scan', 'wp-vigie' ); ?>
			</button>

			<div id="wpvigie-results"></div>

			<p class="wpvigie-footer">
				<?php
				printf(
					wp_kses(
						/* translators: 1: ODACOM link, 2: WPSentinel Cloud link */
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
			</p>
		</div>
		<?php
	}

	public function handle_ajax_scan(): void {
		check_ajax_referer( 'wpvigie_scan', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'wp-vigie' ) ], 403 );
		}

		$checker = new WPVigie_Checker();
		$results = $checker->run_all_checks();
		$score   = WPVigie_Scorer::score( $results );

		wp_send_json_success(
			[
				'score'   => $score,
				'results' => $results,
			]
		);
	}
}
