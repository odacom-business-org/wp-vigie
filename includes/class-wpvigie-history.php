<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_History {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wpvigie_scans';
	}

	/**
	 * Persist a scan result. Returns the inserted row ID.
	 */
	public function save( int $score, array $results, string $trigger = 'manual' ): int {
		global $wpdb;

		// Use an explicit INSERT rather than $wpdb->insert() so we can backtick-quote
		// `trigger`, which is a reserved word in MySQL 8 / MariaDB 10.5+.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"INSERT INTO {$this->table} (scanned_at, score, results, `trigger`) VALUES (%s, %d, %s, %s)",
				current_time( 'mysql', true ),
				$score,
				wp_json_encode( $results ),
				$trigger
			)
		);

		$id = (int) $wpdb->insert_id;

		if ( $id > 0 ) {
			$this->prune();
		}

		return $id;
	}

	/**
	 * Returns the $limit most recent scan rows (without decoded results — for table display).
	 */
	public function get_recent( int $limit = 30 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, scanned_at, score, `trigger` FROM {$this->table} ORDER BY scanned_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Returns a single scan row with results JSON-decoded, or null if not found.
	 */
	public function get_by_id( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$row['results'] = json_decode( $row['results'], true );

		return $row;
	}

	/**
	 * Returns [{date, score}, ...] for the last $days days, oldest first. One point per scan.
	 */
	public function get_chart_data( int $days = 30 ): array {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT scanned_at, score FROM {$this->table} WHERE scanned_at >= %s ORDER BY scanned_at ASC",
				$since
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map(
			static function ( array $row ): array {
				return [
					'date'  => $row['scanned_at'],
					'score' => (int) $row['score'],
				];
			},
			$rows
		);
	}

	/**
	 * Returns the last $count scores as ints, oldest first — for the dashboard widget sparkline.
	 */
	public function get_sparkline_data( int $count = 14 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT score FROM {$this->table} ORDER BY scanned_at DESC LIMIT %d",
				$count
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$scores = array_map( static fn( array $row ): int => (int) $row['score'], $rows );

		return array_reverse( $scores );
	}

	/**
	 * Deletes rows older than the history_retention_days setting.
	 */
	private function prune(): void {
		global $wpdb;

		$settings = get_option( 'wpvigie_settings', [] );
		$days     = isset( $settings['history_retention_days'] )
			? absint( $settings['history_retention_days'] )
			: 30;

		if ( $days <= 0 ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$this->table} WHERE scanned_at < %s",
				$cutoff
			)
		);
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function handle_ajax_get(): void {
		check_ajax_referer( 'wpvigie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		wp_send_json_success(
			[
				'scans'      => $this->get_recent( 30 ),
				'chart_data' => $this->get_chart_data( 30 ),
			]
		);
	}

	public function handle_ajax_get_one(): void {
		check_ajax_referer( 'wpvigie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$id = isset( $_POST['scan_id'] ) ? absint( $_POST['scan_id'] ) : 0;

		if ( $id <= 0 ) {
			wp_send_json_error( null, 400 );
		}

		$scan = $this->get_by_id( $id );

		if ( null === $scan ) {
			wp_send_json_error( null, 404 );
		}

		wp_send_json_success(
			[
				'score'      => (int) $scan['score'],
				'results'    => $scan['results'],
				'scanned_at' => $scan['scanned_at'],
			]
		);
	}
}
