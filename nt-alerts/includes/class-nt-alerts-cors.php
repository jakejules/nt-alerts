<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_CORS {

	public static function register_hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'swap_default_filter' ), 15 );
	}

	public static function swap_default_filter() {
		// WordPress's built-in handler emits "Access-Control-Allow-Origin: *" for
		// the REST API. That violates the spec's allowlist rule, so replace it
		// with our scoped filter that only handles /nt-alerts/v1/* routes and
		// leaves every other namespace alone.
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'handle' ), 10, 4 );
	}

	public static function handle( $served, $result, $request, $server ) {
		unset( $server );

		$route = $request->get_route();
		$is_ours = ( 0 === strpos( $route, '/' . NT_ALERTS_REST_NAMESPACE . '/' ) );

		if ( ! $is_ours ) {
			// Non-nt-alerts routes: restore WordPress's default behaviour for them.
			if ( function_exists( 'rest_send_cors_headers' ) ) {
				rest_send_cors_headers( null );
			}
			return $served;
		}

		$origin = get_http_origin();
		$method = strtoupper( (string) $request->get_method() );

		$is_public_get = ( 'GET' === $method )
			&& ( 0 === strpos( $route, '/' . NT_ALERTS_REST_NAMESPACE . '/alerts/active' )
				|| 0 === strpos( $route, '/' . NT_ALERTS_REST_NAMESPACE . '/alerts/route/' )
				|| 0 === strpos( $route, '/' . NT_ALERTS_REST_NAMESPACE . '/stops' ) );

		$allowed = self::origin_is_allowed( $origin, $is_public_get );

		if ( $origin && $allowed ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
			header( 'Vary: Origin', false );
			if ( ! $is_public_get ) {
				header( 'Access-Control-Allow-Credentials: true' );
			}
		}

		$allow_methods = $is_public_get ? 'GET, OPTIONS' : 'GET, POST, PATCH, OPTIONS';
		header( 'Access-Control-Allow-Methods: ' . $allow_methods );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
		header( 'Access-Control-Max-Age: 600' );

		if ( 'OPTIONS' === $method ) {
			status_header( 200 );
			return true;
		}

		return $served;
	}

	private static function origin_is_allowed( $origin, $is_public_get ) {
		if ( ! $origin ) {
			return false;
		}

		$allowlist = self::full_allowlist();

		if ( in_array( $origin, $allowlist, true ) ) {
			return true;
		}

		if ( $is_public_get && self::is_site_origin( $origin ) ) {
			return true;
		}

		return false;
	}

	private static function full_allowlist() {
		$stored = get_option( 'nt_alerts_cors_origins', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$dev = array();
		if ( defined( 'NT_ALERTS_DEV_CORS_ORIGINS' ) && is_array( NT_ALERTS_DEV_CORS_ORIGINS ) ) {
			$dev = NT_ALERTS_DEV_CORS_ORIGINS;
		}

		$merged = array();
		foreach ( array_merge( $stored, $dev ) as $origin ) {
			$origin = is_string( $origin ) ? trim( $origin ) : '';
			if ( '' !== $origin ) {
				$merged[] = $origin;
			}
		}
		return array_values( array_unique( $merged ) );
	}

	private static function is_site_origin( $origin ) {
		$home  = wp_parse_url( home_url() );
		$check = wp_parse_url( $origin );

		if ( empty( $home['host'] ) || empty( $check['host'] ) ) {
			return false;
		}

		$home_scheme  = isset( $home['scheme'] )  ? $home['scheme']  : 'https';
		$check_scheme = isset( $check['scheme'] ) ? $check['scheme'] : '';
		$home_port    = isset( $home['port'] )    ? (int) $home['port']  : 0;
		$check_port   = isset( $check['port'] )   ? (int) $check['port'] : 0;

		return $home_scheme === $check_scheme
			&& strtolower( $home['host'] ) === strtolower( $check['host'] )
			&& $home_port === $check_port;
	}
}
