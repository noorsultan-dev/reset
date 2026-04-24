<?php
/**
 * Plugin Name: Dev Reset Toolkit
 * Plugin URI: https://example.com/dev-reset-toolkit
 * Description: Safe reset tools for developers with reset types, reactivation controls, dry-run mode, and logs.
 * Version: 1.0.1
 * Author: Dev Reset Toolkit
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: dev-reset-toolkit
 *
 * @package DevResetToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DRT_VERSION', '1.0.1' );
define( 'DRT_PLUGIN_FILE', __FILE__ );
define( 'DRT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DRT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once DRT_PLUGIN_DIR . 'includes/class-logger.php';
require_once DRT_PLUGIN_DIR . 'includes/class-safety.php';
require_once DRT_PLUGIN_DIR . 'includes/class-reactivation-manager.php';
require_once DRT_PLUGIN_DIR . 'includes/class-reset-manager.php';
require_once DRT_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Bootstrap plugin.
 */
function drt_bootstrap() {
	$logger = new DRT_Logger();
	$safety = new DRT_Safety();
	$reactivation_manager = new DRT_Reactivation_Manager( $logger );
	$reset_manager = new DRT_Reset_Manager( $logger, $safety, $reactivation_manager );

	new DRT_Admin( $reset_manager, $reactivation_manager, $logger );
}
add_action( 'plugins_loaded', 'drt_bootstrap' );

/**
 * Activation hook.
 */
function drt_activate() {
	if ( false === get_option( DRT_Logger::OPTION_KEY ) ) {
		add_option( DRT_Logger::OPTION_KEY, array() );
	}
}
register_activation_hook( __FILE__, 'drt_activate' );
