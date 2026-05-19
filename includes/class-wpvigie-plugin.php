<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Plugin {

	private static ?self $instance = null;

	private WPVigie_Admin_Page       $admin_page;
	private WPVigie_History          $history;
	private WPVigie_Scheduler        $scheduler;
	private WPVigie_Settings         $settings;
	private WPVigie_Welcome          $welcome;
	private WPVigie_Dashboard_Widget $widget;

	private function __construct() {
		add_action(
			'plugins_loaded',
			function () {
				load_plugin_textdomain(
					'wp-vigie',
					false,
					dirname( plugin_basename( WPVIGIE_PATH . 'wp-vigie.php' ) ) . '/languages'
				);

				// Self-healing: create/upgrade the table whenever the stored DB schema
				// version doesn't match WPVIGIE_DB_VERSION. Covers cases where the
				// activation hook never fired (Docker reset, manual file drop, etc.).
				// maybe_create_table() is a no-op when the version already matches.
				WPVigie_Activator::maybe_create_table();
			}
		);

		$this->register_hooks();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register_hooks(): void {
		$this->history    = new WPVigie_History();
		$this->scheduler  = new WPVigie_Scheduler();
		$this->settings   = new WPVigie_Settings( $this->scheduler );
		$this->welcome    = new WPVigie_Welcome( $this->scheduler );
		$this->widget     = new WPVigie_Dashboard_Widget( $this->history );
		$this->admin_page = new WPVigie_Admin_Page( $this->history );

		add_action( 'admin_menu',             [ $this->admin_page, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts',  [ $this->admin_page, 'enqueue_assets' ] );
		add_action( 'admin_enqueue_scripts',  [ $this->widget,     'enqueue_assets' ] );
		add_action( 'admin_notices',          [ $this->welcome,    'maybe_show_notice' ] );
		add_action( 'wp_dashboard_setup',     [ $this->widget,     'register' ] );

		add_action( 'wp_ajax_wpvigie_run_scan',                  [ $this->admin_page, 'handle_ajax_scan' ] );
		add_action( 'wp_ajax_wpvigie_get_history',               [ $this->history,    'handle_ajax_get' ] );
		add_action( 'wp_ajax_wpvigie_get_scan',                  [ $this->history,    'handle_ajax_get_one' ] );
		add_action( 'wp_ajax_wpvigie_set_schedule_preference',   [ $this->welcome,    'handle_ajax_choice' ] );
		add_action( 'wp_ajax_wpvigie_save_settings',             [ $this->settings,   'handle_ajax_save' ] );

		add_action( 'wpvigie_daily_scan', [ $this->scheduler, 'run_daily_scan' ] );
	}
}
