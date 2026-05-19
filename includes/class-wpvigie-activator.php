<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Activator {

	/**
	 * Creates or upgrades the scans table when the stored DB version doesn't match
	 * WPVIGIE_DB_VERSION. Safe to call on every request — the version gate prevents
	 * redundant work, and dbDelta() is idempotent.
	 */
	public static function maybe_create_table(): void {
		if ( get_option( 'wpvigie_db_version' ) === WPVIGIE_DB_VERSION ) {
			return;
		}

		global $wpdb;

		$table_name      = $wpdb->prefix . 'wpvigie_scans';
		$charset_collate = $wpdb->get_charset_collate();

		// `trigger` is a reserved word in MySQL 8 / MariaDB 10.5+ — must be backtick-quoted.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scanned_at DATETIME NOT NULL,
			score TINYINT UNSIGNED NOT NULL,
			results LONGTEXT NOT NULL,
			`trigger` VARCHAR(20) NOT NULL DEFAULT 'manual',
			PRIMARY KEY (id),
			KEY scanned_at (scanned_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wpvigie_db_version', WPVIGIE_DB_VERSION );
	}

	public static function activate(): void {
		self::maybe_create_table();

		if ( false === get_option( 'wpvigie_settings' ) ) {
			add_option(
				'wpvigie_settings',
				[
					'daily_scan_enabled'      => false,
					'scan_time_hour'          => 3,
					'history_retention_days'  => 30,
				]
			);
		}

		if ( false === get_option( 'wpvigie_welcome_dismissed' ) ) {
			add_option( 'wpvigie_welcome_dismissed', 0 );
		}
	}

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'wpvigie_daily_scan' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpvigie_daily_scan' );
		}
	}

	public static function uninstall(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpvigie_scans';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		delete_option( 'wpvigie_settings' );
		delete_option( 'wpvigie_welcome_dismissed' );
		delete_option( 'wpvigie_db_version' );
	}
}
