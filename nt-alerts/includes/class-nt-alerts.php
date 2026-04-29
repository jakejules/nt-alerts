<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'admin_init', array( 'NT_Alerts_Activator', 'maybe_upgrade' ) );

		NT_Alerts_Roles::register_hooks();
		NT_Alerts_CPT::register_hooks();

		NT_Alerts_Cache::register_hooks();
		NT_Alerts_CORS::register_hooks();
		NT_Alerts_REST::register_hooks();
		NT_Alerts_Assets::register_hooks();

		NT_Alerts_Admin::register_global_hooks();
		if ( is_admin() || wp_doing_ajax() ) {
			NT_Alerts_Admin::register_hooks();
			NT_Alerts_Settings::register_hooks();
			NT_Alerts_Archive::register_hooks();
		}

		NT_Alerts_Cron::register_hooks();
		NT_Alerts_Notifications::register_hooks();
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'nt-alerts',
			false,
			dirname( plugin_basename( NT_ALERTS_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
