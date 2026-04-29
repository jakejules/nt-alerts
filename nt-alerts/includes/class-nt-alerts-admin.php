<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Admin {

	const MENU_SLUG = 'nt-alerts';

	public static function register_hooks() {
		add_action( 'admin_menu',                 array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts',      array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init',                 array( __CLASS__, 'maybe_redirect_supervisor' ) );
		add_action( 'admin_menu',                 array( __CLASS__, 'trim_supervisor_menu' ), 999 );
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'trim_admin_bar' ) );
	}

	public static function register_global_hooks() {
		// Filters that must fire outside the admin context too
		// (login flow and front-end admin bar visibility).
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'show_admin_bar' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'NT Service Alerts', 'nt-alerts' ),
			__( 'NT Service Alerts', 'nt-alerts' ),
			'edit_nt_alerts',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-warning',
			30
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'nt-alerts' ),
			__( 'Dashboard', 'nt-alerts' ),
			'edit_nt_alerts',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Service alerts archive', 'nt-alerts' ),
			__( 'Archive', 'nt-alerts' ),
			'view_nt_alerts_archive',
			NT_Alerts_Archive::PAGE_SLUG,
			array( 'NT_Alerts_Archive', 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Service Alerts settings', 'nt-alerts' ),
			__( 'Settings', 'nt-alerts' ),
			'manage_nt_alerts_settings',
			NT_Alerts_Settings::PAGE_SLUG,
			array( 'NT_Alerts_Settings', 'render_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( ! self::is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'nt-alerts-admin',
			NT_ALERTS_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			NT_ALERTS_VERSION
		);

		wp_enqueue_script(
			'nt-alerts-admin',
			NT_ALERTS_PLUGIN_URL . 'admin/js/admin.js',
			array(),
			NT_ALERTS_VERSION,
			true
		);

		wp_localize_script( 'nt-alerts-admin', 'ntAlertsAdmin', array(
			'restUrl'         => esc_url_raw( rest_url( NT_ALERTS_REST_NAMESPACE . '/' ) ),
			'dashboardUrl'    => esc_url_raw( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ),
			'newAlertUrl'     => esc_url_raw( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&action=new' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'categoryTitles'  => self::category_title_templates(),
			'defaultDurations'=> get_option( 'nt_alerts_default_durations', NT_Alerts_Activator::default_category_durations() ),
			'categorySeverity' => array(
				'detour'          => 'warning',
				'delay'           => 'warning',
				'cancelled_trip'  => 'warning',
				'stop_closure'    => 'info',
				'reduced_service' => 'warning',
				'other'           => 'info',
			),
			'reasons'         => self::reason_labels(),
			'departments'     => self::department_labels(),
			'internalReasons' => self::internal_reason_labels(),
			'maxImages'      => NT_Alerts_CPT::MAX_IMAGES,
			'i18n'           => array(
				'confirmEnd'    => __( 'End this alert now?', 'nt-alerts' ),
				'pickImages'    => __( 'Choose images from your computer', 'nt-alerts' ),
				'replaceImages' => __( 'Add or replace images', 'nt-alerts' ),
				'tooManyImages' => __( 'Up to 3 images per alert.', 'nt-alerts' ),
				'removeImage'   => __( 'Remove image', 'nt-alerts' ),
				'uploading'     => __( 'Uploading…', 'nt-alerts' ),
				'uploaded'      => __( 'Uploaded.', 'nt-alerts' ),
				'uploadFailed'  => __( 'Upload failed:', 'nt-alerts' ),
				'endFailed'     => __( 'Could not end the alert. Please try again.', 'nt-alerts' ),
				'ended'         => __( 'Alert ended.', 'nt-alerts' ),
				'extendFailed'  => __( 'Could not extend the alert. Please try again.', 'nt-alerts' ),
				'submitting' => __( 'Posting…', 'nt-alerts' ),
				'saving'     => __( 'Saving…', 'nt-alerts' ),
				'submitFailed' => __( 'Could not post the alert. Please review the fields and try again.', 'nt-alerts' ),
				'editFailed' => __( 'Could not save changes. Please try again.', 'nt-alerts' ),
				'postAnother'  => __( 'Post another', 'nt-alerts' ),
				'backToDash'   => __( 'Back to dashboard', 'nt-alerts' ),
				'defaultTitleNoRoutes' => __( 'Select at least one route', 'nt-alerts' ),
				'routesRequired' => __( 'Pick at least one affected route.', 'nt-alerts' ),
				'categoryRequired' => __( 'Pick what happened.', 'nt-alerts' ),
				'expiryInPast' => __( 'Expiry must be in the future.', 'nt-alerts' ),
			),
		) );
	}

	public static function category_labels() {
		return array(
			'detour'          => __( 'Detour', 'nt-alerts' ),
			'delay'           => __( 'Delay', 'nt-alerts' ),
			'cancelled_trip'  => __( 'Cancelled trip', 'nt-alerts' ),
			'stop_closure'    => __( 'Stop closure', 'nt-alerts' ),
			'reduced_service' => __( 'Reduced service', 'nt-alerts' ),
			'other'           => __( 'Other', 'nt-alerts' ),
		);
	}

	public static function reason_labels() {
		return array(
			'construction'     => __( 'Construction', 'nt-alerts' ),
			'street_closure'   => __( 'Street closure', 'nt-alerts' ),
			'weather'          => __( 'Weather', 'nt-alerts' ),
			'maintenance'      => __( 'Maintenance', 'nt-alerts' ),
			'police_activity'  => __( 'Police activity', 'nt-alerts' ),
			'fire'             => __( 'Fire', 'nt-alerts' ),
			'evacuation'       => __( 'Evacuation', 'nt-alerts' ),
			'terminal_closure' => __( 'Terminal closure', 'nt-alerts' ),
			'collision'        => __( 'Collision', 'nt-alerts' ),
			'parade'           => __( 'Parade', 'nt-alerts' ),
			'other'            => __( 'Other', 'nt-alerts' ),
		);
	}

	public static function department_labels() {
		return array(
			'operations'  => __( 'Operations', 'nt-alerts' ),
			'maintenance' => __( 'Maintenance', 'nt-alerts' ),
		);
	}

	public static function internal_reason_labels() {
		return array(
			'change_off' => __( 'Change off', 'nt-alerts' ),
			'breakdown'  => __( 'Breakdown', 'nt-alerts' ),
			'accident'   => __( 'Accident', 'nt-alerts' ),
			'unsanitary' => __( 'Unsanitary', 'nt-alerts' ),
		);
	}

	public static function category_title_templates() {
		return array(
			'detour'          => __( 'Detour on {routes}', 'nt-alerts' ),
			'delay'           => __( 'Delays on {routes}', 'nt-alerts' ),
			'cancelled_trip'  => __( 'Cancelled trip on {routes}', 'nt-alerts' ),
			'stop_closure'    => __( 'Stop closure affecting {routes}', 'nt-alerts' ),
			'reduced_service' => __( 'Reduced service on {routes}', 'nt-alerts' ),
			'other'           => '',
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'edit_nt_alerts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'nt-alerts' ), 403 );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'new' === $action ) {
			if ( ! current_user_can( 'publish_nt_alerts' ) ) {
				wp_die( esc_html__( 'You do not have permission to post alerts.', 'nt-alerts' ), 403 );
			}
			$nt_alerts_routes = self::get_route_catalogue();
			$nt_alerts_editing = null;
			include NT_ALERTS_PLUGIN_DIR . 'admin/views/new-alert.php';
			return;
		}

		if ( 'edit' === $action ) {
			$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
			if ( $id <= 0 || NT_ALERTS_CPT !== get_post_type( $id ) ) {
				wp_die( esc_html__( 'Alert not found.', 'nt-alerts' ), 404 );
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				wp_die( esc_html__( 'You do not have permission to edit this alert.', 'nt-alerts' ), 403 );
			}
			$nt_alerts_routes  = self::get_route_catalogue();
			$nt_alerts_editing = NT_Alerts_Alert::to_admin_array( get_post( $id ) );
			$nt_alerts_editing['id'] = $id;
			include NT_ALERTS_PLUGIN_DIR . 'admin/views/new-alert.php';
			return;
		}

		$data = self::build_dashboard_data();

		$nt_alerts_data = $data;
		include NT_ALERTS_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public static function get_route_catalogue() {
		$groups = get_option( 'nt_alerts_routes', array() );
		if ( ! is_array( $groups ) ) {
			$groups = array();
		}
		return $groups;
	}

	public static function build_dashboard_data() {
		$now_ts = time();

		$active = self::query_alerts( array(
			'status'      => 'active',
			'alert_type'  => 'short_term',
			'author'      => self::current_author_filter(),
		) );

		$ongoing = self::query_alerts( array(
			'status'      => 'active',
			'alert_type'  => 'long_term',
			'author'      => self::current_author_filter(),
		) );

		$ended_today = self::query_ended_today( $now_ts );

		return array(
			'active'                 => array_map( array( 'NT_Alerts_Alert', 'to_admin_array' ), $active ),
			'ongoing'                => array_map( array( 'NT_Alerts_Alert', 'to_admin_array' ), $ongoing ),
			'ended_today'            => array_map( array( 'NT_Alerts_Alert', 'to_admin_array' ), $ended_today ),
			'can_see_all'            => current_user_can( 'edit_others_nt_alerts' ),
			'reason_labels'          => self::reason_labels(),
			'dept_labels'            => self::department_labels(),
			'internal_reason_labels' => self::internal_reason_labels(),
		);
	}

	private static function current_author_filter() {
		// Admins see all; supervisors see only their own.
		return current_user_can( 'edit_others_nt_alerts' ) ? 0 : get_current_user_id();
	}

	private static function query_alerts( $args ) {
		$query_args = array(
			'post_type'        => NT_ALERTS_CPT,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
			'meta_query'       => array(
				array(
					'key'     => 'status',
					'value'   => $args['status'],
					'compare' => '=',
				),
				array(
					'key'     => 'alert_type',
					'value'   => $args['alert_type'],
					'compare' => '=',
				),
			),
		);

		if ( ! empty( $args['author'] ) ) {
			$query_args['author'] = (int) $args['author'];
		}

		return get_posts( $query_args );
	}

	private static function query_ended_today( $now_ts ) {
		$start = strtotime( 'today 00:00:00' );
		$end   = strtotime( 'tomorrow 00:00:00' );

		if ( ! $start || ! $end ) {
			return array();
		}

		$start_iso = gmdate( 'c', $start );
		$end_iso   = gmdate( 'c', $end );

		$query_args = array(
			'post_type'        => NT_ALERTS_CPT,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
			'meta_query'       => array(
				'relation' => 'AND',
				array(
					'key'     => 'status',
					'value'   => 'expired',
					'compare' => '=',
				),
				array(
					'key'     => 'end_time',
					'value'   => array( $start_iso, $end_iso ),
					'compare' => 'BETWEEN',
					'type'    => 'CHAR',
				),
			),
		);

		$author = self::current_author_filter();
		if ( ! empty( $author ) ) {
			$query_args['author'] = (int) $author;
		}

		return get_posts( $query_args );
	}

	/* -----------------------------------------------------------------
	 * Alerts-only UX trimming (supervisors and managers)
	 * ----------------------------------------------------------------- */

	/**
	 * True for users whose only role in this site is Alert Supervisor or
	 * Alert Manager — i.e., the people who should land on the dashboard
	 * instead of the WP index and have the WP-native chrome trimmed.
	 */
	public static function is_alerts_only_user() {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}
		$roles = (array) $user->roles;
		return in_array( NT_Alerts_Roles::ROLE_SUPERVISOR, $roles, true )
			|| in_array( NT_Alerts_Roles::ROLE_MANAGER, $roles, true );
	}

	public static function maybe_redirect_supervisor() {
		if ( ! self::is_alerts_only_user() || wp_doing_ajax() ) {
			return;
		}

		global $pagenow;
		// Land them on the alerts dashboard instead of the WP index.
		if ( 'index.php' === $pagenow ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
			exit;
		}
	}

	public static function trim_supervisor_menu() {
		if ( ! self::is_alerts_only_user() ) {
			return;
		}
		// upload.php is hidden — image uploads on the alert form still work
		// because they use the upload_files cap directly, not the menu link.
		foreach ( array( 'index.php', 'tools.php', 'edit-comments.php', 'upload.php' ) as $slug ) {
			remove_menu_page( $slug );
		}
	}

	public static function trim_admin_bar() {
		if ( ! self::is_alerts_only_user() ) {
			return;
		}
		global $wp_admin_bar;
		if ( ! $wp_admin_bar ) {
			return;
		}
		foreach ( array( 'wp-logo', 'comments', 'new-content', 'updates', 'site-name' ) as $node ) {
			$wp_admin_bar->remove_node( $node );
		}
	}

	public static function show_admin_bar( $show ) {
		// Hide the admin bar on the front-end for supervisors / managers; keep
		// it in /wp-admin/ so they retain the log-out control.
		if ( is_admin() ) {
			return $show;
		}
		if ( self::is_alerts_only_user() ) {
			return false;
		}
		return $show;
	}

	public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		unset( $requested_redirect_to );
		if ( ! ( $user instanceof WP_User ) ) {
			return $redirect_to;
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return $redirect_to;
		}
		$roles = (array) $user->roles;
		if ( in_array( NT_Alerts_Roles::ROLE_SUPERVISOR, $roles, true )
			|| in_array( NT_Alerts_Roles::ROLE_MANAGER, $roles, true ) ) {
			return admin_url( 'admin.php?page=' . self::MENU_SLUG );
		}
		return $redirect_to;
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------- */

	private static function is_plugin_screen( $hook ) {
		$hook = (string) $hook;
		return 'toplevel_page_' . self::MENU_SLUG === $hook
			|| false !== strpos( $hook, '_page_' . NT_Alerts_Settings::PAGE_SLUG )
			|| false !== strpos( $hook, '_page_' . NT_Alerts_Archive::PAGE_SLUG );
	}

	private static function render_placeholder( $message ) {
		echo '<div class="wrap nt-alerts-wrap"><h1>' . esc_html__( 'Service Alerts', 'nt-alerts' ) . '</h1>';
		echo '<p class="nt-alerts-placeholder">' . esc_html( $message ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '">' . esc_html__( '← Back to dashboard', 'nt-alerts' ) . '</a></p>';
		echo '</div>';
	}
}
