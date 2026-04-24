<?php
/**
 * Plugin Name: Dev Reset Toolkit
 * Description: Safe, free reset tools for developers.
 * Version: 2.0.0
 * Author: Dev Reset Toolkit
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: dev-reset-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$main = __DIR__ . '/dev-reset-toolkit/dev-reset-toolkit.php';
if ( file_exists( $main ) ) {
	require_once $main;
} else {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Dev Reset Toolkit files are missing. Reinstall the plugin.', 'dev-reset-toolkit' ) . '</p></div>';
		}
	);
}
