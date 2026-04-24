<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Logger {
	const OPTION_KEY = 'drt_reset_logs';
	protected $settings;

	public function __construct( DRT_Settings $settings ) {
		$this->settings = $settings;
	}

	public function log( $entry ) {
		if ( ! $this->settings->get( 'enable_logs' ) ) {
			return;
		}
		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		$defaults = array(
			'timestamp' => current_time( 'mysql' ),
			'user'      => wp_get_current_user()->user_login,
			'action'    => '',
			'type'      => '',
			'status'    => 'failed',
			'error'     => '',
		);
		array_unshift( $logs, wp_parse_args( $entry, $defaults ) );
		$logs = array_slice( $logs, 0, 500 );
		update_option( self::OPTION_KEY, $logs, false );
	}

	public function all() {
		$logs = get_option( self::OPTION_KEY, array() );
		return is_array( $logs ) ? $logs : array();
	}
}
