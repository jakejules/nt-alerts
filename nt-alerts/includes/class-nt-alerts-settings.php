<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Settings {

	const PAGE_SLUG     = 'nt-alerts-settings';
	const OPTION_GROUP  = 'nt_alerts_settings';

	const OPT_CORS_ORIGINS    = 'nt_alerts_cors_origins';
	const OPT_ROUTES          = 'nt_alerts_routes';
	const OPT_STOPS           = 'nt_alerts_stops';
	const OPT_DEFAULT_DURATIONS = 'nt_alerts_default_durations';
	const OPT_EMBED_VERSION   = 'nt_alerts_embed_version';
	const OPT_CACHE_TTL       = 'nt_alerts_cache_ttl';
	const OPT_ARCHIVE_AFTER   = 'nt_alerts_archive_after_days';
	const OPT_NEW_ALERT_NOTIFY = 'nt_alerts_new_alert_notify';

	public static function register_hooks() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_nt_alerts_reseed_stops', array( __CLASS__, 'handle_reseed_stops' ) );
	}

	public static function handle_reseed_stops() {
		if ( ! current_user_can( 'manage_nt_alerts_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'nt-alerts' ), 403 );
		}
		check_admin_referer( 'nt_alerts_reseed_stops' );

		$stops = NT_Alerts_Activator::default_stops();
		update_option( self::OPT_STOPS, $stops );
		if ( class_exists( 'NT_Alerts_Alert' ) ) {
			NT_Alerts_Alert::flush_stops_lookup();
		}
		if ( class_exists( 'NT_Alerts_Cache' ) ) {
			NT_Alerts_Cache::flush();
		}

		wp_safe_redirect( add_query_arg(
			array( 'page' => self::PAGE_SLUG, 'reseeded' => count( $stops ) ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public static function register_settings() {
		register_setting( self::OPTION_GROUP, self::OPT_CORS_ORIGINS,    array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_cors_origins' ),
			'default'           => array(),
		) );
		register_setting( self::OPTION_GROUP, self::OPT_ROUTES, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_routes' ),
			'default'           => array(),
		) );
		register_setting( self::OPTION_GROUP, self::OPT_DEFAULT_DURATIONS, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_default_durations' ),
			'default'           => NT_Alerts_Activator::default_category_durations(),
		) );
		register_setting( self::OPTION_GROUP, self::OPT_EMBED_VERSION, array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_embed_version' ),
			'default'           => NT_ALERTS_VERSION,
		) );
		register_setting( self::OPTION_GROUP, self::OPT_CACHE_TTL, array(
			'type'              => 'integer',
			'sanitize_callback' => array( __CLASS__, 'sanitize_cache_ttl' ),
			'default'           => NT_ALERTS_CACHE_TTL_DEFAULT,
		) );
		register_setting( self::OPTION_GROUP, self::OPT_ARCHIVE_AFTER, array(
			'type'              => 'integer',
			'sanitize_callback' => array( __CLASS__, 'sanitize_archive_after' ),
			'default'           => 30,
		) );
		register_setting( self::OPTION_GROUP, self::OPT_NEW_ALERT_NOTIFY, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_emails' ),
			'default'           => array(),
		) );
	}

	public static function sanitize_emails( $value ) {
		if ( is_string( $value ) ) {
			$lines = preg_split( "/\r\n|\r|\n|,/", $value );
		} elseif ( is_array( $value ) ) {
			$lines = $value;
		} else {
			return array();
		}

		$out = array();
		foreach ( $lines as $line ) {
			$email = sanitize_email( trim( (string) $line ) );
			if ( $email && is_email( $email ) ) {
				$out[] = $email;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_nt_alerts_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage settings.', 'nt-alerts' ), 403 );
		}

		$nt_settings = array(
			'cors_origins'      => get_option( self::OPT_CORS_ORIGINS, array() ),
			'routes'            => get_option( self::OPT_ROUTES, array() ),
			'stops_count'       => count( (array) get_option( self::OPT_STOPS, array() ) ),
			'default_durations' => get_option( self::OPT_DEFAULT_DURATIONS, NT_Alerts_Activator::default_category_durations() ),
			'embed_version'     => (string) get_option( self::OPT_EMBED_VERSION, NT_ALERTS_VERSION ),
			'cache_ttl'         => (int) get_option( self::OPT_CACHE_TTL, NT_ALERTS_CACHE_TTL_DEFAULT ),
			'archive_after'     => (int) get_option( self::OPT_ARCHIVE_AFTER, 30 ),
			'notify_emails'     => (array) get_option( self::OPT_NEW_ALERT_NOTIFY, array() ),
			'reseeded'          => isset( $_GET['reseeded'] ) ? (int) $_GET['reseeded'] : 0,
		);

		include NT_ALERTS_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/* -----------------------------------------------------------------
	 * Sanitizers
	 * ----------------------------------------------------------------- */

	public static function sanitize_cors_origins( $value ) {
		if ( is_string( $value ) ) {
			$lines = preg_split( "/\r\n|\r|\n/", $value );
		} elseif ( is_array( $value ) ) {
			$lines = $value;
		} else {
			return array();
		}

		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			// Reject wildcards and obviously invalid origins.
			if ( '*' === $line ) {
				continue;
			}
			$parts = wp_parse_url( $line );
			if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
				continue;
			}
			if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
				continue;
			}
			$origin = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );
			if ( isset( $parts['port'] ) ) {
				$origin .= ':' . (int) $parts['port'];
			}
			$out[] = $origin;
		}
		return array_values( array_unique( $out ) );
	}

	public static function sanitize_routes( $value ) {
		// Form posts the textarea as a JSON string under key 'json'.
		if ( is_array( $value ) && isset( $value['json'] ) ) {
			$json = (string) $value['json'];
		} elseif ( is_string( $value ) ) {
			$json = $value;
		} else {
			// Already an array — passed through.
			return is_array( $value ) ? $value : array();
		}

		$decoded = json_decode( wp_unslash( $json ), true );
		if ( ! is_array( $decoded ) ) {
			add_settings_error(
				self::OPT_ROUTES,
				'nt_alerts_routes_invalid',
				__( 'Routes JSON could not be parsed; previous value kept.', 'nt-alerts' )
			);
			return get_option( self::OPT_ROUTES, array() );
		}

		$clean_groups = array();
		foreach ( $decoded as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$label = isset( $group['group'] ) ? sanitize_text_field( (string) $group['group'] ) : '';
			$routes_in = isset( $group['routes'] ) && is_array( $group['routes'] ) ? $group['routes'] : array();
			$clean_routes = array();
			foreach ( $routes_in as $route ) {
				if ( ! is_array( $route ) || empty( $route['id'] ) ) {
					continue;
				}
				$entry = array(
					'id'    => sanitize_text_field( (string) $route['id'] ),
					'label' => isset( $route['label'] ) ? sanitize_text_field( (string) $route['label'] ) : '',
				);
				if ( ! empty( $route['color'] ) ) {
					$entry['color'] = sanitize_hex_color( (string) $route['color'] );
				}
				$clean_routes[] = $entry;
			}
			if ( '' === $label && empty( $clean_routes ) ) {
				continue;
			}
			$clean_groups[] = array(
				'group'  => $label,
				'routes' => $clean_routes,
			);
		}

		// Bust the public cache + routes lookup so the new routes propagate.
		if ( class_exists( 'NT_Alerts_Cache' ) ) {
			NT_Alerts_Cache::flush();
		}
		if ( class_exists( 'NT_Alerts_Alert' ) ) {
			NT_Alerts_Alert::flush_routes_lookup();
		}

		return $clean_groups;
	}

	public static function sanitize_default_durations( $value ) {
		$valid = array( '1h', '2h', '4h', 'rest_of_day', 'long_term' );
		$categories = array( 'detour', 'delay', 'cancelled_trip', 'stop_closure', 'weather', 'other' );

		$out = NT_Alerts_Activator::default_category_durations();
		if ( ! is_array( $value ) ) {
			return $out;
		}
		foreach ( $categories as $cat ) {
			if ( isset( $value[ $cat ] ) && in_array( $value[ $cat ], $valid, true ) ) {
				$out[ $cat ] = $value[ $cat ];
			}
		}
		return $out;
	}

	public static function sanitize_embed_version( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return NT_ALERTS_VERSION;
		}
		// Allow letters, numbers, dot, dash, underscore.
		$value = preg_replace( '/[^A-Za-z0-9._-]/', '', $value );
		return '' === $value ? NT_ALERTS_VERSION : $value;
	}

	public static function sanitize_cache_ttl( $value ) {
		$n = absint( $value );
		if ( $n < 5 )      $n = 5;     // floor to avoid hammering the DB
		if ( $n > 3600 )   $n = 3600;  // ceiling at 1h
		return $n;
	}

	public static function sanitize_archive_after( $value ) {
		$n = absint( $value );
		if ( $n < 1 )    $n = 1;
		if ( $n > 365 )  $n = 365;
		return $n;
	}
}
