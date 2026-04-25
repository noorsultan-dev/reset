<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Safety {
	public function protected_options() {
		return array(
			'siteurl',
			'home',
			'admin_email',
			'blogname',
			'blogdescription',
			'template',
			'stylesheet',
			'current_theme',
			'active_plugins',
			'users_can_register',
			'default_role',
			'permalink_structure',
			'rewrite_rules',
			'db_version',
			'initial_db_version',
			'cron',
			'upload_path',
			'upload_url_path',
		);
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

	public static function is_protected_path( $path ) {
		$real = wp_normalize_path( realpath( $path ) ?: $path );
		$protected = array(
			wp_normalize_path( ABSPATH ),
			wp_normalize_path( WP_PLUGIN_DIR ),
			wp_normalize_path( get_theme_root() ),
			wp_normalize_path( WP_CONTENT_DIR . '/plugins' ),
			wp_normalize_path( WP_CONTENT_DIR . '/themes' ),
			wp_normalize_path( ABSPATH . 'wp-config.php' ),
			wp_normalize_path( ABSPATH . 'wp-admin' ),
			wp_normalize_path( ABSPATH . 'wp-includes' ),
		);

		foreach ( $protected as $item ) {
			if ( $real === $item || 0 === strpos( $real, trailingslashit( $item ) ) ) {
				return true;
			}
		}

		return false;
	}
}
