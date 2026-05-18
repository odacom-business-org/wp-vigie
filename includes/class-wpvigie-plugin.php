<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPVigie_Plugin {

	private static ?self $instance = null;

	private WPVigie_Admin_Page $admin_page;

	private function __construct() {
		add_action(
			'plugins_loaded',
			function () {
				load_plugin_textdomain(
					'wp-vigie',
					false,
					dirname( plugin_basename( WPVIGIE_PATH . 'wp-vigie.php' ) ) . '/languages'
				);
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
		$this->admin_page = new WPVigie_Admin_Page();

		add_action( 'admin_menu', [ $this->admin_page, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this->admin_page, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wpvigie_run_scan', [ $this->admin_page, 'handle_ajax_scan' ] );
	}
}
