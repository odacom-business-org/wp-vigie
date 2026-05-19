<?php
/**
 * Plugin Name:       WP Vigie
 * Plugin URI:        https://github.com/odacom/wp-vigie
 * Description:       10-point health audit for WordPress. Open-source companion to WPSentinel Cloud.
 * Version:           0.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            ODACOM
 * Author URI:        https://odacom.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-vigie
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPVIGIE_VERSION', '0.2.0' );
define( 'WPVIGIE_DB_VERSION', '1' );
define( 'WPVIGIE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPVIGIE_URL', plugin_dir_url( __FILE__ ) );
define( 'WPVIGIE_FILE', __FILE__ );

require_once WPVIGIE_PATH . 'includes/class-wpvigie-activator.php';
require_once WPVIGIE_PATH . 'includes/class-wpvigie-history.php';
require_once WPVIGIE_PATH . 'includes/class-wpvigie-scheduler.php';
require_once WPVIGIE_PATH . 'includes/class-wpvigie-settings.php';
require_once WPVIGIE_PATH . 'includes/class-wpvigie-welcome.php';
require_once WPVIGIE_PATH . 'includes/class-wpvigie-dashboard-widget.php';
require_once WPVIGIE_PATH . 'includes/class-wpvigie-plugin.php';
require_once WPVIGIE_PATH . 'includes/class-wpvigie-checker.php';
require_once WPVIGIE_PATH . 'includes/class-wpvigie-scorer.php';
require_once WPVIGIE_PATH . 'includes/class-wpvigie-admin-page.php';

register_activation_hook( WPVIGIE_FILE, [ 'WPVigie_Activator', 'activate' ] );
register_deactivation_hook( WPVIGIE_FILE, [ 'WPVigie_Activator', 'deactivate' ] );

WPVigie_Plugin::instance();
