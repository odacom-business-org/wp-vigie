<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Settings {

	private WPVigie_Scheduler $scheduler;

	public function __construct( WPVigie_Scheduler $scheduler ) {
		$this->scheduler = $scheduler;
	}

	public static function defaults(): array {
		return [
			'daily_scan_enabled'     => false,
			'scan_time_hour'         => 3,
			'history_retention_days' => 30,
		];
	}

	public static function get( string $key, $default = null ) {
		$settings = get_option( 'wpvigie_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];
		$settings = wp_parse_args( $settings, self::defaults() );

		return $settings[ $key ] ?? $default;
	}

	public static function set( string $key, $value ): bool {
		$settings = get_option( 'wpvigie_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];
		$settings = wp_parse_args( $settings, self::defaults() );

		$settings[ $key ] = $value;

		return (bool) update_option( 'wpvigie_settings', $settings );
	}

	public static function get_all(): array {
		$settings = get_option( 'wpvigie_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		return wp_parse_args( $settings, self::defaults() );
	}

	public function handle_ajax_save(): void {
		check_ajax_referer( 'wpvigie_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$current = self::get_all();

		// Sanitize and validate each field.
		$daily_scan_enabled = isset( $_POST['daily_scan_enabled'] ) && '1' === $_POST['daily_scan_enabled'];

		$scan_time_hour = isset( $_POST['scan_time_hour'] )
			? absint( $_POST['scan_time_hour'] )
			: $current['scan_time_hour'];
		if ( $scan_time_hour > 23 ) {
			$scan_time_hour = 3;
		}

		$history_retention_days = isset( $_POST['history_retention_days'] )
			? absint( $_POST['history_retention_days'] )
			: $current['history_retention_days'];
		if ( $history_retention_days < 1 ) {
			$history_retention_days = 30;
		}

		$new = [
			'daily_scan_enabled'     => $daily_scan_enabled,
			'scan_time_hour'         => $scan_time_hour,
			'history_retention_days' => $history_retention_days,
		];

		update_option( 'wpvigie_settings', $new );

		// Cron side-effects — only act on what actually changed.
		$enabled_changed = $current['daily_scan_enabled'] !== $new['daily_scan_enabled'];
		$hour_changed    = $current['scan_time_hour']     !== $new['scan_time_hour'];

		if ( $enabled_changed ) {
			if ( $new['daily_scan_enabled'] ) {
				$this->scheduler->schedule_daily_scan( $new['scan_time_hour'] );
			} else {
				$this->scheduler->unschedule_daily_scan();
			}
		} elseif ( $hour_changed && $new['daily_scan_enabled'] ) {
			$this->scheduler->reschedule_daily_scan( $new['scan_time_hour'] );
		}

		wp_send_json_success( [ 'message' => __( 'Settings saved.', 'wp-vigie' ) ] );
	}
}
