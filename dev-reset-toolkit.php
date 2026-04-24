<?php
/**
 * Plugin Name: Dev Reset Toolkit (Bootstrap Loader)
 * Plugin URI: https://example.com/dev-reset-toolkit
 * Description: Bootstrap loader for packaged installs that include the plugin in a nested folder.
 * Version: 1.0.1
 * Author: Dev Reset Toolkit
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: dev-reset-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nested_main_file = __DIR__ . '/dev-reset-toolkit/dev-reset-toolkit.php';
if ( file_exists( $nested_main_file ) ) {
	require_once $nested_main_file;
	return;
}

add_action(
	'admin_notices',
	static function () {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Dev Reset Toolkit is missing required plugin files. Please reinstall the plugin package.', 'dev-reset-toolkit' ) . '</p></div>';
	}
);
