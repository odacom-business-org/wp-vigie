<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Welcome {

	private WPVigie_Scheduler $scheduler;

	public function __construct( WPVigie_Scheduler $scheduler ) {
		$this->scheduler = $scheduler;
	}

	/**
	 * Hooked to admin_notices. Shows the activation welcome notice once per site.
	 */
	public function maybe_show_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( 1 === (int) get_option( 'wpvigie_welcome_dismissed', 0 ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'wpvigie_admin' );
		?>
		<div class="notice notice-info wpvigie-welcome-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<strong><?php esc_html_e( 'Welcome to WP Vigie', 'wp-vigie' ); ?></strong><br>
				<?php esc_html_e( 'Want WP Vigie to scan your site automatically once a day and keep a history of scores?', 'wp-vigie' ); ?>
			</p>
			<p>
				<button class="button button-primary" type="button" data-choice="enable">
					<?php esc_html_e( 'Enable daily scans', 'wp-vigie' ); ?>
				</button>
				<button class="button" type="button" data-choice="skip">
					<?php esc_html_e( 'Not now', 'wp-vigie' ); ?>
				</button>
			</p>
		</div>
		<?php
		// Register the inline handler script only when the notice will actually render.
		add_action( 'admin_footer', [ $this, 'render_notice_script' ] );
	}

	/**
	 * Outputs an inline script to handle the notice button clicks via fetch().
	 * Attached to admin_footer only when the notice is shown.
	 */
	public function render_notice_script(): void {
		?>
		<script>
		( function () {
			var notice = document.querySelector( '.wpvigie-welcome-notice' );
			if ( ! notice ) return;

			notice.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '[data-choice]' );
				if ( ! btn ) return;

				e.preventDefault();
				btn.disabled = true;

				var body = new FormData();
				body.append( 'action', 'wpvigie_set_schedule_preference' );
				body.append( 'nonce',  notice.dataset.nonce );
				body.append( 'choice', btn.dataset.choice );

				fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
					method: 'POST',
					body:   body,
					credentials: 'same-origin'
				} )
				.then( function () { notice.remove(); } )
				.catch( function () { btn.disabled = false; } );
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * AJAX handler for wpvigie_set_schedule_preference.
	 */
	public function handle_ajax_choice(): void {
		check_ajax_referer( 'wpvigie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$choice = isset( $_POST['choice'] )
			? sanitize_text_field( wp_unslash( $_POST['choice'] ) )
			: '';

		if ( 'enable' === $choice ) {
			WPVigie_Settings::set( 'daily_scan_enabled', true );
			$hour = (int) WPVigie_Settings::get( 'scan_time_hour', 3 );
			$this->scheduler->schedule_daily_scan( $hour );
		}

		update_option( 'wpvigie_welcome_dismissed', 1 );

		wp_send_json_success( [ 'success' => true ] );
	}
}
