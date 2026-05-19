<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Scheduler {

	private const HOOK = 'wpvigie_daily_scan';

	/**
	 * Schedule the daily scan event at the next occurrence of $hour:00 local time.
	 * No-ops if already scheduled.
	 */
	public function schedule_daily_scan( int $hour = 3 ): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		$next = mktime( $hour, 0, 0 );
		if ( $next <= time() ) {
			$next = strtotime( '+1 day', $next );
		}

		wp_schedule_event( $next, 'daily', self::HOOK );
	}

	/**
	 * Remove all scheduled instances of the daily scan hook.
	 */
	public function unschedule_daily_scan(): void {
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * Unschedule then re-schedule at a new hour. Used when scan_time_hour changes.
	 */
	public function reschedule_daily_scan( int $hour ): void {
		$this->unschedule_daily_scan();
		$this->schedule_daily_scan( $hour );
	}

	/**
	 * Cron callback. Aborts if daily scans are disabled in settings.
	 */
	public function run_daily_scan(): void {
		if ( ! WPVigie_Settings::get( 'daily_scan_enabled', false ) ) {
			return;
		}

		$checker = new WPVigie_Checker();
		$results = $checker->run_all_checks();
		$score   = WPVigie_Scorer::score( $results );

		$history = new WPVigie_History();
		$history->save( $score, $results, 'scheduled' );
	}
}
