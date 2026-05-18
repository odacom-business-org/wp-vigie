<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Checker {

	public function run_all_checks(): array {
		return [
			$this->check_wp_core_version(),
			$this->check_php_version(),
			$this->check_db_version(),
			$this->check_plugin_updates(),
			$this->check_theme_updates(),
			$this->check_https_enabled(),
			$this->check_wp_debug(),
			$this->check_admin_user(),
			$this->check_file_edit_allowed(),
			$this->check_wp_cron(),
		];
	}

	// -------------------------------------------------------------------------
	// Check 1 — WordPress Core Version
	// -------------------------------------------------------------------------

	private function check_wp_core_version(): array {
		$id    = 'wp_core_version';
		$title = __( 'WordPress Core Version', 'wp-vigie' );
		$rec   = __( 'Update WordPress core via Dashboard → Updates.', 'wp-vigie' );

		$current = get_bloginfo( 'version' );

		// Read the latest available version from WP's own update transient.
		// We do not call wp_update_core() here to avoid external HTTP calls.
		$update_data = get_site_transient( 'update_core' );
		$latest      = null;

		if ( isset( $update_data->updates ) && is_array( $update_data->updates ) ) {
			foreach ( $update_data->updates as $update ) {
				if ( isset( $update->current ) ) {
					$latest = $update->current;
					break;
				}
			}
		}

		if ( null === $latest ) {
			return [
				'id'             => $id,
				'title'          => $title,
				'severity'       => 'fail',
				'value'          => sprintf( 'Running WP %s (latest: unknown)', $current ),
				'recommendation' => $rec,
			];
		}

		if ( version_compare( $current, $latest, '>=' ) ) {
			return [
				'id'             => $id,
				'title'          => $title,
				'severity'       => 'pass',
				'value'          => sprintf( 'Running WP %s (latest: %s)', $current, $latest ),
				'recommendation' => '',
			];
		}

		// Determine whether the gap is a major (minor segment changed) or patch difference.
		$cur_parts    = explode( '.', $current );
		$latest_parts = explode( '.', $latest );
		$same_major   = ( $cur_parts[0] ?? '0' ) === ( $latest_parts[0] ?? '0' );
		$same_minor   = ( $cur_parts[1] ?? '0' ) === ( $latest_parts[1] ?? '0' );

		$severity = ( $same_major && $same_minor ) ? 'warn' : 'fail';

		return [
			'id'             => $id,
			'title'          => $title,
			'severity'       => $severity,
			'value'          => sprintf( 'Running WP %s (latest: %s)', $current, $latest ),
			'recommendation' => $rec,
		];
	}

	// -------------------------------------------------------------------------
	// Check 2 — PHP Version
	// -------------------------------------------------------------------------

	private function check_php_version(): array {
		$version = PHP_VERSION;
		$rec     = __( 'Ask your host to upgrade to PHP 8.1+. Older versions are unsupported and unsafe.', 'wp-vigie' );

		if ( version_compare( $version, '8.1', '>=' ) ) {
			$severity = 'pass';
			$rec      = '';
		} elseif ( version_compare( $version, '7.4', '>=' ) ) {
			$severity = 'warn';
		} else {
			$severity = 'fail';
		}

		return [
			'id'             => 'php_version',
			'title'          => __( 'PHP Version', 'wp-vigie' ),
			'severity'       => $severity,
			'value'          => 'PHP ' . $version,
			'recommendation' => $rec,
		];
	}

	// -------------------------------------------------------------------------
	// Check 3 — Database Server Version
	// -------------------------------------------------------------------------

	private function check_db_version(): array {
		global $wpdb;

		$id  = 'db_version';
		$rec = __( 'Database is below recommended version. Contact your host.', 'wp-vigie' );

		$raw = $wpdb->get_var( 'SELECT VERSION()' );

		if ( null === $raw ) {
			return [
				'id'             => $id,
				'title'          => __( 'Database Version', 'wp-vigie' ),
				'severity'       => 'fail',
				'value'          => __( 'Could not determine database version', 'wp-vigie' ),
				'recommendation' => $rec,
			];
		}

		$is_mariadb = false !== stripos( $raw, 'mariadb' );

		// Strip the MySQL-compatibility prefix (5.5.5-) that some MariaDB builds emit.
		$clean = preg_replace( '/^5\.5\.5-/', '', $raw );

		preg_match( '/^([\d.]+)/', (string) $clean, $matches );
		$numeric = $matches[1] ?? '0';

		if ( $is_mariadb ) {
			$display = 'MariaDB ' . $numeric;

			if ( version_compare( $numeric, '10.5', '>=' ) ) {
				$severity = 'pass';
				$rec      = '';
			} elseif ( version_compare( $numeric, '10.3', '>=' ) ) {
				$severity = 'warn';
			} else {
				$severity = 'fail';
			}
		} else {
			$display = 'MySQL ' . $numeric;

			if ( version_compare( $numeric, '8.0', '>=' ) ) {
				$severity = 'pass';
				$rec      = '';
			} elseif ( version_compare( $numeric, '5.7', '>=' ) ) {
				$severity = 'warn';
			} else {
				$severity = 'fail';
			}
		}

		return [
			'id'             => $id,
			'title'          => __( 'Database Version', 'wp-vigie' ),
			'severity'       => $severity,
			'value'          => $display,
			'recommendation' => $rec,
		];
	}

	// -------------------------------------------------------------------------
	// Check 4 — Plugin Updates Available
	// -------------------------------------------------------------------------

	private function check_plugin_updates(): array {
		$id  = 'plugin_updates';
		$rec = __( 'Review and apply plugin updates from Dashboard → Updates.', 'wp-vigie' );

		$transient = get_site_transient( 'update_plugins' );
		$count     = ( isset( $transient->response ) && is_array( $transient->response ) )
			? count( $transient->response )
			: 0;

		if ( 0 === $count ) {
			$severity = 'pass';
			$value    = __( '0 plugin updates available', 'wp-vigie' );
			$rec      = '';
		} elseif ( $count <= 3 ) {
			$severity = 'warn';
			$value    = sprintf(
				/* translators: %d: number of plugin updates */
				_n( '%d plugin update available', '%d plugin updates available', $count, 'wp-vigie' ),
				$count
			);
		} else {
			$severity = 'fail';
			$value    = sprintf(
				/* translators: %d: number of plugin updates */
				_n( '%d plugin update available', '%d plugin updates available', $count, 'wp-vigie' ),
				$count
			);
		}

		return [
			'id'             => $id,
			'title'          => __( 'Plugin Updates', 'wp-vigie' ),
			'severity'       => $severity,
			'value'          => $value,
			'recommendation' => $rec,
		];
	}

	// -------------------------------------------------------------------------
	// Check 5 — Theme Updates Available
	// -------------------------------------------------------------------------

	private function check_theme_updates(): array {
		$id  = 'theme_updates';
		$rec = __( 'Review and apply theme updates from Dashboard → Updates.', 'wp-vigie' );

		$transient = get_site_transient( 'update_themes' );
		$count     = ( isset( $transient->response ) && is_array( $transient->response ) )
			? count( $transient->response )
			: 0;

		if ( 0 === $count ) {
			$severity = 'pass';
			$value    = __( '0 theme updates available', 'wp-vigie' );
			$rec      = '';
		} elseif ( $count <= 3 ) {
			$severity = 'warn';
			$value    = sprintf(
				/* translators: %d: number of theme updates */
				_n( '%d theme update available', '%d theme updates available', $count, 'wp-vigie' ),
				$count
			);
		} else {
			$severity = 'fail';
			$value    = sprintf(
				/* translators: %d: number of theme updates */
				_n( '%d theme update available', '%d theme updates available', $count, 'wp-vigie' ),
				$count
			);
		}

		return [
			'id'             => $id,
			'title'          => __( 'Theme Updates', 'wp-vigie' ),
			'severity'       => $severity,
			'value'          => $value,
			'recommendation' => $rec,
		];
	}

	// -------------------------------------------------------------------------
	// Check 6 — HTTPS Enabled
	// -------------------------------------------------------------------------

	private function check_https_enabled(): array {
		$enabled = is_ssl() && str_starts_with( home_url(), 'https://' );

		return [
			'id'             => 'https_enabled',
			'title'          => __( 'HTTPS', 'wp-vigie' ),
			'severity'       => $enabled ? 'pass' : 'fail',
			'value'          => $enabled
				? __( 'HTTPS enabled', 'wp-vigie' )
				: __( 'HTTPS missing', 'wp-vigie' ),
			'recommendation' => $enabled
				? ''
				: __( 'Install an SSL certificate and update Site URL/Home URL to https://.', 'wp-vigie' ),
		];
	}

	// -------------------------------------------------------------------------
	// Check 7 — WP_DEBUG Status
	// -------------------------------------------------------------------------

	private function check_wp_debug(): array {
		$debug_on = defined( 'WP_DEBUG' ) && true === WP_DEBUG;

		return [
			'id'             => 'wp_debug',
			'title'          => __( 'Debug Mode', 'wp-vigie' ),
			'severity'       => $debug_on ? 'warn' : 'pass',
			'value'          => $debug_on
				? __( 'WP_DEBUG: enabled', 'wp-vigie' )
				: __( 'WP_DEBUG: disabled', 'wp-vigie' ),
			'recommendation' => $debug_on
				? __( "Disable WP_DEBUG in production. Set define('WP_DEBUG', false); in wp-config.php.", 'wp-vigie' )
				: '',
		];
	}

	// -------------------------------------------------------------------------
	// Check 8 — Default "admin" User
	// -------------------------------------------------------------------------

	private function check_admin_user(): array {
		$exists = false !== get_user_by( 'login', 'admin' );

		return [
			'id'             => 'admin_user',
			'title'          => __( 'Default Admin Username', 'wp-vigie' ),
			'severity'       => $exists ? 'fail' : 'pass',
			'value'          => $exists
				? __( "'admin' user found", 'wp-vigie' )
				: __( 'No default admin user', 'wp-vigie' ),
			'recommendation' => $exists
				? __( "Delete or rename the 'admin' account. It is the #1 brute-force target.", 'wp-vigie' )
				: '',
		];
	}

	// -------------------------------------------------------------------------
	// Check 9 — Dashboard File Editing
	// -------------------------------------------------------------------------

	private function check_file_edit_allowed(): array {
		$disabled = defined( 'DISALLOW_FILE_EDIT' ) && true === DISALLOW_FILE_EDIT;

		return [
			'id'             => 'file_edit_allowed',
			'title'          => __( 'Dashboard File Editor', 'wp-vigie' ),
			'severity'       => $disabled ? 'pass' : 'warn',
			'value'          => $disabled
				? __( 'File editing disabled', 'wp-vigie' )
				: __( 'File editing allowed', 'wp-vigie' ),
			'recommendation' => $disabled
				? ''
				: __( "Add define('DISALLOW_FILE_EDIT', true); to wp-config.php to block code execution from the dashboard.", 'wp-vigie' ),
		];
	}

	// -------------------------------------------------------------------------
	// Check 10 — WP-Cron Configuration
	// -------------------------------------------------------------------------

	private function check_wp_cron(): array {
		$disabled = defined( 'DISABLE_WP_CRON' ) && true === DISABLE_WP_CRON;

		return [
			'id'             => 'wp_cron',
			'title'          => __( 'WP-Cron', 'wp-vigie' ),
			'severity'       => $disabled ? 'pass' : 'warn',
			'value'          => $disabled
				? __( 'WP pseudo-cron disabled (use system cron)', 'wp-vigie' )
				: __( 'WP pseudo-cron active', 'wp-vigie' ),
			'recommendation' => $disabled
				? ''
				: __( "For sites with real traffic, set define('DISABLE_WP_CRON', true); and add a system cron job: */5 * * * * wget -q -O - https://yoursite.com/wp-cron.php.", 'wp-vigie' ),
		];
	}
}
