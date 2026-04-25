<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Snapshot_Manager {
	const OPTION_KEY = 'drt_snapshots';
	protected $logger;
	protected $settings;

	public function __construct( DRT_Logger $logger, DRT_Settings $settings ) {
		$this->logger = $logger;
		$this->settings = $settings;
	}

	public function create() {
		global $wpdb;
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		$data = array();
		foreach ( $tables as $table ) {
			if ( 0 !== strpos( $table, $wpdb->prefix ) ) {
				continue;
			}
			$data[ $table ] = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
		}
		$snapshots = $this->all();
		$id = 'snap_' . time() . '_' . wp_generate_password( 6, false, false );
		$snapshots[ $id ] = array(
			'id'        => $id,
			'created'   => current_time( 'mysql' ),
			'user'      => wp_get_current_user()->user_login,
			'table_cnt' => count( $data ),
			'data'      => $data,
		);
		$max = (int) $this->settings->get( 'max_snapshots' );
		if ( count( $snapshots ) > $max ) {
			$keys = array_keys( $snapshots );
			while ( count( $keys ) > $max ) {
				unset( $snapshots[ array_shift( $keys ) ] );
			}
		}
		update_option( self::OPTION_KEY, $snapshots, false );
		$this->logger->log( array( 'action' => 'snapshot', 'type' => 'create', 'status' => 'success' ) );
	}

	public function restore( $id ) {
		global $wpdb;
		$snapshots = $this->all();
		if ( empty( $snapshots[ $id ]['data'] ) ) {
			return new WP_Error( 'drt_snapshot_missing', __( 'Snapshot not found.', 'dev-reset-toolkit' ) );
		}
		foreach ( $snapshots[ $id ]['data'] as $table => $rows ) {
			$wpdb->query( "TRUNCATE TABLE `{$table}`" );
			foreach ( $rows as $row ) {
				$wpdb->insert( $table, $row );
			}
		}
		$this->logger->log( array( 'action' => 'snapshot', 'type' => 'restore', 'status' => 'success' ) );
		return true;
	}

	public function delete( $id ) {
		$s = $this->all();
		unset( $s[ $id ] );
		update_option( self::OPTION_KEY, $s, false );
		$this->logger->log( array( 'action' => 'snapshot', 'type' => 'delete', 'status' => 'success' ) );
	}

	public function all() {
		$s = get_option( self::OPTION_KEY, array() );
		return is_array( $s ) ? $s : array();
	}
}
