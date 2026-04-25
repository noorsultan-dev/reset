<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Safety {
	public function protected_options() {
		return array( 'siteurl', 'home', 'admin_email', 'blogname', 'template', 'stylesheet', 'active_plugins' );
	}

	public function can_run() {
		return is_admin() && current_user_can( 'manage_options' );
	}

	public function require_capability_and_nonce( $action, $nonce_name ) {
		if ( ! $this->can_run() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'dev-reset-toolkit' ) );
		}
		check_admin_referer( $action, $nonce_name );
	}

	public function reset_roles( $dry_run = false ) {
		if ( $dry_run ) {
			return;
		}
		global $wp_roles;
		if ( ! ( $wp_roles instanceof WP_Roles ) ) {
			$wp_roles = new WP_Roles();
		}
		foreach ( array_keys( $wp_roles->roles ) as $role ) {
			remove_role( $role );
		}
		populate_roles();
	}
}
