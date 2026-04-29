<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Alerts_Roles {

	const ROLE_SUPERVISOR = 'nt_alert_supervisor';
	const ROLE_MANAGER    = 'nt_alert_manager';

	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'ensure_roles' ), 9 );
	}

	public static function ensure_roles() {
		if ( ! get_role( self::ROLE_SUPERVISOR ) ) {
			add_role(
				self::ROLE_SUPERVISOR,
				__( 'Alert Supervisor', 'nt-alerts' ),
				self::get_supervisor_caps()
			);
		}
		if ( ! get_role( self::ROLE_MANAGER ) ) {
			add_role(
				self::ROLE_MANAGER,
				__( 'Alert Manager', 'nt-alerts' ),
				self::get_manager_caps()
			);
		}
	}

	public static function get_supervisor_caps() {
		return array(
			'read'                         => true,
			'upload_files'                 => true,

			'edit_nt_alert'                => true,
			'edit_nt_alerts'               => true,
			'edit_others_nt_alerts'        => true,
			'edit_published_nt_alerts'     => true,
			'publish_nt_alerts'            => true,

			'delete_nt_alert'              => true,
			'delete_nt_alerts'             => true,
			'delete_published_nt_alerts'   => true,

			'read_nt_alert'                => true,
			'read_private_nt_alerts'       => true,
		);
	}

	/**
	 * Manager caps = supervisor caps + everything in admin_extra_caps.
	 * Effectively: full plugin access without WordPress-wide admin powers.
	 */
	public static function get_manager_caps() {
		$caps = self::get_supervisor_caps();
		foreach ( self::get_admin_extra_caps() as $cap ) {
			$caps[ $cap ] = true;
		}
		return $caps;
	}

	public static function get_admin_extra_caps() {
		return array(
			'delete_others_nt_alerts',
			'manage_nt_alerts_settings',
			'view_nt_alerts_archive',
		);
	}
}
