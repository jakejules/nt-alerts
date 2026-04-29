<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_CPT {

	const ALERT_TYPES = array( 'short_term', 'long_term' );
	const CATEGORIES  = array( 'detour', 'delay', 'cancelled_trip', 'stop_closure', 'reduced_service', 'other' );
	const SEVERITIES  = array( 'info', 'warning', 'critical' );
	const STATUSES    = array( 'active', 'expired', 'archived' );

	const REASONS = array(
		'construction', 'street_closure', 'weather', 'maintenance',
		'police_activity', 'fire', 'evacuation', 'terminal_closure',
		'collision', 'parade', 'other',
	);

	const DEPARTMENTS      = array( 'operations', 'maintenance' );
	const INTERNAL_REASONS = array( 'change_off', 'breakdown', 'accident', 'unsanitary' );

	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register' ), 10 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 11 );
	}

	public static function register() {
		// show_ui stays true so the Archive view's "View" link can open the
		// classic editor for full inspection (admins only). show_in_menu is
		// false so the CPT doesn't duplicate the custom Service Alerts menu.
		$args = array(
			'labels'              => self::labels(),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'menu_icon'           => 'dashicons-warning',
			'menu_position'       => 30,
			'supports'            => array( 'title', 'author' ),
			'capability_type'     => array( 'nt_alert', 'nt_alerts' ),
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'hierarchical'        => false,
			'can_export'          => true,
		);

		register_post_type( NT_ALERTS_CPT, $args );
	}

	public static function register_meta() {
		$auth = array( __CLASS__, 'meta_auth_callback' );

		register_post_meta( NT_ALERTS_CPT, 'alert_type', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_enum_alert_type' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'category', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_enum_category' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'severity', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_enum_severity' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'routes', array(
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_routes' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'description', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_textarea_field',
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'start_time', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_iso8601' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'end_time', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_iso8601' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'last_updated', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_iso8601' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'status', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_enum_status' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'details_url', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'reason', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_enum_reason' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'closed_stops', array(
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_routes' ), // same array-of-strings shape
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'alternate_stops', array(
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_routes' ),
			'auth_callback'     => $auth,
		) );

		// Internal-only fields (excluded from the public REST response).
		register_post_meta( NT_ALERTS_CPT, 'dept_responsible', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_enum_department' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'vehicle_number', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'internal_reason', array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_enum_internal_reason' ),
			'auth_callback'     => $auth,
		) );

		register_post_meta( NT_ALERTS_CPT, 'images', array(
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => array( __CLASS__, 'sanitize_image_ids' ),
			'auth_callback'     => $auth,
		) );
	}

	const MAX_IMAGES = 3;

	public static function sanitize_image_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $id ) {
			$id = absint( $id );
			if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
				$out[] = $id;
			}
			if ( count( $out ) >= self::MAX_IMAGES ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function meta_auth_callback( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	public static function sanitize_enum_alert_type( $value ) {
		return in_array( $value, self::ALERT_TYPES, true ) ? $value : 'short_term';
	}

	public static function sanitize_enum_category( $value ) {
		return in_array( $value, self::CATEGORIES, true ) ? $value : 'other';
	}

	public static function sanitize_enum_severity( $value ) {
		return in_array( $value, self::SEVERITIES, true ) ? $value : 'info';
	}

	public static function sanitize_enum_status( $value ) {
		return in_array( $value, self::STATUSES, true ) ? $value : 'active';
	}

	public static function sanitize_enum_reason( $value ) {
		return in_array( $value, self::REASONS, true ) ? $value : '';
	}

	public static function sanitize_enum_department( $value ) {
		return in_array( $value, self::DEPARTMENTS, true ) ? $value : '';
	}

	public static function sanitize_enum_internal_reason( $value ) {
		return in_array( $value, self::INTERNAL_REASONS, true ) ? $value : '';
	}

	public static function sanitize_routes( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$clean = array();
		foreach ( $value as $id ) {
			$id = sanitize_text_field( (string) $id );
			if ( '' !== $id ) {
				$clean[] = $id;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	public static function sanitize_iso8601( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$ts = strtotime( $value );
		if ( false === $ts ) {
			return '';
		}
		return gmdate( 'c', $ts );
	}

	private static function labels() {
		return array(
			'name'                  => _x( 'Service Alerts', 'post type general name', 'nt-alerts' ),
			'singular_name'         => _x( 'Service Alert', 'post type singular name', 'nt-alerts' ),
			'menu_name'             => _x( 'Service Alerts', 'admin menu', 'nt-alerts' ),
			'name_admin_bar'        => _x( 'Service Alert', 'add new on admin bar', 'nt-alerts' ),
			'add_new'               => _x( 'Add New', 'service alert', 'nt-alerts' ),
			'add_new_item'          => __( 'Add New Alert', 'nt-alerts' ),
			'new_item'              => __( 'New Alert', 'nt-alerts' ),
			'edit_item'             => __( 'Edit Alert', 'nt-alerts' ),
			'view_item'             => __( 'View Alert', 'nt-alerts' ),
			'all_items'             => __( 'All Alerts', 'nt-alerts' ),
			'search_items'          => __( 'Search Alerts', 'nt-alerts' ),
			'not_found'             => __( 'No alerts found.', 'nt-alerts' ),
			'not_found_in_trash'    => __( 'No alerts found in Trash.', 'nt-alerts' ),
		);
	}
}
