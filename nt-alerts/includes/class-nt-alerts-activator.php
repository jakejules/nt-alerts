<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Activator {

	public static function activate() {
		self::ensure_role_and_caps();
		self::seed_options();

		if ( class_exists( 'NT_Alerts_Cron' ) ) {
			NT_Alerts_Cron::schedule_all();
		}

		flush_rewrite_rules();
	}

	public static function deactivate() {
		if ( class_exists( 'NT_Alerts_Cron' ) ) {
			NT_Alerts_Cron::unschedule_all();
		}
		flush_rewrite_rules();
	}

	private static function ensure_role_and_caps() {
		$supervisor = get_role( NT_Alerts_Roles::ROLE_SUPERVISOR );
		if ( ! $supervisor ) {
			add_role(
				NT_Alerts_Roles::ROLE_SUPERVISOR,
				__( 'Alert Supervisor', 'nt-alerts' ),
				NT_Alerts_Roles::get_supervisor_caps()
			);
		} else {
			// Patch existing role with any new caps that were added in plugin
			// upgrades (e.g. upload_files added in 1.4.1 for image uploads).
			foreach ( NT_Alerts_Roles::get_supervisor_caps() as $cap => $grant ) {
				if ( $grant && ! $supervisor->has_cap( $cap ) ) {
					$supervisor->add_cap( $cap );
				}
			}
		}

		$manager = get_role( NT_Alerts_Roles::ROLE_MANAGER );
		if ( ! $manager ) {
			add_role(
				NT_Alerts_Roles::ROLE_MANAGER,
				__( 'Alert Manager', 'nt-alerts' ),
				NT_Alerts_Roles::get_manager_caps()
			);
		} else {
			foreach ( NT_Alerts_Roles::get_manager_caps() as $cap => $grant ) {
				if ( $grant && ! $manager->has_cap( $cap ) ) {
					$manager->add_cap( $cap );
				}
			}
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( NT_Alerts_Roles::get_admin_extra_caps() as $cap ) {
				if ( ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
			// Admins also need the full supervisor cap set so map_meta_cap
			// resolves edit_post, publish_post, etc. on nt_alert objects.
			foreach ( NT_Alerts_Roles::get_supervisor_caps() as $cap => $grant ) {
				if ( $grant && ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}
	}

	private static function seed_options() {
		add_option( 'nt_alerts_version',            NT_ALERTS_VERSION );
		add_option( 'nt_alerts_routes',             self::default_routes() );
		add_option( 'nt_alerts_stops',              self::default_stops() );
		add_option( 'nt_alerts_cors_origins',       array() );
		add_option( 'nt_alerts_cache_ttl',          NT_ALERTS_CACHE_TTL_DEFAULT );
		add_option( 'nt_alerts_default_durations',  self::default_category_durations() );
		add_option( 'nt_alerts_embed_version',      NT_ALERTS_VERSION );
		add_option( 'nt_alerts_archive_after_days', 30 );
		add_option( 'nt_alerts_new_alert_notify',   array() );
	}

	public static function default_stops() {
		$file = NT_ALERTS_PLUGIN_DIR . 'data/stops-default.php';
		if ( ! file_exists( $file ) ) {
			return array();
		}
		$stops = include $file;
		return is_array( $stops ) ? $stops : array();
	}

	public static function default_category_durations() {
		return array(
			'detour'          => '2h',
			'delay'           => '2h',
			'cancelled_trip'  => '2h',
			'stop_closure'    => '4h',
			'reduced_service' => 'rest_of_day',
			'other'           => '2h',
		);
	}

	/**
	 * Default route catalogue, parsed from the Niagara Transit GTFS routes.txt.
	 * Grouped by service area for the supervisor multi-select UI.
	 */
	public static function default_routes() {
		return array(
			array(
				'group'  => __( 'St. Catharines / Thorold', 'nt-alerts' ),
				'routes' => array(
					array( 'id' => '301', 'label' => 'Route 301 — Hospital',                  'color' => '#ed171f' ),
					array( 'id' => '302', 'label' => 'Route 302 — Ontario St.',               'color' => '#04a559' ),
					array( 'id' => '303', 'label' => 'Route 303 — Pelham Rd.',                'color' => '#943734' ),
					array( 'id' => '304', 'label' => 'Route 304 — Oakdale Av. / Pen Centre',  'color' => '#e46c0a' ),
					array( 'id' => '305', 'label' => 'Route 305 — Haig St. / Linwell Rd.',    'color' => '#604a7b' ),
					array( 'id' => '306', 'label' => 'Route 306 — Lake St.',                  'color' => '#ed171f' ),
					array( 'id' => '307', 'label' => 'Route 307 — Niagara St.',               'color' => '#376092' ),
					array( 'id' => '308', 'label' => 'Route 308 — Grantham Av.',              'color' => '#428b96' ),
					array( 'id' => '309', 'label' => 'Route 309 — Geneva St.',                'color' => '#519177' ),
					array( 'id' => '310', 'label' => 'Route 310 — Glenridge Av. / Pen Centre','color' => '#524d59' ),
					array( 'id' => '311', 'label' => 'Route 311 — Hartzel Rd.',               'color' => '#376092' ),
					array( 'id' => '312', 'label' => 'Route 312 — Vine St.',                  'color' => '#04a559' ),
					array( 'id' => '314', 'label' => 'Route 314 — Scott St.',                 'color' => '#bd4a5b' ),
					array( 'id' => '315', 'label' => 'Route 315 — West St. Catharines',       'color' => '#0e8343' ),
					array( 'id' => '316', 'label' => 'Route 316 — Brock / Glenridge',         'color' => '#ff0000' ),
					array( 'id' => '317', 'label' => 'Route 317 — Bunting Rd.',               'color' => '#604a7b' ),
					array( 'id' => '318', 'label' => 'Route 318 — Secord Woods',              'color' => '#0e8343' ),
					array( 'id' => '320', 'label' => 'Route 320 — Thorold',                   'color' => '#534ca3' ),
					array( 'id' => '321', 'label' => 'Route 321 — Confederation',             'color' => '#519177' ),
					array( 'id' => '322', 'label' => 'Route 322 — Thorold South',             'color' => '#c0504d' ),
					array( 'id' => '324', 'label' => 'Route 324 — Brock / Tupper',            'color' => '#0070c0' ),
					array( 'id' => '331', 'label' => 'Route 331 — Brock / Richmond',          'color' => '#04a559' ),
					array( 'id' => '335', 'label' => 'Route 335 — Pen / Brock',               'color' => '#519177' ),
					array( 'id' => '336', 'label' => 'Route 336 — Pen / Glendale / Brock',    'color' => '#e73d2a' ),
					array( 'id' => '337', 'label' => 'Route 337 — Crosstown',                 'color' => '#e46c0a' ),
					array( 'id' => '401', 'label' => 'Route 401 — Hospital (evening)',        'color' => '#e02626' ),
					array( 'id' => '402', 'label' => 'Route 402 — Ontario St. (evening)',     'color' => '#04a559' ),
					array( 'id' => '404', 'label' => 'Route 404 — Oakdale Av. (evening)',     'color' => '#e46c0a' ),
					array( 'id' => '406', 'label' => 'Route 406 — Lake St. (evening)',        'color' => '#e02626' ),
					array( 'id' => '408', 'label' => 'Route 408 — Grantham Av. (evening)',    'color' => '#519177' ),
					array( 'id' => '409', 'label' => 'Route 409 — Geneva St. (evening)',      'color' => '#519177' ),
					array( 'id' => '410', 'label' => 'Route 410 — Glenridge / Pen (evening)', 'color' => '#524d59' ),
					array( 'id' => '412', 'label' => 'Route 412 — Vine St. (evening)',        'color' => '#524d59' ),
					array( 'id' => '414', 'label' => 'Route 414 — Scott St. (evening)',       'color' => '#bd4a5b' ),
					array( 'id' => '415', 'label' => 'Route 415 — West St. Catharines (evening)', 'color' => '#0e8343' ),
					array( 'id' => '416', 'label' => 'Route 416 — Brock / Glenridge (evening)',   'color' => '#ff0000' ),
					array( 'id' => '417', 'label' => 'Route 417 — Bunting Rd. (evening)',     'color' => '#604a7b' ),
					array( 'id' => '418', 'label' => 'Route 418 — Secord Woods (evening)',    'color' => '#0e8343' ),
					array( 'id' => '420', 'label' => 'Route 420 — Thorold (evening)',         'color' => '#524d59' ),
					array( 'id' => '421', 'label' => 'Route 421 — Confederation (evening)',   'color' => '#519177' ),
					array( 'id' => '424', 'label' => 'Route 424 — Brock / Tupper (evening)',  'color' => '#0070c0' ),
					array( 'id' => '431', 'label' => 'Route 431 — Brock / Richmond (evening)','color' => '#04a559' ),
					array( 'id' => '435', 'label' => 'Route 435 — Pen / Brock (evening)',     'color' => '#519177' ),
					array( 'id' => '437', 'label' => 'Route 437 — Crosstown (evening)',       'color' => '#e46c0a' ),
				),
			),

			array(
				'group'  => __( 'Niagara Falls', 'nt-alerts' ),
				'routes' => array(
					array( 'id' => '101', 'label' => 'Route 101 — Dunn St.',                         'color' => '#feb811' ),
					array( 'id' => '102', 'label' => 'Route 102 — Hospital',                         'color' => '#8882bd' ),
					array( 'id' => '103', 'label' => 'Route 103 — Drummond Rd. / Oldfield Rd.',      'color' => '#f599c1' ),
					array( 'id' => '104', 'label' => 'Route 104 — Victoria Av.',                     'color' => '#77caeb' ),
					array( 'id' => '105', 'label' => 'Route 105 — Kalar Rd.',                        'color' => '#eb9073' ),
					array( 'id' => '106', 'label' => 'Route 106 — Stanley Av. / Ind. Park / Chippawa','color' => '#fff34c' ),
					array( 'id' => '107', 'label' => "Route 107 — Town & Country / Church's Ln",    'color' => '#cda793' ),
					array( 'id' => '108', 'label' => 'Route 108 — Thorold Stone Rd. / Dorchester',   'color' => '#04a559' ),
					array( 'id' => '109', 'label' => 'Route 109 — Thorold Stone Rd. / Mt. Carmel',   'color' => '#bdd732' ),
					array( 'id' => '110', 'label' => 'Route 110 — Drummond Rd.',                     'color' => '#97afdb' ),
					array( 'id' => '111', 'label' => 'Route 111 — Dorchester Rd.',                   'color' => '#fbb19b' ),
					array( 'id' => '112', 'label' => 'Route 112 — McLeod Rd. / Chippawa',            'color' => '#dfb7d9' ),
					array( 'id' => '113', 'label' => 'Route 113 — Montrose Rd. / Heartland Forest',  'color' => '#b79785' ),
					array( 'id' => '114', 'label' => 'Route 114 — Thorold Stone Rd. / Town & Country','color' => '#d593c1' ),
					array( 'id' => '115', 'label' => 'Route 115 — Morrison St. / Downtown',          'color' => '#000000' ),
					array( 'id' => '116', 'label' => "Route 116 — Lundy's Ln.",                      'color' => '#ff0000' ),
					array( 'id' => '203', 'label' => 'Route 203 — Dunn St. / Oldfield Rd. (evening)','color' => '#f79ac3' ),
					array( 'id' => '204', 'label' => 'Route 204 — Victoria Av. (evening)',           'color' => '#7dd3f7' ),
					array( 'id' => '205', 'label' => 'Route 205 — Kalar Rd. (evening)',              'color' => '#f79675' ),
					array( 'id' => '206', 'label' => 'Route 206 — Stanley Av. / Ind. Park / Chippawa (evening)', 'color' => '#fff34e' ),
					array( 'id' => '209', 'label' => 'Route 209 — Thorold Stone Rd. / Mt. Carmel (evening)',     'color' => '#bed734' ),
					array( 'id' => '210', 'label' => 'Route 210 — Drummond Rd. (evening)',           'color' => '#96aedb' ),
					array( 'id' => '211', 'label' => 'Route 211 — Dorchester Rd. (evening)',         'color' => '#feae9b' ),
					array( 'id' => '213', 'label' => 'Route 213 — Montrose / Mt Carmel / Heartland (evening)',   'color' => '#b69686' ),
					array( 'id' => '214', 'label' => "Route 214 — Dorchester Rd. / Church's Ln. (evening)",      'color' => '#d792be' ),
					array( 'id' => '216', 'label' => "Route 216 — Lundy's Ln. (evening)",            'color' => '#ff0000' ),
				),
			),

			array(
				'group'  => __( 'Welland', 'nt-alerts' ),
				'routes' => array(
					array( 'id' => '501', 'label' => 'Route 501 — Broadway',             'color' => '#ff0000' ),
					array( 'id' => '502', 'label' => 'Route 502 — Rice Road',            'color' => '#4a452a' ),
					array( 'id' => '503', 'label' => 'Route 503 — First Av',             'color' => '#93cddd' ),
					array( 'id' => '504', 'label' => 'Route 504 — Fitch St',             'color' => '#604a7b' ),
					array( 'id' => '505', 'label' => 'Route 505 — Lincoln / Wellington', 'color' => '#b3a2c7' ),
					array( 'id' => '506', 'label' => 'Route 506 — Ontario Rd',           'color' => '#93cddd' ),
					array( 'id' => '508', 'label' => 'Route 508 — Woodlawn',             'color' => '#943734' ),
					array( 'id' => '509', 'label' => 'Route 509 — Niagara St',           'color' => '#548ed5' ),
				),
			),

			array(
				'group'  => __( 'Regional & Campus Links', 'nt-alerts' ),
				'routes' => array(
					array( 'id' => '22',  'label' => 'Route 22 — Fort Erie Link',                              'color' => '#db5e2c' ),
					array( 'id' => '25',  'label' => 'Route 25 — Port Colborne Link',                         'color' => '#ffffff' ),
					array( 'id' => '34',  'label' => 'Route 34 — Niagara College Campus Link',                'color' => '#943734' ),
					array( 'id' => '40',  'label' => 'Route 40 — Niagara College NOTL Campus',                'color' => '#4f809e' ),
					array( 'id' => '45',  'label' => 'Route 45 — Niagara College NOTL Campus',                'color' => '#4f809e' ),
					array( 'id' => '50',  'label' => 'Route 50 — Brock University / St. Catharines',          'color' => '#e46c0a' ),
					array( 'id' => '55',  'label' => 'Route 55 — Brock University / Niagara Falls',           'color' => '#e46c0a' ),
					array( 'id' => '60',  'label' => 'Route 60 — Niagara College / Welland Campus',           'color' => '#8c73a6' ),
					array( 'id' => '65',  'label' => 'Route 65 — Niagara Falls',                              'color' => '#8c73a6' ),
					array( 'id' => '70',  'label' => 'Route 70 — Brock / Niagara College Welland',            'color' => '#00a3ae' ),
					array( 'id' => '75',  'label' => 'Route 75 — Brock University / St. Catharines',          'color' => '#00a3ae' ),
					array( 'id' => '438', 'label' => 'Route 438 — GO Train Station Connection',               'color' => '#4e6128' ),
					array( 'id' => '751', 'label' => 'Route 751 — Fort Erie Community Bus',                   'color' => '#007a56' ),
				),
			),
		);
	}
}
