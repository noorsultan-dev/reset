<?php
/**
 * Logger class.
 *
 * @package DevResetToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles reset logs.
 */
class DRT_Logger {
	/**
	 * Option key for logs.
	 */
	const OPTION_KEY = 'drt_reset_logs';

	/**
	 * Max logs.
	 *
	 * @var int
	 */
	protected $max_logs = 200;

	/**
	 * Record log entry.
	 *
	 * @param array $data Log data.
	 * @return void
	 */
	public function log( $data ) {
		$logs = $this->get_logs();

		$entry = wp_parse_args(
			$data,
			array(
				'timestamp' => current_time( 'mysql' ),
				'user_id'   => get_current_user_id(),
				'username'  => wp_get_current_user()->user_login,
				'action'    => 'reset',
				'reset_type'=> '',
				'reactivate'=> array(),
				'dry_run'   => false,
				'success'   => false,
				'errors'    => array(),
			),
		);

		array_unshift( $logs, $entry );
		$logs = array_slice( $logs, 0, $this->max_logs );

		update_option( self::OPTION_KEY, $logs, false );
	}

	/**
	 * Get logs.
	 *
	 * @return array
	 */
	public function get_logs() {
		$logs = get_option( self::OPTION_KEY, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Clear logs.
	 *
	 * @return void
	 */
	public function clear_logs() {
		update_option( self::OPTION_KEY, array(), false );
	}
}
