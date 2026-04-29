<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Archive {

	const PAGE_SLUG       = 'nt-alerts-archive';
	const PER_PAGE        = 25;
	const EXPORT_MAX_ROWS = 5000;

	public static function register_hooks() {
		add_action( 'admin_post_nt_alerts_export_csv', array( __CLASS__, 'handle_export_csv' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'view_nt_alerts_archive' ) ) {
			wp_die( esc_html__( 'You do not have permission to view the archive.', 'nt-alerts' ), 403 );
		}

		$filters = self::parse_filters();
		$page    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );

		$query_args = self::build_query_args( $filters, $page );
		$query      = new WP_Query( $query_args );

		$rows = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$post = get_post();
			$rows[] = array(
				'post'  => $post,
				'alert' => NT_Alerts_Alert::to_admin_array( $post ),
				'author_name' => get_the_author_meta( 'display_name', $post->post_author ),
				'edit_link'   => get_edit_post_link( $post->ID, '' ),
			);
		}
		wp_reset_postdata();

		$nt_archive = array(
			'rows'         => $rows,
			'filters'      => $filters,
			'total'        => (int) $query->found_posts,
			'pages'        => (int) $query->max_num_pages,
			'current_page' => $page,
			'page_url'     => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			'authors'      => self::author_options(),
			'categories'   => NT_Alerts_CPT::CATEGORIES,
		);

		include NT_ALERTS_PLUGIN_DIR . 'admin/views/archive.php';
	}

	/* -----------------------------------------------------------------
	 * Filters & query
	 * ----------------------------------------------------------------- */

	private static function parse_filters() {
		$g = wp_unslash( $_GET );

		$status   = isset( $g['status'] )   ? sanitize_key( $g['status'] )   : 'archived';
		$category = isset( $g['category'] ) ? sanitize_key( $g['category'] ) : '';
		$author   = isset( $g['author'] )   ? (int) $g['author']             : 0;
		$search   = isset( $g['s'] )        ? sanitize_text_field( $g['s'] ) : '';
		$from     = isset( $g['from'] )     ? sanitize_text_field( $g['from'] ) : '';
		$to       = isset( $g['to'] )       ? sanitize_text_field( $g['to'] )   : '';

		$valid_statuses = array( 'expired', 'archived', 'any', 'active' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$status = 'archived';
		}

		return compact( 'status', 'category', 'author', 'search', 'from', 'to' );
	}

	private static function build_query_args( $filters, $page ) {
		$args = array(
			'post_type'      => NT_ALERTS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $filters['search'] ) ) {
			$args['s'] = $filters['search'];
		}
		if ( ! empty( $filters['author'] ) ) {
			$args['author'] = (int) $filters['author'];
		}

		$meta_query = array( 'relation' => 'AND' );

		if ( 'any' !== $filters['status'] ) {
			$meta_query[] = array(
				'key'     => 'status',
				'value'   => $filters['status'],
				'compare' => '=',
			);
		}
		if ( ! empty( $filters['category'] ) ) {
			$meta_query[] = array(
				'key'     => 'category',
				'value'   => $filters['category'],
				'compare' => '=',
			);
		}

		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}

		// Date range applies to post_date (when the alert was posted).
		$date_query = array();
		if ( ! empty( $filters['from'] ) ) {
			$date_query['after'] = $filters['from'];
		}
		if ( ! empty( $filters['to'] ) ) {
			$date_query['before'] = $filters['to'] . ' 23:59:59';
		}
		if ( ! empty( $date_query ) ) {
			$date_query['inclusive'] = true;
			$args['date_query'] = array( $date_query );
		}

		return $args;
	}

	/* -----------------------------------------------------------------
	 * CSV export
	 * ----------------------------------------------------------------- */

	public static function handle_export_csv() {
		if ( ! current_user_can( 'view_nt_alerts_archive' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'nt-alerts' ), 403 );
		}
		check_admin_referer( 'nt_alerts_export_csv' );

		$filters = self::parse_filters();
		$range   = isset( $_REQUEST['range'] ) ? sanitize_key( wp_unslash( $_REQUEST['range'] ) ) : '';
		if ( $range ) {
			$bounds = self::range_bounds( $range );
			if ( $bounds ) {
				$filters['from'] = $bounds['from'];
				$filters['to']   = $bounds['to'];
				// Status defaults to "any" for date-range exports — managers
				// usually want every alert in the period regardless of state.
				if ( 'archived' === $filters['status'] ) {
					$filters['status'] = 'any';
				}
			}
		}

		$args = self::build_query_args( $filters, 1 );
		$args['posts_per_page'] = self::EXPORT_MAX_ROWS;
		$args['paged']          = 1;

		$query = new WP_Query( $args );

		$category_labels = NT_Alerts_Admin::category_labels();
		$reason_labels   = NT_Alerts_Admin::reason_labels();
		$dept_labels     = NT_Alerts_Admin::department_labels();
		$ireason_labels  = NT_Alerts_Admin::internal_reason_labels();
		$status_labels   = array(
			'active'   => __( 'Active', 'nt-alerts' ),
			'expired'  => __( 'Expired', 'nt-alerts' ),
			'archived' => __( 'Archived', 'nt-alerts' ),
		);

		$filename = sprintf( 'nt-alerts-export-%s.csv', wp_date( 'Y-m-d-His' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM so Excel recognizes accented characters in stop names.
		fwrite( $out, "\xEF\xBB\xBF" );

		fputcsv( $out, array(
			'ID',
			'Posted at',
			'Posted by',
			'Status',
			'Type',
			'Category',
			'Reason',
			'Severity',
			'Title',
			'Description',
			'Routes',
			'Closed stops',
			'Alternate stops',
			'Start time',
			'End time',
			'Last updated',
			'Department',
			'Vehicle #',
			'Maintenance reason',
		) );

		while ( $query->have_posts() ) {
			$query->the_post();
			$post  = get_post();
			$alert = NT_Alerts_Alert::to_admin_array( $post );

			$status_key = (string) get_post_meta( $post->ID, 'status', true );

			$cat_key    = isset( $alert['category'] ) ? $alert['category'] : '';
			$reason_key = isset( $alert['reason'] ) ? $alert['reason'] : '';
			$dept_key   = isset( $alert['dept_responsible'] ) ? $alert['dept_responsible'] : '';
			$ir_key     = isset( $alert['internal_reason'] ) ? $alert['internal_reason'] : '';

			$stop_names = function ( $list ) {
				if ( empty( $list ) || ! is_array( $list ) ) {
					return '';
				}
				return implode( '; ', array_map( function ( $s ) {
					return isset( $s['name'] ) && '' !== $s['name'] ? $s['name'] : ( isset( $s['id'] ) ? $s['id'] : '' );
				}, $list ) );
			};

			fputcsv( $out, array(
				(int) $post->ID,
				self::csv_friendly_time( $alert['posted_at'] ),
				get_the_author_meta( 'display_name', $post->post_author ),
				isset( $status_labels[ $status_key ] ) ? $status_labels[ $status_key ] : ucfirst( $status_key ),
				isset( $alert['alert_type'] ) ? $alert['alert_type'] : '',
				isset( $category_labels[ $cat_key ] ) ? $category_labels[ $cat_key ] : $cat_key,
				$reason_key && isset( $reason_labels[ $reason_key ] ) ? $reason_labels[ $reason_key ] : '',
				isset( $alert['severity'] ) ? ucfirst( $alert['severity'] ) : '',
				$alert['title'],
				isset( $alert['description'] ) ? $alert['description'] : '',
				isset( $alert['routes'] ) && is_array( $alert['routes'] ) ? implode( '; ', $alert['routes'] ) : '',
				$stop_names( isset( $alert['closed_stops'] ) ? $alert['closed_stops'] : array() ),
				$stop_names( isset( $alert['alternate_stops'] ) ? $alert['alternate_stops'] : array() ),
				self::csv_friendly_time( isset( $alert['start_time'] ) ? $alert['start_time'] : '' ),
				self::csv_friendly_time( isset( $alert['expires_at'] ) ? $alert['expires_at'] : '' ),
				self::csv_friendly_time( isset( $alert['last_updated'] ) ? $alert['last_updated'] : '' ),
				$dept_key && isset( $dept_labels[ $dept_key ] ) ? $dept_labels[ $dept_key ] : '',
				isset( $alert['vehicle_number'] ) ? $alert['vehicle_number'] : '',
				$ir_key && isset( $ireason_labels[ $ir_key ] ) ? $ireason_labels[ $ir_key ] : '',
			) );
		}

		wp_reset_postdata();
		fclose( $out );
		exit;
	}

	private static function csv_friendly_time( $iso ) {
		if ( ! is_string( $iso ) || '' === $iso ) {
			return '';
		}
		$ts = strtotime( $iso );
		if ( false === $ts ) {
			return $iso;
		}
		return wp_date( 'Y-m-d H:i', $ts );
	}

	public static function range_bounds( $range ) {
		try {
			$tz  = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			$now = new DateTimeImmutable( 'now', $tz );
		} catch ( Exception $e ) {
			return null;
		}

		switch ( $range ) {
			case 'week':
				$start = $now->modify( 'monday this week' )->setTime( 0, 0, 0 );
				$end   = $start->modify( '+6 days' );
				break;
			case 'last_week':
				$start = $now->modify( 'monday last week' )->setTime( 0, 0, 0 );
				$end   = $start->modify( '+6 days' );
				break;
			case 'month':
				$start = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
				$end   = $start->modify( 'last day of this month' );
				break;
			case 'last_month':
				$start = $now->modify( 'first day of last month' )->setTime( 0, 0, 0 );
				$end   = $start->modify( 'last day of last month' );
				break;
			default:
				return null;
		}

		return array(
			'from' => $start->format( 'Y-m-d' ),
			'to'   => $end->format( 'Y-m-d' ),
		);
	}

	private static function author_options() {
		$users = get_users( array(
			'role__in' => array( NT_Alerts_Roles::ROLE_SUPERVISOR, NT_Alerts_Roles::ROLE_MANAGER, 'administrator' ),
			'fields'   => array( 'ID', 'display_name' ),
			'orderby'  => 'display_name',
			'number'   => 200,
		) );
		$out = array();
		foreach ( $users as $u ) {
			$out[ (int) $u->ID ] = $u->display_name;
		}
		return $out;
	}
}
