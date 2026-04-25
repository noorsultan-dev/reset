<?php
/**
 * Plugin Name: Dev Reset Toolkit
 * Description: Safe, free reset tools for developers.
 * Version: 2.1.0
 * Author: Dev Reset Toolkit
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: dev-reset-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DRT_VERSION', '2.1.0' );
define( 'DRT_PLUGIN_FILE', __FILE__ );
define( 'DRT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DRT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once DRT_PLUGIN_DIR . 'includes/class-logger.php';
require_once DRT_PLUGIN_DIR . 'includes/class-safety.php';
require_once DRT_PLUGIN_DIR . 'includes/class-settings.php';
require_once DRT_PLUGIN_DIR . 'includes/class-snapshot-manager.php';
require_once DRT_PLUGIN_DIR . 'includes/class-collections-manager.php';
require_once DRT_PLUGIN_DIR . 'includes/class-reactivation-manager.php';
require_once DRT_PLUGIN_DIR . 'includes/class-tools-manager.php';
require_once DRT_PLUGIN_DIR . 'includes/class-reset-manager.php';
require_once DRT_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Initialize plugin services.
 */
function drt_bootstrap() {
	$settings = new DRT_Settings();
	$logger = new DRT_Logger( $settings );
	$safety = new DRT_Safety();
	$snapshots = new DRT_Snapshot_Manager( $logger, $settings );
	$collections = new DRT_Collections_Manager( $logger );
	$reactivation = new DRT_Reactivation_Manager( $logger );
	$tools = new DRT_Tools_Manager( $logger, $safety, $settings );
	$resets = new DRT_Reset_Manager( $logger, $safety, $reactivation, $settings );

	new DRT_Admin( $resets, $tools, $snapshots, $collections, $reactivation, $logger, $settings );
}
add_action( 'plugins_loaded', 'drt_bootstrap' );

/**
 * Activation setup.
 */
function drt_activate() {
	DRT_Settings::ensure_defaults();
	if ( false === get_option( DRT_Logger::OPTION_KEY ) ) {
		add_option( DRT_Logger::OPTION_KEY, array(), '', false );
	}
	if ( false === get_option( DRT_Snapshot_Manager::OPTION_KEY ) ) {
		add_option( DRT_Snapshot_Manager::OPTION_KEY, array(), '', false );
	}
}
register_activation_hook( __FILE__, 'drt_activate' );
