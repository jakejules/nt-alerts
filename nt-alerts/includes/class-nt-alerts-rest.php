<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_REST {

	public static function register_hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$ns = NT_ALERTS_REST_NAMESPACE;

		register_rest_route( $ns, '/alerts/active', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'handle_get_active' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/alerts/route/(?P<route_id>[A-Za-z0-9_-]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'handle_get_by_route' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'route_id' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		) );

		register_rest_route( $ns, '/stops', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'handle_get_stops' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/alerts/mine', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'handle_mine' ),
			'permission_callback' => array( __CLASS__, 'can_list_own' ),
		) );

		register_rest_route( $ns, '/alerts', array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => array( __CLASS__, 'handle_create' ),
			'permission_callback' => array( __CLASS__, 'can_create' ),
			'args'                => self::write_args( true ),
		) );

		register_rest_route( $ns, '/alerts/(?P<id>\d+)', array(
			'methods'             => 'PATCH',
			'callback'            => array( __CLASS__, 'handle_update' ),
			'permission_callback' => array( __CLASS__, 'can_edit_post_arg' ),
			'args'                => self::write_args( false ),
		) );

		register_rest_route( $ns, '/alerts/(?P<id>\d+)/end', array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => array( __CLASS__, 'handle_end' ),
			'permission_callback' => array( __CLASS__, 'can_edit_post_arg' ),
		) );
	}

	/* -----------------------------------------------------------------
	 * Permission callbacks
	 * ----------------------------------------------------------------- */

	public static function can_create() {
		return current_user_can( 'publish_nt_alerts' );
	}

	public static function can_list_own() {
		return current_user_can( 'edit_nt_alerts' );
	}

	public static function can_edit_post_arg( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( $id <= 0 || NT_ALERTS_CPT !== get_post_type( $id ) ) {
			return new WP_Error(
				'nt_alerts_not_found',
				__( 'Alert not found.', 'nt-alerts' ),
				array( 'status' => 404 )
			);
		}
		return current_user_can( 'edit_post', $id );
	}

	/* -----------------------------------------------------------------
	 * Arg schemas
	 * ----------------------------------------------------------------- */

	private static function write_args( $creating ) {
		return array(
			'alert_type'  => array(
				'type'     => 'string',
				'enum'     => NT_Alerts_CPT::ALERT_TYPES,
				'required' => $creating,
			),
			'category'    => array(
				'type'     => 'string',
				'enum'     => NT_Alerts_CPT::CATEGORIES,
				'required' => $creating,
			),
			'severity'    => array(
				'type' => 'string',
				'enum' => NT_Alerts_CPT::SEVERITIES,
			),
			'routes'      => array(
				'type'     => 'array',
				'items'    => array( 'type' => 'string' ),
				'required' => $creating,
			),
			'title'       => array( 'type' => 'string', 'required' => $creating ),
			'description' => array( 'type' => 'string' ),
			'start_time'  => array( 'type' => 'string' ),
			'end_time'    => array( 'type' => 'string' ),
			'details_url' => array( 'type' => 'string' ),
			'reason'      => array(
				'type' => 'string',
				'enum' => array_merge( array( '' ), NT_Alerts_CPT::REASONS ),
			),
			'closed_stops'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'alternate_stops' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'dept_responsible' => array(
				'type' => 'string',
				'enum' => array_merge( array( '' ), NT_Alerts_CPT::DEPARTMENTS ),
			),
			'vehicle_number'  => array( 'type' => 'string' ),
			'internal_reason' => array(
				'type' => 'string',
				'enum' => array_merge( array( '' ), NT_Alerts_CPT::INTERNAL_REASONS ),
			),
			'internal_notes'  => array( 'type' => 'string' ),
			'images' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
		);
	}

	/* -----------------------------------------------------------------
	 * Handlers
	 * ----------------------------------------------------------------- */

	public static function handle_get_active( WP_REST_Request $request ) {
		unset( $request );

		$cached = NT_Alerts_Cache::get_active();
		$response = rest_ensure_response( false === $cached ? self::build_active_payload() : $cached );

		if ( false === $cached ) {
			NT_Alerts_Cache::set_active( $response->get_data() );
		}
		$response->header( 'X-Nt-Alerts-Cache', NT_Alerts_Cache::last_status() );

		return $response;
	}

	public static function handle_get_stops( WP_REST_Request $request ) {
		unset( $request );
		$stops = get_option( 'nt_alerts_stops', array() );
		if ( ! is_array( $stops ) ) {
			$stops = array();
		}
		$response = rest_ensure_response( array(
			'updated_at' => wp_date( DATE_ATOM ),
			'count'      => count( $stops ),
			'stops'      => array_values( $stops ),
		) );
		// Stops change rarely; let downstream clients cache for 5 minutes.
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	public static function handle_get_by_route( WP_REST_Request $request ) {
		$route_id = (string) $request->get_param( 'route_id' );
		$payload  = self::build_active_payload();

		$filter = function ( $alert ) use ( $route_id ) {
			return is_array( $alert )
				&& isset( $alert['routes'] )
				&& is_array( $alert['routes'] )
				&& in_array( $route_id, $alert['routes'], true );
		};

		$payload['alerts']['short_term'] = array_values( array_filter( $payload['alerts']['short_term'], $filter ) );
		$payload['alerts']['long_term']  = array_values( array_filter( $payload['alerts']['long_term'],  $filter ) );
		$payload['route_id']             = $route_id;

		return rest_ensure_response( $payload );
	}

	public static function handle_mine( WP_REST_Request $request ) {
		unset( $request );

		$posts = get_posts( array(
			'post_type'        => NT_ALERTS_CPT,
			'post_status'      => array( 'publish', 'future', 'draft' ),
			'author'           => get_current_user_id(),
			'numberposts'      => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		) );

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = NT_Alerts_Alert::to_public_array( $post );
		}

		return rest_ensure_response( array(
			'updated_at' => wp_date( DATE_ATOM ),
			'alerts'     => $out,
		) );
	}

	public static function handle_create( WP_REST_Request $request ) {
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( '' === $title ) {
			return new WP_Error(
				'nt_alerts_title_required',
				__( 'Title is required.', 'nt-alerts' ),
				array( 'status' => 400 )
			);
		}

		$now = self::now_iso();

		$post_id = wp_insert_post( array(
			'post_type'   => NT_ALERTS_CPT,
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_author' => get_current_user_id(),
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = NT_Alerts_Alert::from_request( $request );

		if ( ! isset( $meta['status'] ) ) {
			$meta['status'] = 'active';
		}
		if ( ! isset( $meta['start_time'] ) || '' === $meta['start_time'] ) {
			$meta['start_time'] = $now;
		}
		$meta['last_updated'] = $now;

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		NT_Alerts_Cache::flush();

		// Fire the operator notification AFTER meta is written so the email
		// body has the full alert context (category, routes, expires, etc.).
		if ( class_exists( 'NT_Alerts_Notifications' ) ) {
			NT_Alerts_Notifications::send_new_alert_log( get_post( $post_id ) );
		}

		$response = rest_ensure_response( NT_Alerts_Alert::to_public_array( get_post( $post_id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	public static function handle_update( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( $request->has_param( 'title' ) ) {
			$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
			if ( '' !== $title ) {
				wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
			}
		}

		$meta = NT_Alerts_Alert::from_request( $request );
		$meta['last_updated'] = self::now_iso();

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}

		NT_Alerts_Cache::flush();

		return rest_ensure_response( NT_Alerts_Alert::to_public_array( get_post( $id ) ) );
	}

	public static function handle_end( WP_REST_Request $request ) {
		$id  = (int) $request->get_param( 'id' );
		$now = self::now_iso();

		update_post_meta( $id, 'status',       'expired' );
		update_post_meta( $id, 'end_time',     $now );
		update_post_meta( $id, 'last_updated', $now );

		NT_Alerts_Cache::flush();

		return rest_ensure_response( NT_Alerts_Alert::to_public_array( get_post( $id ) ) );
	}

	/* -----------------------------------------------------------------
	 * Internal helpers
	 * ----------------------------------------------------------------- */

	private static function build_active_payload() {
		$now_ts = time();
		$now    = gmdate( 'c', $now_ts );

		$posts = get_posts( array(
			'post_type'        => NT_ALERTS_CPT,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'meta_query'       => array(
				'relation' => 'AND',
				array(
					'key'     => 'status',
					'value'   => 'active',
					'compare' => '=',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => 'end_time',
						'value'   => '',
						'compare' => '=',
					),
					array(
						'key'     => 'end_time',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'end_time',
						'value'   => $now,
						'compare' => '>',
						'type'    => 'CHAR',
					),
				),
			),
			'suppress_filters' => false,
		) );

		$short = array();
		$long  = array();

		foreach ( $posts as $post ) {
			$alert = NT_Alerts_Alert::to_public_array( $post );
			if ( 'long_term' === $alert['alert_type'] ) {
				$long[] = $alert;
			} else {
				$short[] = $alert;
			}
		}

		return array(
			'updated_at' => wp_date( DATE_ATOM, $now_ts ),
			'alerts'     => array(
				'short_term' => $short,
				'long_term'  => $long,
			),
		);
	}

	private static function now_iso() {
		return gmdate( 'c' );
	}
}
