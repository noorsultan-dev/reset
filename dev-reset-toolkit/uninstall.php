<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'drt_settings', array() );
if ( empty( $settings['cleanup_on_uninstall'] ) ) {
	return;
}

delete_option( 'drt_settings' );
delete_option( 'drt_reset_logs' );
delete_option( 'drt_snapshots' );
delete_option( 'drt_runtime_snapshot' );
delete_option( 'drt_collections' );
