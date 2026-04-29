<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Alert {

	/**
	 * Transform a nt_alert post into the public JSON shape defined in the spec.
	 */
	public static function to_public_array( WP_Post $post ) {
		$alert_type = (string) get_post_meta( $post->ID, 'alert_type', true );
		if ( '' === $alert_type ) {
			$alert_type = 'short_term';
		}

		$routes = get_post_meta( $post->ID, 'routes', true );
		if ( ! is_array( $routes ) ) {
			$routes = array();
		}

		$posted_at = self::format_iso8601( $post->post_date_gmt, true );

		$start_time   = (string) get_post_meta( $post->ID, 'start_time', true );
		$end_time     = (string) get_post_meta( $post->ID, 'end_time', true );
		$last_updated = (string) get_post_meta( $post->ID, 'last_updated', true );

		$route_ids = array_values( array_map( 'strval', $routes ) );

		$data = array(
			'id'            => (int) $post->ID,
			'title'         => get_the_title( $post ),
			'description'   => (string) get_post_meta( $post->ID, 'description', true ),
			'category'      => (string) get_post_meta( $post->ID, 'category', true ),
			'severity'      => (string) get_post_meta( $post->ID, 'severity', true ),
			'alert_type'    => $alert_type,
			'routes'        => $route_ids,
			'routes_detail' => self::resolve_routes( $route_ids ),
			'posted_at'     => $posted_at,
			'expires_at'    => self::format_iso8601( $end_time, false ),
			'last_updated'  => '' !== $last_updated
				? self::format_iso8601( $last_updated, false )
				: $posted_at,
		);

		if ( '' !== $start_time ) {
			$data['start_time'] = self::format_iso8601( $start_time, false );
		}

		if ( 'long_term' === $alert_type ) {
			$details_url = (string) get_post_meta( $post->ID, 'details_url', true );
			if ( '' !== $details_url ) {
				$data['details_url'] = esc_url_raw( $details_url );
			}
		}

		$reason = (string) get_post_meta( $post->ID, 'reason', true );
		if ( '' !== $reason ) {
			$data['reason'] = $reason;
		}

		$closed_stops = get_post_meta( $post->ID, 'closed_stops', true );
		if ( is_array( $closed_stops ) && ! empty( $closed_stops ) ) {
			$data['closed_stops'] = self::resolve_stops( $closed_stops );
		}

		$alt_stops = get_post_meta( $post->ID, 'alternate_stops', true );
		if ( is_array( $alt_stops ) && ! empty( $alt_stops ) ) {
			$data['alternate_stops'] = self::resolve_stops( $alt_stops );
		}

		$image_ids = get_post_meta( $post->ID, 'images', true );
		if ( is_array( $image_ids ) && ! empty( $image_ids ) ) {
			$data['images'] = self::resolve_images( $image_ids, $data['title'] );
		}

		// Internal-only fields (dept_responsible, vehicle_number, internal_reason)
		// are intentionally NOT included here. Use to_admin_array() instead.

		return $data;
	}

	/**
	 * Like to_public_array(), but also includes internal fields. Use this only
	 * in admin-side rendering (dashboard, archive). NEVER return from the
	 * public REST endpoint.
	 */
	public static function to_admin_array( WP_Post $post ) {
		$data = self::to_public_array( $post );

		$author_id = (int) $post->post_author;
		if ( $author_id > 0 ) {
			$display = get_the_author_meta( 'display_name', $author_id );
			if ( '' !== $display ) {
				$data['posted_by'] = array(
					'id'   => $author_id,
					'name' => $display,
				);
			}
		}

		$dept = (string) get_post_meta( $post->ID, 'dept_responsible', true );
		if ( '' !== $dept ) {
			$data['dept_responsible'] = $dept;
		}
		$veh = (string) get_post_meta( $post->ID, 'vehicle_number', true );
		if ( '' !== $veh ) {
			$data['vehicle_number'] = $veh;
		}
		$ir = (string) get_post_meta( $post->ID, 'internal_reason', true );
		if ( '' !== $ir ) {
			$data['internal_reason'] = $ir;
		}

		return $data;
	}

	/**
	 * Build a sanitized meta-value array from an incoming REST request.
	 * Returns only keys that were actually present, so PATCH can partial-update.
	 */
	public static function from_request( WP_REST_Request $request ) {
		$meta = array();

		if ( $request->has_param( 'alert_type' ) ) {
			$meta['alert_type'] = NT_Alerts_CPT::sanitize_enum_alert_type( (string) $request->get_param( 'alert_type' ) );
		}
		if ( $request->has_param( 'category' ) ) {
			$meta['category'] = NT_Alerts_CPT::sanitize_enum_category( (string) $request->get_param( 'category' ) );
		}
		if ( $request->has_param( 'severity' ) ) {
			$meta['severity'] = NT_Alerts_CPT::sanitize_enum_severity( (string) $request->get_param( 'severity' ) );
		}
		if ( $request->has_param( 'status' ) ) {
			$meta['status'] = NT_Alerts_CPT::sanitize_enum_status( (string) $request->get_param( 'status' ) );
		}
		if ( $request->has_param( 'routes' ) ) {
			$meta['routes'] = NT_Alerts_CPT::sanitize_routes( $request->get_param( 'routes' ) );
		}
		if ( $request->has_param( 'description' ) ) {
			$meta['description'] = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
		}
		if ( $request->has_param( 'start_time' ) ) {
			$meta['start_time'] = NT_Alerts_CPT::sanitize_iso8601( (string) $request->get_param( 'start_time' ) );
		}
		if ( $request->has_param( 'end_time' ) ) {
			$meta['end_time'] = NT_Alerts_CPT::sanitize_iso8601( (string) $request->get_param( 'end_time' ) );
		}
		if ( $request->has_param( 'details_url' ) ) {
			$meta['details_url'] = esc_url_raw( (string) $request->get_param( 'details_url' ) );
		}
		if ( $request->has_param( 'reason' ) ) {
			$meta['reason'] = NT_Alerts_CPT::sanitize_enum_reason( (string) $request->get_param( 'reason' ) );
		}
		if ( $request->has_param( 'closed_stops' ) ) {
			$meta['closed_stops'] = NT_Alerts_CPT::sanitize_routes( $request->get_param( 'closed_stops' ) );
		}
		if ( $request->has_param( 'alternate_stops' ) ) {
			$meta['alternate_stops'] = NT_Alerts_CPT::sanitize_routes( $request->get_param( 'alternate_stops' ) );
		}
		if ( $request->has_param( 'dept_responsible' ) ) {
			$meta['dept_responsible'] = NT_Alerts_CPT::sanitize_enum_department( (string) $request->get_param( 'dept_responsible' ) );
		}
		if ( $request->has_param( 'vehicle_number' ) ) {
			$meta['vehicle_number'] = sanitize_text_field( (string) $request->get_param( 'vehicle_number' ) );
		}
		if ( $request->has_param( 'internal_reason' ) ) {
			$meta['internal_reason'] = NT_Alerts_CPT::sanitize_enum_internal_reason( (string) $request->get_param( 'internal_reason' ) );
		}
		if ( $request->has_param( 'images' ) ) {
			$meta['images'] = NT_Alerts_CPT::sanitize_image_ids( (array) $request->get_param( 'images' ) );
		}

		return $meta;
	}

	private static $routes_lookup = null;
	private static $stops_lookup  = null;

	private static function routes_lookup() {
		if ( null === self::$routes_lookup ) {
			$catalogue = get_option( 'nt_alerts_routes', array() );
			self::$routes_lookup = array();
			if ( is_array( $catalogue ) ) {
				foreach ( $catalogue as $group ) {
					if ( ! is_array( $group ) || empty( $group['routes'] ) ) {
						continue;
					}
					foreach ( $group['routes'] as $route ) {
						if ( ! is_array( $route ) || empty( $route['id'] ) ) {
							continue;
						}
						self::$routes_lookup[ (string) $route['id'] ] = array(
							'id'    => (string) $route['id'],
							'label' => isset( $route['label'] ) ? (string) $route['label'] : '',
							'color' => isset( $route['color'] ) ? (string) $route['color'] : '',
						);
					}
				}
			}
		}
		return self::$routes_lookup;
	}

	private static function resolve_routes( array $ids ) {
		$lookup = self::routes_lookup();
		$out = array();
		foreach ( $ids as $rid ) {
			$rid = (string) $rid;
			if ( isset( $lookup[ $rid ] ) ) {
				$out[] = $lookup[ $rid ];
			} else {
				$out[] = array( 'id' => $rid, 'label' => '', 'color' => '' );
			}
		}
		return $out;
	}

	public static function flush_routes_lookup() {
		self::$routes_lookup = null;
	}

	private static function stops_lookup() {
		if ( null === self::$stops_lookup ) {
			$catalogue = get_option( 'nt_alerts_stops', array() );
			self::$stops_lookup = array();
			if ( is_array( $catalogue ) ) {
				foreach ( $catalogue as $stop ) {
					if ( ! is_array( $stop ) || empty( $stop['id'] ) ) {
						continue;
					}
					self::$stops_lookup[ (string) $stop['id'] ] = array(
						'id'   => (string) $stop['id'],
						'name' => isset( $stop['name'] ) ? (string) $stop['name'] : '',
						'code' => isset( $stop['code'] ) ? (string) $stop['code'] : '',
					);
				}
			}
		}
		return self::$stops_lookup;
	}

	private static function resolve_stops( array $ids ) {
		$lookup = self::stops_lookup();
		$out = array();
		foreach ( $ids as $sid ) {
			$sid = (string) $sid;
			if ( isset( $lookup[ $sid ] ) ) {
				$out[] = $lookup[ $sid ];
			} else {
				$out[] = array( 'id' => $sid, 'name' => $sid, 'code' => '' );
			}
		}
		return $out;
	}

	public static function flush_stops_lookup() {
		self::$stops_lookup = null;
	}

	private static function resolve_images( array $ids, $title ) {
		$out = array();
		$total = count( $ids );
		$index = 0;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
				continue;
			}
			$index++;
			$full      = wp_get_attachment_image_src( $id, 'full' );
			$large     = wp_get_attachment_image_src( $id, 'large' );
			$thumbnail = wp_get_attachment_image_src( $id, 'medium' );

			$out[] = array(
				'id'        => $id,
				'url'       => $full ? $full[0] : '',
				'large'     => $large ? $large[0] : ( $full ? $full[0] : '' ),
				'thumbnail' => $thumbnail ? $thumbnail[0] : ( $full ? $full[0] : '' ),
				'width'     => $full ? (int) $full[1] : 0,
				'height'    => $full ? (int) $full[2] : 0,
				'alt'       => self::auto_alt_text( $title, $index, $total ),
			);
		}
		return $out;
	}

	private static function auto_alt_text( $title, $index, $total ) {
		$title = trim( (string) $title );
		if ( '' === $title ) {
			$title = __( 'Service alert', 'nt-alerts' );
		}
		if ( $total <= 1 ) {
			return $title;
		}
		return sprintf(
			/* translators: 1: alert title, 2: image index, 3: total count */
			__( '%1$s — image %2$d of %3$d', 'nt-alerts' ),
			$title,
			$index,
			$total
		);
	}

	public static function format_iso8601( $value, $is_gmt ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}
		$ts = $is_gmt ? strtotime( $value . ' UTC' ) : strtotime( $value );
		if ( false === $ts ) {
			return '';
		}
		return wp_date( DATE_ATOM, $ts );
	}
}
