<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Dashboard_Widget {

	private WPVigie_History $history;

	private const WIDGET_ID = 'wpvigie_site_health';

	// Small ring geometry: r=24, so circumference = 2π×24 ≈ 150.796
	private const RING_CIRCUMFERENCE = 150.796;

	public function __construct( WPVigie_History $history ) {
		$this->history = $history;
	}

	public function register(): void {
		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'WP Vigie — Site Health', 'wp-vigie' ),
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		$latest = $this->history->get_recent( 1 );

		if ( empty( $latest ) ) {
			$this->render_empty_state();
			return;
		}

		$scan     = $latest[0];
		$score    = (int) $scan['score'];
		$scan_ts  = strtotime( $scan['scanned_at'] . ' UTC' );
		$time_ago = human_time_diff( $scan_ts, time() );
		$color    = $this->score_color( $score );
		$offset   = self::RING_CIRCUMFERENCE * ( 1 - $score / 100 );
		$sparkline_data = $this->history->get_sparkline_data( 14 );

		$page_url = esc_url( admin_url( 'tools.php?page=wp-vigie' ) );
		?>
		<div class="wpvw-wrap">

			<div class="wpvw-score-row">

				<?php /* Compact score ring */ ?>
				<div class="wpvw-ring-wrap">
					<svg class="wpvw-ring" viewBox="0 0 60 60" aria-hidden="true">
						<circle cx="30" cy="30" r="24"
						        fill="none" stroke="#e2e8f0" stroke-width="4" />
						<circle class="wpvw-ring__progress"
						        cx="30" cy="30" r="24"
						        fill="none"
						        stroke="<?php echo esc_attr( $color ); ?>"
						        stroke-width="4"
						        stroke-linecap="round"
						        stroke-dasharray="<?php echo esc_attr( number_format( self::RING_CIRCUMFERENCE, 3 ) ); ?>"
						        stroke-dashoffset="<?php echo esc_attr( number_format( self::RING_CIRCUMFERENCE, 3 ) ); ?>"
						        data-offset="<?php echo esc_attr( number_format( $offset, 3 ) ); ?>"
						        transform="rotate(-90 30 30)" />
					</svg>
					<span class="wpvw-score-num" style="color:<?php echo esc_attr( $color ); ?>">
						<?php echo esc_html( $score ); ?>
					</span>
				</div>

				<?php /* Timestamp + sparkline */ ?>
				<div class="wpvw-meta">
					<div class="wpvw-time">
						<?php
						printf(
							/* translators: %s: human-readable time difference */
							esc_html__( 'Last scan: %s ago', 'wp-vigie' ),
							esc_html( $time_ago )
						);
						?>
					</div>
					<?php $this->render_sparkline( $sparkline_data ); ?>
				</div>

			</div><?php /* .wpvw-score-row */ ?>

			<div class="wpvw-actions">
				<a href="<?php echo $page_url; ?>" class="button button-primary button-small">
					<?php esc_html_e( 'Run new scan', 'wp-vigie' ); ?>
				</a>
				<a href="<?php echo $page_url; ?>" class="wpvw-link">
					<?php esc_html_e( 'View full report', 'wp-vigie' ); ?> &rarr;
				</a>
			</div>

		</div>
		<?php
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'index.php' !== $hook ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpvigie-dashboard-widget',
			WPVIGIE_URL . 'assets/dashboard-widget.css',
			[],
			WPVIGIE_VERSION
		);

		wp_enqueue_script(
			'wpvigie-dashboard-widget',
			WPVIGIE_URL . 'assets/dashboard-widget.js',
			[],
			WPVIGIE_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function render_empty_state(): void {
		$page_url = esc_url( admin_url( 'tools.php?page=wp-vigie' ) );
		?>
		<div class="wpvw-wrap wpvw-empty">
			<p>
				<?php esc_html_e( 'No scans yet. Run your first scan to start tracking your site\'s health.', 'wp-vigie' ); ?>
			</p>
			<a href="<?php echo $page_url; ?>" class="button button-primary button-small">
				<?php esc_html_e( 'Run your first scan', 'wp-vigie' ); ?>
			</a>
		</div>
		<?php
	}

	private function render_sparkline( array $scores ): void {
		$total = 14;
		$bar_w = 8;
		$gap   = 4;
		$max_h = 36;
		$svg_w = $total * ( $bar_w + $gap ) - $gap;

		// Pad with nulls on the left so real bars grow from the right.
		$count  = count( $scores );
		$padded = array_merge( array_fill( 0, max( 0, $total - $count ), null ), $scores );

		$bars = '';
		foreach ( $padded as $i => $val ) {
			$x = $i * ( $bar_w + $gap );
			if ( null === $val ) {
				// Placeholder slot: 4 px tall, grey.
				$bars .= sprintf(
					'<rect x="%d" y="%d" width="%d" height="4" fill="#e2e8f0" rx="2"/>',
					(int) $x,
					(int) ( $max_h - 4 ),
					(int) $bar_w
				);
			} else {
				$h     = max( 4, (int) round( ( $val / 100 ) * $max_h ) );
				$y     = $max_h - $h;
				$color = $this->score_color( $val );
				$bars .= sprintf(
					'<rect x="%d" y="%d" width="%d" height="%d" fill="%s" rx="2"/>',
					(int) $x,
					(int) $y,
					(int) $bar_w,
					(int) $h,
					esc_attr( $color )
				);
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values escaped above
		echo '<svg class="wpvw-sparkline" viewBox="0 0 ' . (int) $svg_w . ' ' . (int) $max_h . '" '
			. 'aria-hidden="true">'
			. $bars
			. '</svg>';
	}

	private function score_color( int $score ): string {
		if ( $score >= 80 ) { return '#10b981'; }
		if ( $score >= 50 ) { return '#f59e0b'; }
		return '#ef4444';
	}
}
