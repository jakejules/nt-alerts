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

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
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

		$category_labels   = NT_Alerts_Admin::category_labels();
		$reason_labels     = NT_Alerts_Admin::reason_labels();
		$dept_labels       = NT_Alerts_Admin::department_labels();
		$internal_labels   = NT_Alerts_Admin::internal_reason_labels();

		$cat       = isset( $alert['category'] ) ? $alert['category'] : '';
		$cat_label = isset( $category_labels[ $cat ] ) ? $category_labels[ $cat ] : ucfirst( str_replace( '_', ' ', $cat ) );

		$severity       = isset( $alert['severity'] ) ? $alert['severity'] : '';
		$severity_label = '' !== $severity ? ucfirst( $severity ) : '';

		$reason       = isset( $alert['reason'] ) ? $alert['reason'] : '';
		$reason_label = isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : '';

		$subject = sprintf(
			/* translators: 1: severity word, 2: category label, 3: routes joined, 4: title */
			__( '[NT Alerts] %1$s — %2$s on %3$s: %4$s', 'nt-alerts' ),
			$severity_label ? $severity_label : __( 'Alert', 'nt-alerts' ),
			$cat_label ? $cat_label : __( 'Service alert', 'nt-alerts' ),
			! empty( $alert['routes'] ) ? implode( ', ', $alert['routes'] ) : __( '(no routes)', 'nt-alerts' ),
			$alert['title']
		);

		$lines = array();
		$lines[] = sprintf(
			/* translators: %s: author display name */
			__( 'A new service alert was posted by %s.', 'nt-alerts' ),
			$author && $author->display_name ? $author->display_name : __( 'an unknown user', 'nt-alerts' )
		);
		$lines[] = '';
		$lines[] = $alert['title'];
		$lines[] = str_repeat( '—', max( 8, min( 60, strlen( $alert['title'] ) ) ) );
		if ( $cat_label ) {
			$lines[] = __( 'Category:', 'nt-alerts' ) . ' ' . $cat_label;
		}
		if ( $severity_label ) {
			$lines[] = __( 'Severity:', 'nt-alerts' ) . ' ' . $severity_label;
		}
		if ( $reason_label ) {
			$lines[] = __( 'Reason:', 'nt-alerts' ) . ' ' . $reason_label;
		}
		if ( ! empty( $alert['routes'] ) ) {
			$lines[] = __( 'Routes:', 'nt-alerts' ) . ' ' . implode( ', ', $alert['routes'] );
		}
		if ( ! empty( $alert['posted_at'] ) ) {
			$lines[] = __( 'Posted:', 'nt-alerts' ) . ' ' . self::friendly_time( $alert['posted_at'] );
		}
		if ( ! empty( $alert['expires_at'] ) ) {
			$lines[] = __( 'Expires:', 'nt-alerts' ) . ' ' . self::friendly_time( $alert['expires_at'] );
		} elseif ( isset( $alert['alert_type'] ) && 'long_term' === $alert['alert_type'] ) {
			$lines[] = __( 'Expires:', 'nt-alerts' ) . ' ' . __( 'no scheduled expiry', 'nt-alerts' );
		}

		if ( ! empty( $alert['description'] ) ) {
			$lines[] = '';
			$lines[] = __( 'Description:', 'nt-alerts' );
			$lines[] = $alert['description'];
		}

		$append_bullets = function ( $heading, $list ) use ( &$lines ) {
			if ( empty( $list ) ) {
				return;
			}
			$lines[] = '';
			$lines[] = $heading;
			foreach ( $list as $s ) {
				$name = isset( $s['name'] ) && '' !== $s['name'] ? $s['name'] : ( isset( $s['id'] ) ? $s['id'] : '' );
				if ( '' !== $name ) {
					$lines[] = '  • ' . $name;
				}
			}
		};

		if ( ! empty( $alert['closed_stops'] ) ) {
			$append_bullets( __( 'Closed stops:', 'nt-alerts' ), $alert['closed_stops'] );
		}
		if ( ! empty( $alert['alternate_stops'] ) ) {
			$append_bullets( __( 'Use these stops instead:', 'nt-alerts' ), $alert['alternate_stops'] );
		}

		$dept_key = isset( $alert['dept_responsible'] ) ? $alert['dept_responsible'] : '';
		$veh      = isset( $alert['vehicle_number'] )   ? $alert['vehicle_number']   : '';
		$ir_key   = isset( $alert['internal_reason'] )  ? $alert['internal_reason']  : '';

		if ( $dept_key || $veh || $ir_key ) {
			$lines[] = '';
			$lines[] = __( '— Internal —', 'nt-alerts' );
			if ( $dept_key ) {
				$dept_label = isset( $dept_labels[ $dept_key ] ) ? $dept_labels[ $dept_key ] : ucfirst( $dept_key );
				$lines[] = __( 'Department:', 'nt-alerts' ) . ' ' . $dept_label;
			}
			if ( $veh ) {
				$lines[] = __( 'Vehicle #:', 'nt-alerts' ) . ' ' . $veh;
			}
			if ( $ir_key ) {
				$ir_label = isset( $internal_labels[ $ir_key ] ) ? $internal_labels[ $ir_key ] : ucfirst( str_replace( '_', ' ', $ir_key ) );
				$lines[] = __( 'Maintenance reason:', 'nt-alerts' ) . ' ' . $ir_label;
			}
		}

		$lines[] = '';
		$lines[] = __( 'View on the supervisor dashboard:', 'nt-alerts' );
		$lines[] = $dashboard;
		$lines[] = '';
		$lines[] = __( '— NT Service Alerts', 'nt-alerts' );

		return array(
			'subject' => $subject,
			'body'    => implode( "\n", $lines ),
		);
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
