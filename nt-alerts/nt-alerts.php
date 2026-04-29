<?php
/**
 * Plugin Name:       NT Service Alerts
 * Plugin URI:        https://niagaratransit.ca/
 * Description:       Supervisor-posted service alerts for Niagara Transit, with REST API and embeddable widget.
 * Version:           1.9.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Niagara Transit
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nt-alerts
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NT_ALERTS_VERSION',           '1.9.0' );
define( 'NT_ALERTS_PLUGIN_FILE',       __FILE__ );
define( 'NT_ALERTS_PLUGIN_DIR',        plugin_dir_path( __FILE__ ) );
define( 'NT_ALERTS_PLUGIN_URL',        plugin_dir_url( __FILE__ ) );
define( 'NT_ALERTS_REST_NAMESPACE',    'nt-alerts/v1' );
define( 'NT_ALERTS_CACHE_TTL_DEFAULT', 60 );
define( 'NT_ALERTS_CPT',               'nt_alert' );

require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-cpt.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-roles.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-activator.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-alert.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-cache.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-cors.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-rest.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-assets.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-admin.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-settings.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-archive.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-notifications.php';
require_once NT_ALERTS_PLUGIN_DIR . 'includes/class-nt-alerts-cron.php';

register_activation_hook(   __FILE__, array( 'NT_Alerts_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NT_Alerts_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( NT_Alerts::instance(), 'boot' ) );
