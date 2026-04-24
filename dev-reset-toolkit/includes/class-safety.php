<?php
/**
 * Safety utilities.
 *
 * @package DevResetToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Safety guardrails.
 */
class DRT_Safety {
	/**
	 * Protected options.
	 *
	 * @return array
	 */
	public function get_protected_options() {
		return array(
			'siteurl',
			'home',
			'admin_email',
			'blogname',
			'blogdescription',
			'stylesheet',
			'template',
			'active_plugins',
			'recently_edited',
			'users_can_register',
			'default_role',
		);
	}

	/**
	 * Check if option is protected.
	 *
	 * @param string $option_name Option name.
	 * @return bool
	 */
	public function is_protected_option( $option_name ) {
		return in_array( $option_name, $this->get_protected_options(), true );
	}

	/**
	 * Remove users except current user.
	 *
	 * @param bool $dry_run Dry run mode.
	 * @return array
	 */
	public function remove_users_except_current( $dry_run = false ) {
		$current_user = get_current_user_id();
		$users = get_users(
			array(
				'fields' => 'ID',
			)
		);

		$deleted = array();
		foreach ( $users as $user_id ) {
			if ( (int) $user_id === (int) $current_user ) {
				continue;
			}
			$deleted[] = (int) $user_id;
			if ( ! $dry_run ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( (int) $user_id, $current_user );
			}
		}

		return $deleted;
	}

	/**
	 * Reset roles to defaults.
	 *
	 * @param bool $dry_run Dry run mode.
	 * @return void
	 */
	public function reset_roles_to_defaults( $dry_run = false ) {
		if ( $dry_run ) {
			return;
		}

		global $wp_roles;
		if ( ! ( $wp_roles instanceof WP_Roles ) ) {
			$wp_roles = new WP_Roles();
		}

		foreach ( array_keys( $wp_roles->roles ) as $role_name ) {
			remove_role( $role_name );
		}

		populate_roles();
	}
}
