<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Settings {
	const OPTION_KEY = 'drt_settings';

	public static function defaults() {
		return array(
			'enable_logs'          => 1,
			'enable_dry_run'       => 0,
			'keep_plugin_active'   => 1,
			'max_snapshots'        => 5,
			'cleanup_on_uninstall' => 0,
		);
	}

	public static function ensure_defaults() {
		$current = get_option( self::OPTION_KEY, array() );
		update_option( self::OPTION_KEY, wp_parse_args( $current, self::defaults() ), false );
	}

	public function get_all() {
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );
	}

	public function get( $key ) {
		$all = $this->get_all();
		return $all[ $key ] ?? null;
	}

	public function update( $new_values ) {
		$clean = array(
			'enable_logs'          => ! empty( $new_values['enable_logs'] ) ? 1 : 0,
			'enable_dry_run'       => ! empty( $new_values['enable_dry_run'] ) ? 1 : 0,
			'keep_plugin_active'   => ! empty( $new_values['keep_plugin_active'] ) ? 1 : 0,
			'max_snapshots'        => max( 1, (int) ( $new_values['max_snapshots'] ?? 5 ) ),
			'cleanup_on_uninstall' => ! empty( $new_values['cleanup_on_uninstall'] ) ? 1 : 0,
		);
		update_option( self::OPTION_KEY, $clean, false );
	}
}
