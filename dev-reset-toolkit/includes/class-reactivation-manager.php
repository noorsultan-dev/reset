<?php
/**
 * Reactivation manager.
 *
 * @package DevResetToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles theme/plugin reactivation workflows.
 */
class DRT_Reactivation_Manager {
	/**
	 * Snapshot option key.
	 */
	const SNAPSHOT_OPTION_KEY = 'drt_last_snapshot';

	/**
	 * Logger.
	 *
	 * @var DRT_Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param DRT_Logger $logger Logger instance.
	 */
	public function __construct( DRT_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Create environment snapshot.
	 *
	 * @param string $reset_type Reset type.
	 * @return array
	 */
	public function create_snapshot( $reset_type = '' ) {
		$snapshot = array(
			'timestamp'      => current_time( 'mysql' ),
			'user_id'        => get_current_user_id(),
			'active_theme'   => wp_get_theme()->get_stylesheet(),
			'active_plugins' => (array) get_option( 'active_plugins', array() ),
			'siteurl'        => get_option( 'siteurl' ),
			'home'           => get_option( 'home' ),
			'reset_type'     => $reset_type,
		);

		update_option( self::SNAPSHOT_OPTION_KEY, $snapshot, false );
		return $snapshot;
	}

	/**
	 * Get snapshot.
	 *
	 * @return array
	 */
	public function get_snapshot() {
		$snapshot = get_option( self::SNAPSHOT_OPTION_KEY, array() );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	/**
	 * Reactivate previous theme.
	 *
	 * @param bool $dry_run Dry-run mode.
	 * @return bool|WP_Error
	 */
	public function reactivate_previous_theme( $dry_run = false ) {
		$snapshot = $this->get_snapshot();
		if ( empty( $snapshot['active_theme'] ) ) {
			return new WP_Error( 'drt_no_theme', __( 'No theme snapshot found.', 'dev-reset-toolkit' ) );
		}
		if ( $dry_run ) {
			return true;
		}

		switch_theme( sanitize_text_field( $snapshot['active_theme'] ) );
		return true;
	}

	/**
	 * Reactivate all previously active plugins.
	 *
	 * @param bool $dry_run Dry-run mode.
	 * @return array
	 */
	public function reactivate_previous_plugins( $dry_run = false ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$snapshot = $this->get_snapshot();
		$plugins = isset( $snapshot['active_plugins'] ) ? (array) $snapshot['active_plugins'] : array();
		if ( $dry_run ) {
			return array( 'activated' => $plugins, 'errors' => array() );
		}

		$errors = array();
		foreach ( $plugins as $plugin ) {
			$result = activate_plugin( $plugin );
			if ( is_wp_error( $result ) ) {
				$errors[] = $plugin . ': ' . $result->get_error_message();
			}
		}
		return array(
			'activated' => $plugins,
			'errors'    => $errors,
		);
	}

	/**
	 * Reactivate selected plugin.
	 *
	 * @param string $plugin Plugin basename.
	 * @param bool   $dry_run Dry-run mode.
	 * @return true|WP_Error
	 */
	public function reactivate_selected_plugin( $plugin, $dry_run = false ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin = sanitize_text_field( $plugin );
		if ( empty( $plugin ) ) {
			return new WP_Error( 'drt_no_plugin', __( 'No plugin selected.', 'dev-reset-toolkit' ) );
		}
		if ( $dry_run ) {
			return true;
		}
		return activate_plugin( $plugin );
	}

	/**
	 * Deactivate all plugins and then restore previous active plugins.
	 *
	 * @param bool $dry_run Dry-run mode.
	 * @return array
	 */
	public function recycle_plugins( $dry_run = false ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$snapshot = $this->get_snapshot();
		$plugins = isset( $snapshot['active_plugins'] ) ? (array) $snapshot['active_plugins'] : array();

		if ( ! $dry_run ) {
			deactivate_plugins( array_keys( get_plugins() ) );
		}

		$result = $this->reactivate_previous_plugins( $dry_run );
		$result['snapshot_plugins'] = $plugins;
		return $result;
	}

	/**
	 * Reset selected plugin with optional option cleanup.
	 *
	 * @param string $plugin Plugin basename.
	 * @param string $option_prefix Optional option prefix for cleanup.
	 * @param bool   $dry_run Dry-run mode.
	 * @return array
	 */
	public function reset_selected_plugin( $plugin, $option_prefix = '', $dry_run = false ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin = sanitize_text_field( $plugin );
		$option_prefix = sanitize_text_field( $option_prefix );
		$errors = array();

		if ( empty( $plugin ) ) {
			$errors[] = __( 'No plugin selected for reset.', 'dev-reset-toolkit' );
			return array( 'success' => false, 'errors' => $errors );
		}

		if ( ! $dry_run ) {
			deactivate_plugins( $plugin );
			if ( ! empty( $option_prefix ) ) {
				global $wpdb;
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						$wpdb->esc_like( $option_prefix ) . '%'
					)
				);
			}
			$activate = activate_plugin( $plugin );
			if ( is_wp_error( $activate ) ) {
				$errors[] = $activate->get_error_message();
			}
		}

		return array(
			'success' => empty( $errors ),
			'errors'  => $errors,
		);
	}
}
