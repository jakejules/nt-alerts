<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Notifications {

	public static function register_hooks() {
		// No bootstrap hooks needed; the new-alert log is invoked from REST.
	}

	/**
	 * Sends a "new alert posted" notification to the operator-configured
	 * email list. Fires for every newly published nt_alert post, regardless
	 * of who created it.
	 */
	public static function send_new_alert_log( WP_Post $post ) {
		$recipients = (array) get_option( 'nt_alerts_new_alert_notify', array() );
		$recipients = array_filter( array_map( 'sanitize_email', $recipients ) );
		if ( empty( $recipients ) ) {
			return;
		}

		$rendered = self::render_new_alert_log( array( 'post' => $post ) );
		if ( empty( $rendered['subject'] ) || empty( $rendered['body'] ) ) {
			return;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $recipients, $rendered['subject'], $rendered['body'], $headers );
	}

	/* -----------------------------------------------------------------
	 * Template rendering
	 * ----------------------------------------------------------------- */

	private static function render_new_alert_log( array $context ) {
		$post = isset( $context['post'] ) ? $context['post'] : null;
		if ( ! ( $post instanceof WP_Post ) ) {
			return array();
		}

		$alert     = NT_Alerts_Alert::to_admin_array( $post );
		$author    = get_userdata( (int) $post->post_author );
		$dashboard = admin_url( 'admin.php?page=' . NT_Alerts_Admin::MENU_SLUG );

		$category_labels = NT_Alerts_Admin::category_labels();
		$reason_labels   = NT_Alerts_Admin::reason_labels();
		$dept_labels     = NT_Alerts_Admin::department_labels();
		$internal_labels = NT_Alerts_Admin::internal_reason_labels();

		$cat       = isset( $alert['category'] ) ? $alert['category'] : '';
		$cat_label = isset( $category_labels[ $cat ] ) ? $category_labels[ $cat ] : ucfirst( str_replace( '_', ' ', $cat ) );

		$severity       = isset( $alert['severity'] ) ? $alert['severity'] : '';
		$severity_label = '' !== $severity ? ucfirst( $severity ) : '';

		$reason       = isset( $alert['reason'] ) ? $alert['reason'] : '';
		$reason_label = isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : '';

		$routes      = isset( $alert['routes'] ) && is_array( $alert['routes'] ) ? $alert['routes'] : array();
		$cities      = self::cities_for_routes( $routes );
		$is_long     = isset( $alert['alert_type'] ) && 'long_term' === $alert['alert_type'];
		$start_iso   = isset( $alert['start_time'] ) && '' !== $alert['start_time'] ? $alert['start_time'] : ( isset( $alert['posted_at'] ) ? $alert['posted_at'] : '' );
		$start_str   = self::friendly_date( $start_iso );
		$expires_iso = isset( $alert['expires_at'] ) ? $alert['expires_at'] : '';
		if ( $expires_iso ) {
			$end_str = self::friendly_date( $expires_iso );
		} else {
			$end_str = $is_long ? __( 'No scheduled end', 'nt-alerts' ) : __( '—', 'nt-alerts' );
		}

		$author_name = $author && $author->display_name ? $author->display_name : __( 'an unknown user', 'nt-alerts' );

		$subject = sprintf(
			/* translators: 1: severity word, 2: category label, 3: routes joined, 4: title */
			__( '[NT Alerts] %1$s — %2$s on %3$s: %4$s', 'nt-alerts' ),
			$severity_label ? $severity_label : __( 'Alert', 'nt-alerts' ),
			$cat_label ? $cat_label : __( 'Service alert', 'nt-alerts' ),
			! empty( $routes ) ? implode( ', ', $routes ) : __( '(no routes)', 'nt-alerts' ),
			$alert['title']
		);

		$severity_palette = array(
			'info'     => array( 'bg' => '#2c6aa0', 'fg' => '#ffffff' ),
			'warning'  => array( 'bg' => '#a4731b', 'fg' => '#ffffff' ),
			'critical' => array( 'bg' => '#a12117', 'fg' => '#ffffff' ),
		);
		$palette = isset( $severity_palette[ $severity ] ) ? $severity_palette[ $severity ] : $severity_palette['info'];

		$dept_key = isset( $alert['dept_responsible'] ) ? $alert['dept_responsible'] : '';
		$veh      = isset( $alert['vehicle_number'] )   ? $alert['vehicle_number']   : '';
		$ir_key   = isset( $alert['internal_reason'] )  ? $alert['internal_reason']  : '';
		$notes    = isset( $alert['internal_notes'] )   ? $alert['internal_notes']   : '';

		$body  = '';
		$body .= '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1a1a1a;max-width:640px;margin:0 auto;background:#ffffff;">';

		// Severity strip + category
		$body .= sprintf(
			'<div style="background:%1$s;color:%2$s;padding:8px 16px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">%3$s &middot; %4$s</div>',
			esc_attr( $palette['bg'] ),
			esc_attr( $palette['fg'] ),
			esc_html( $severity_label ? $severity_label : __( 'Alert', 'nt-alerts' ) ),
			esc_html( $cat_label ? $cat_label : __( 'Service alert', 'nt-alerts' ) )
		);

		// Title
		$body .= '<h1 style="font-size:22px;line-height:1.25;margin:14px 16px 4px;color:#1a1a1a;font-weight:700;">' . esc_html( $alert['title'] ) . '</h1>';

		// Key facts table: Cities | Routes (top) / Start | End (bottom)
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate;width:calc(100% - 32px);margin:12px 16px;background:#eef4fb;border:1px solid #c8dbef;border-radius:6px;">';
		$body .= '<tr>';
		$body .= self::fact_cell( __( 'Cities Affected', 'nt-alerts' ), '' !== $cities ? $cities : __( '—', 'nt-alerts' ) );
		$body .= self::fact_cell( __( 'Routes Affected', 'nt-alerts' ), ! empty( $routes ) ? implode( ', ', $routes ) : __( '—', 'nt-alerts' ) );
		$body .= '</tr>';
		$body .= '<tr>';
		$body .= self::fact_cell( __( 'Start Date', 'nt-alerts' ), '' !== $start_str ? $start_str : __( '—', 'nt-alerts' ) );
		$body .= self::fact_cell( __( 'End Date', 'nt-alerts' ), $end_str );
		$body .= '</tr>';
		$body .= '</table>';

		// Reason (inline, smaller)
		if ( $reason_label ) {
			$body .= '<p style="margin:6px 16px 12px;font-size:14px;color:#333;">'
				. '<strong>' . esc_html__( 'Reason:', 'nt-alerts' ) . '</strong> '
				. esc_html( $reason_label )
				. '</p>';
		}

		// Description
		if ( ! empty( $alert['description'] ) ) {
			$body .= '<div style="margin:14px 16px;">';
			$body .= '<div style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#666;margin-bottom:4px;">' . esc_html__( 'Description', 'nt-alerts' ) . '</div>';
			$body .= '<div style="font-size:15px;line-height:1.5;color:#1a1a1a;">' . nl2br( esc_html( $alert['description'] ) ) . '</div>';
			$body .= '</div>';
		}

		// Closed stops
		if ( ! empty( $alert['closed_stops'] ) ) {
			$body .= self::stops_block( __( 'Closed stops', 'nt-alerts' ), $alert['closed_stops'], '#a12117' );
		}

		// Alternate stops
		if ( ! empty( $alert['alternate_stops'] ) ) {
			$body .= self::stops_block( __( 'Use these stops instead', 'nt-alerts' ), $alert['alternate_stops'], '#2c6aa0' );
		}

		// Internal block
		if ( $dept_key || $veh || $ir_key || '' !== $notes ) {
			$body .= '<div style="margin:14px 16px;padding:10px 14px;background:#f6f1e6;border-left:4px solid #8c6d22;border-radius:4px;">';
			$body .= '<div style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#8c6d22;margin-bottom:6px;">' . esc_html__( 'Internal — not shown to riders', 'nt-alerts' ) . '</div>';

			if ( $dept_key ) {
				$dept_label_text = isset( $dept_labels[ $dept_key ] ) ? $dept_labels[ $dept_key ] : ucfirst( $dept_key );
				$body .= '<p style="margin:2px 0;font-size:14px;color:#463810;"><strong>' . esc_html__( 'Department:', 'nt-alerts' ) . '</strong> ' . esc_html( $dept_label_text ) . '</p>';
			}
			if ( $veh ) {
				$body .= '<p style="margin:2px 0;font-size:14px;color:#463810;"><strong>' . esc_html__( 'Vehicle #:', 'nt-alerts' ) . '</strong> ' . esc_html( $veh ) . '</p>';
			}
			if ( $ir_key ) {
				$ir_label_text = isset( $internal_labels[ $ir_key ] ) ? $internal_labels[ $ir_key ] : ucfirst( str_replace( '_', ' ', $ir_key ) );
				$body .= '<p style="margin:2px 0;font-size:14px;color:#463810;"><strong>' . esc_html__( 'Maintenance reason:', 'nt-alerts' ) . '</strong> ' . esc_html( $ir_label_text ) . '</p>';
			}
			if ( '' !== $notes ) {
				$body .= '<div style="margin-top:6px;">';
				$body .= '<div style="font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#8c6d22;margin-bottom:2px;">' . esc_html__( 'Notes', 'nt-alerts' ) . '</div>';
				$body .= '<div style="font-size:14px;line-height:1.5;color:#463810;">' . nl2br( esc_html( $notes ) ) . '</div>';
				$body .= '</div>';
			}
			$body .= '</div>';
		}

		// Footer: posted by, dashboard link
		$body .= '<hr style="border:none;border-top:1px solid #e0e0e0;margin:18px 16px 12px;">';
		$body .= '<p style="margin:0 16px 4px;font-size:13px;color:#555;">'
			. sprintf(
				/* translators: 1: author display name, 2: posted timestamp */
				esc_html__( 'Posted by %1$s on %2$s', 'nt-alerts' ),
				'<strong>' . esc_html( $author_name ) . '</strong>',
				esc_html( self::friendly_time( isset( $alert['posted_at'] ) ? $alert['posted_at'] : '' ) )
			)
			. '</p>';
		$body .= '<p style="margin:6px 16px 18px;font-size:13px;">'
			. '<a href="' . esc_url( $dashboard ) . '" style="color:#2c6aa0;text-decoration:underline;">'
			. esc_html__( 'Open the supervisor dashboard →', 'nt-alerts' )
			. '</a></p>';

		$body .= '</div>';

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Render one labeled cell of the key-facts table (uppercase label
	 * with a large bold value beneath).
	 */
	private static function fact_cell( $label, $value ) {
		$html  = '<td style="padding:10px 14px;vertical-align:top;width:50%;">';
		$html .= '<div style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#4a6a8c;margin-bottom:2px;">' . esc_html( $label ) . '</div>';
		$html .= '<div style="font-size:16px;font-weight:700;color:#1a2a3a;line-height:1.3;">' . esc_html( $value ) . '</div>';
		$html .= '</td>';
		return $html;
	}

	private static function stops_block( $heading, array $stops, $accent ) {
		$html  = '<div style="margin:12px 16px;">';
		$html .= '<div style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:' . esc_attr( $accent ) . ';margin-bottom:4px;">' . esc_html( $heading ) . '</div>';
		$html .= '<ul style="margin:0;padding-left:22px;font-size:14px;color:#1a1a1a;">';
		foreach ( $stops as $s ) {
			$name = isset( $s['name'] ) && '' !== $s['name'] ? $s['name'] : ( isset( $s['id'] ) ? $s['id'] : '' );
			if ( '' !== $name ) {
				$html .= '<li style="margin:2px 0;">' . esc_html( $name ) . '</li>';
			}
		}
		$html .= '</ul>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Map a list of route IDs to the unique set of city/region group names
	 * from the route catalogue option (nt_alerts_routes). Returns a comma-
	 * separated string in catalogue order. Routes that aren't in the
	 * catalogue are silently ignored.
	 */
	private static function cities_for_routes( array $route_ids ) {
		if ( empty( $route_ids ) ) {
			return '';
		}
		$catalogue = get_option( 'nt_alerts_routes', array() );
		if ( ! is_array( $catalogue ) ) {
			return '';
		}

		$route_to_group = array();
		$group_order    = array();
		foreach ( $catalogue as $group ) {
			if ( ! is_array( $group ) || empty( $group['group'] ) || empty( $group['routes'] ) || ! is_array( $group['routes'] ) ) {
				continue;
			}
			$name = (string) $group['group'];
			$group_order[ $name ] = true;
			foreach ( $group['routes'] as $r ) {
				if ( ! empty( $r['id'] ) ) {
					$route_to_group[ (string) $r['id'] ] = $name;
				}
			}
		}

		$hit = array();
		foreach ( $route_ids as $rid ) {
			$rid = (string) $rid;
			if ( isset( $route_to_group[ $rid ] ) ) {
				$hit[ $route_to_group[ $rid ] ] = true;
			}
		}
		if ( empty( $hit ) ) {
			return '';
		}

		$ordered = array();
		foreach ( array_keys( $group_order ) as $name ) {
			if ( isset( $hit[ $name ] ) ) {
				$ordered[] = $name;
			}
		}
		return implode( ', ', $ordered );
	}

	private static function friendly_date( $iso ) {
		if ( ! is_string( $iso ) || '' === $iso ) {
			return '';
		}
		$ts = strtotime( $iso );
		if ( false === $ts ) {
			return $iso;
		}
		return wp_date( get_option( 'date_format', 'F j, Y' ), $ts );
	}

	private static function friendly_time( $iso ) {
		if ( ! is_string( $iso ) || '' === $iso ) {
			return '';
		}
		$ts = strtotime( $iso );
		if ( false === $ts ) {
			return $iso;
		}
		return wp_date( get_option( 'date_format', 'M j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' ), $ts );
	}
}
