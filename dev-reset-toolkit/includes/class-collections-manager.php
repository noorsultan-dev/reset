<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Collections_Manager {
	const OPTION_KEY = 'drt_collections';
	protected $logger;

	public function __construct( DRT_Logger $logger ) {
		$this->logger = $logger;
	}

	public function all() {
		$c = get_option( self::OPTION_KEY, array() );
		return is_array( $c ) ? $c : array();
	}

	public function save_all( $collections ) {
		update_option( self::OPTION_KEY, $collections, false );
	}

	public function create( $name ) {
		$collections = $this->all();
		$id = 'col_' . time() . '_' . wp_generate_password( 5, false, false );
		$collections[ $id ] = array(
			'id'      => $id,
			'name'    => sanitize_text_field( $name ),
			'plugins' => array(),
			'themes'  => array(),
		);
		$this->save_all( $collections );
		$this->logger->log( array( 'action' => 'collection', 'type' => 'create', 'status' => 'success' ) );
	}

	public function rename( $id, $name ) {
		$collections = $this->all();
		if ( isset( $collections[ $id ] ) ) {
			$collections[ $id ]['name'] = sanitize_text_field( $name );
			$this->save_all( $collections );
		}
	}

	public function delete( $id ) {
		$collections = $this->all();
		unset( $collections[ $id ] );
		$this->save_all( $collections );
	}

	public function add_plugin( $id, $plugin_file ) {
		$collections = $this->all();
		if ( isset( $collections[ $id ] ) ) {
			$plugin_file = sanitize_text_field( $plugin_file );
			if ( ! in_array( $plugin_file, $collections[ $id ]['plugins'], true ) ) {
				$collections[ $id ]['plugins'][] = $plugin_file;
			}
			$this->save_all( $collections );
		}
	}

	public function add_theme( $id, $theme_slug ) {
		$collections = $this->all();
		if ( isset( $collections[ $id ] ) ) {
			$theme_slug = sanitize_text_field( $theme_slug );
			if ( ! in_array( $theme_slug, $collections[ $id ]['themes'], true ) ) {
				$collections[ $id ]['themes'][] = $theme_slug;
			}
			$this->save_all( $collections );
		}
	}

	public function install_and_activate( $id ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$collections = $this->all();
		if ( empty( $collections[ $id ] ) ) {
			return;
		}
		foreach ( $collections[ $id ]['plugins'] as $plugin ) {
			if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
				activate_plugin( $plugin );
			}
		}
		foreach ( $collections[ $id ]['themes'] as $theme ) {
			if ( wp_get_theme( $theme )->exists() ) {
				switch_theme( $theme );
			}
		}
	}

	public function export_json( $id ) {
		$collections = $this->all();
		return wp_json_encode( $collections[ $id ] ?? array(), JSON_PRETTY_PRINT );
	}

	public function import_json( $json ) {
		$data = json_decode( wp_unslash( $json ), true );
		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'drt_invalid_collection', __( 'Invalid collection JSON.', 'dev-reset-toolkit' ) );
		}
		$this->create( $data['name'] );
		$all = $this->all();
		$last_id = array_key_last( $all );
		$all[ $last_id ]['plugins'] = array_map( 'sanitize_text_field', (array) ( $data['plugins'] ?? array() ) );
		$all[ $last_id ]['themes'] = array_map( 'sanitize_text_field', (array) ( $data['themes'] ?? array() ) );
		$this->save_all( $all );
		return true;
	}
}
