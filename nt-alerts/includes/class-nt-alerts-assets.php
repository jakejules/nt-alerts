<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Assets {

	const HANDLE_JS  = 'nt-alerts-embed';
	const HANDLE_CSS = 'nt-alerts-embed';

	public static function register_hooks() {
		// Register (but do not auto-enqueue) so pages that opt in can call
		// wp_enqueue_script( NT_Alerts_Assets::HANDLE_JS ). Third-party sites
		// include the file by URL instead.
		add_action( 'wp_enqueue_scripts',    array( __CLASS__, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		$version = self::pinned_version();

		wp_register_script(
			self::HANDLE_JS,
			self::embed_js_src(),
			array(),
			$version,
			true
		);
		wp_register_style(
			self::HANDLE_CSS,
			self::embed_css_src(),
			array(),
			$version
		);
	}

	public static function embed_js_src() {
		return NT_ALERTS_PLUGIN_URL . 'public/embed.js';
	}

	public static function embed_css_src() {
		return NT_ALERTS_PLUGIN_URL . 'public/embed.css';
	}

	public static function pinned_version() {
		$pinned = (string) get_option( 'nt_alerts_embed_version', NT_ALERTS_VERSION );
		return '' !== $pinned ? $pinned : NT_ALERTS_VERSION;
	}

	/**
	 * Returns the third-party embed snippet for documentation / settings screens.
	 */
	public static function embed_snippet() {
		$version = self::pinned_version();
		$js  = add_query_arg( 'ver', $version, self::embed_js_src() );
		$css = add_query_arg( 'ver', $version, self::embed_css_src() );

		return sprintf(
			"<link rel=\"stylesheet\" href=\"%s\">\n" .
			"<div id=\"nt-alerts\"></div>\n" .
			"<script src=\"%s\"></script>",
			esc_url( $css ),
			esc_url( $js )
		);
	}
}
