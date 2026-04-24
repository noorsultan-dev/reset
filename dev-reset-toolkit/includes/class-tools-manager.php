<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Tools_Manager {
	protected $logger;
	protected $safety;
	protected $settings;

	public function __construct( DRT_Logger $logger, DRT_Safety $safety, DRT_Settings $settings ) {
		$this->logger = $logger;
		$this->safety = $safety;
		$this->settings = $settings;
	}

	public function run( $tool, $args ) {
		$dry_run = ! empty( $args['dry_run'] ) || $this->settings->get( 'enable_dry_run' );
		switch ( $tool ) {
			case 'reset_theme_options':
				$this->reset_theme_options( $dry_run );
				break;
			case 'reset_user_roles':
				$this->safety->reset_roles( $dry_run );
				break;
			case 'delete_transients':
				$this->delete_transients( $dry_run );
				break;
			case 'purge_cache':
				$this->purge_cache();
				break;
			case 'delete_widgets':
				$this->delete_widgets( $dry_run );
				break;
			case 'delete_themes_except_active':
				$this->delete_themes_except_active( $dry_run );
				break;
			case 'delete_plugins_except_self':
				$this->delete_plugins_except_self( $dry_run );
				break;
			case 'delete_htaccess':
				$this->delete_htaccess( $dry_run );
				break;
			default:
				throw new Exception( __( 'Invalid tool.', 'dev-reset-toolkit' ) );
		}
		$this->logger->log( array( 'action' => 'tool', 'type' => $tool, 'status' => 'success' ) );
	}

	protected function reset_theme_options( $dry_run ) { $this->delete_widgets( $dry_run ); if ( ! $dry_run ) { remove_theme_mods(); } }
	protected function delete_transients( $dry_run ) { global $wpdb; if ( ! $dry_run ) { $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" ); } }
	protected function purge_cache() { if ( function_exists( 'wp_cache_flush' ) ) { wp_cache_flush(); } if ( function_exists( 'w3tc_flush_all' ) ) { w3tc_flush_all(); } if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); } }
	protected function delete_widgets( $dry_run ) { global $wpdb; $rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'widget_%'" ); foreach ( $rows as $row ) { if ( ! $dry_run ) { delete_option( $row ); } } }
	protected function delete_themes_except_active( $dry_run ) { if ( $dry_run ) { return; } require_once ABSPATH . 'wp-admin/includes/theme.php'; $active = wp_get_theme()->get_stylesheet(); foreach ( wp_get_themes() as $slug => $theme ) { if ( $slug !== $active ) { delete_theme( $slug ); } } }
	protected function delete_plugins_except_self( $dry_run ) { if ( $dry_run ) { return; } require_once ABSPATH . 'wp-admin/includes/plugin.php'; foreach ( array_keys( get_plugins() ) as $plugin ) { if ( false === strpos( $plugin, 'dev-reset-toolkit' ) && false === strpos( $plugin, 'reset.php' ) ) { delete_plugins( array( $plugin ) ); } } }
	protected function delete_htaccess( $dry_run ) { $file = ABSPATH . '.htaccess'; if ( file_exists( $file ) && ! $dry_run ) { wp_delete_file( $file ); } }
}
