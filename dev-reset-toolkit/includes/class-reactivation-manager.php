<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Reactivation_Manager {
	const SNAPSHOT_OPTION = 'drt_runtime_snapshot';
	protected $logger;

	public function __construct( DRT_Logger $logger ) {
		$this->logger = $logger;
	}

	public function store_runtime_snapshot( $reset_type ) {
		update_option(
			self::SNAPSHOT_OPTION,
			array(
				'timestamp'      => current_time( 'mysql' ),
				'reset_type'     => $reset_type,
				'user_id'        => get_current_user_id(),
				'active_theme'   => wp_get_theme()->get_stylesheet(),
				'active_plugins' => (array) get_option( 'active_plugins', array() ),
				'siteurl'        => get_option( 'siteurl' ),
				'home'           => get_option( 'home' ),
			),
			false
		);
	}

	public function get_runtime_snapshot() {
		$s = get_option( self::SNAPSHOT_OPTION, array() );
		return is_array( $s ) ? $s : array();
	}

	public function reactivate_theme() {
		$s = $this->get_runtime_snapshot();
		if ( empty( $s['active_theme'] ) ) {
			return new WP_Error( 'drt_no_theme', __( 'No theme found in snapshot.', 'dev-reset-toolkit' ) );
		}
		switch_theme( sanitize_text_field( $s['active_theme'] ) );
		return true;
	}

	public function reactivate_all_plugins() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$s = $this->get_runtime_snapshot();
		$errors = array();
		foreach ( (array) ( $s['active_plugins'] ?? array() ) as $plugin ) {
			$r = activate_plugin( $plugin );
			if ( is_wp_error( $r ) ) {
				$errors[] = $r->get_error_message();
			}
		}
		return $errors;
	}

	public function reactivate_plugin( $plugin ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		return activate_plugin( sanitize_text_field( $plugin ) );
	}

	public function recycle_plugins() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( array_keys( get_plugins() ) );
		return $this->reactivate_all_plugins();
	}
}
