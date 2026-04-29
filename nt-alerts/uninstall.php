<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$alert_ids = get_posts( array(
	'post_type'      => 'nt_alert',
	'post_status'    => 'any',
	'numberposts'    => -1,
	'fields'         => 'ids',
	'suppress_filters' => true,
) );

foreach ( $alert_ids as $id ) {
	wp_delete_post( $id, true );
}

delete_option( 'nt_alerts_version' );
delete_option( 'nt_alerts_routes' );
delete_option( 'nt_alerts_stops' );
delete_option( 'nt_alerts_cors_origins' );
delete_option( 'nt_alerts_cache_ttl' );
delete_option( 'nt_alerts_default_durations' );
delete_option( 'nt_alerts_embed_version' );
delete_option( 'nt_alerts_archive_after_days' );
delete_option( 'nt_alerts_new_alert_notify' );

$admin_extra_caps = array(
	'edit_others_nt_alerts',
	'delete_others_nt_alerts',
	'read_private_nt_alerts',
	'manage_nt_alerts_settings',
	'view_nt_alerts_archive',
	'edit_nt_alert',
	'edit_nt_alerts',
	'edit_published_nt_alerts',
	'publish_nt_alerts',
	'delete_nt_alert',
	'delete_nt_alerts',
	'delete_published_nt_alerts',
	'read_nt_alert',
);

$admin = get_role( 'administrator' );
if ( $admin ) {
	foreach ( $admin_extra_caps as $cap ) {
		$admin->remove_cap( $cap );
	}
}

remove_role( 'nt_alert_supervisor' );
remove_role( 'nt_alert_manager' );

foreach ( array(
	'nt_alerts_cron_expire_check',
	'nt_alerts_cron_expiry_warning',
	'nt_alerts_cron_eod_digest',
	'nt_alerts_cron_archive',
) as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_nt_alerts_%' OR option_name LIKE '_transient_timeout_nt_alerts_%'"
);
