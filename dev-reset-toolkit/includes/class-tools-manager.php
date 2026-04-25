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
		$dangerous_tools = array( 'delete_themes_except_active', 'delete_plugins_except_self', 'delete_mu_dropins', 'clean_uploads_orphans', 'clean_wp_content', 'custom_tables', 'delete_htaccess' );
		if ( in_array( $tool, $dangerous_tools, true ) && ! $this->settings->get( 'enable_advanced_tools' ) ) {
			throw new Exception( __( 'Enable advanced dangerous tools in Settings first.', 'dev-reset-toolkit' ) );
		}
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
			case 'delete_content':
				$this->delete_content( $dry_run );
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
			case 'delete_mu_dropins':
				$this->delete_mu_dropins( $dry_run, (array) ( $args['selected_files'] ?? array() ) );
				break;
			case 'clean_uploads_orphans':
				$this->clean_uploads_orphans( $dry_run, (array) ( $args['selected_files'] ?? array() ) );
				break;
			case 'clean_wp_content':
				$this->clean_wp_content( $dry_run, (array) ( $args['selected_files'] ?? array() ) );
				break;
			case 'custom_tables':
				$this->custom_tables_action( $dry_run, (array) ( $args['selected_tables'] ?? array() ), ! empty( $args['drop_tables'] ) );
				break;
			case 'delete_htaccess':
				$this->delete_htaccess( $dry_run );
				break;
			default:
				throw new Exception( __( 'Invalid tool.', 'dev-reset-toolkit' ) );
		}
		$this->logger->log( array( 'action' => 'tool', 'type' => $tool, 'status' => 'success' ) );
	}

	protected function reset_theme_options( $dry_run ) {
		$this->delete_widgets( $dry_run );
		if ( ! $dry_run ) {
			remove_theme_mods();
		}
	}

	protected function delete_transients( $dry_run ) {
		global $wpdb;
		if ( ! $dry_run ) {
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
		}
	}

	protected function purge_cache() {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
	}

	protected function delete_content( $dry_run ) {
		foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) ) as $id ) {
			if ( ! $dry_run ) {
				wp_delete_post( (int) $id, true );
			}
		}
		foreach ( get_comments( array( 'fields' => 'ids', 'number' => 0 ) ) as $comment_id ) {
			if ( ! $dry_run ) {
				wp_delete_comment( (int) $comment_id, true );
			}
		}
		foreach ( wp_get_nav_menus() as $menu ) {
			if ( ! $dry_run ) {
				wp_delete_nav_menu( $menu );
			}
		}
	}

	protected function delete_widgets( $dry_run ) {
		global $wpdb;
		$rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'widget_%' OR option_name='sidebars_widgets'" );
		foreach ( $rows as $row ) {
			if ( ! $dry_run ) {
				delete_option( $row );
			}
		}
	}

	protected function delete_themes_except_active( $dry_run ) {
		if ( $dry_run ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		$active = wp_get_theme()->get_stylesheet();
		foreach ( wp_get_themes() as $slug => $theme ) {
			if ( $slug !== $active ) {
				delete_theme( $slug );
			}
		}
	}

	protected function delete_plugins_except_self( $dry_run ) {
		if ( $dry_run ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		foreach ( array_keys( get_plugins() ) as $plugin ) {
			if ( false === strpos( $plugin, 'dev-reset-toolkit' ) && false === strpos( $plugin, 'reset.php' ) ) {
				if ( ! is_plugin_active( $plugin ) ) {
					delete_plugins( array( $plugin ) );
				}
			}
		}
	}

	protected function delete_mu_dropins( $dry_run, $selected_files ) {
		$dropins = array( 'advanced-cache.php', 'db.php', 'object-cache.php', 'sunrise.php' );
		foreach ( $selected_files as $file ) {
			$file = sanitize_file_name( $file );
			$path = in_array( $file, $dropins, true ) ? trailingslashit( WP_CONTENT_DIR ) . $file : trailingslashit( WPMU_PLUGIN_DIR ) . $file;
			if ( file_exists( $path ) && ! $dry_run && ! DRT_Safety::is_protected_path( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}

	protected function clean_uploads_orphans( $dry_run, $selected_files ) {
		$base = wp_get_upload_dir()['basedir'];
		foreach ( $selected_files as $relative ) {
			$relative = ltrim( str_replace( '..', '', wp_unslash( $relative ) ), '/' );
			$full = trailingslashit( $base ) . $relative;
				if ( file_exists( $full ) && ! $dry_run && ! DRT_Safety::is_protected_path( $full ) ) {
					wp_delete_file( $full );
				}
		}
	}

	protected function clean_wp_content( $dry_run, $selected_files ) {
		foreach ( $selected_files as $relative ) {
			$relative = ltrim( str_replace( '..', '', wp_unslash( $relative ) ), '/' );
			if ( preg_match( '#^(plugins|themes|uploads|mu-plugins)(/|$)#', $relative ) ) {
				continue;
			}
			$full = trailingslashit( WP_CONTENT_DIR ) . $relative;
				if ( file_exists( $full ) && ! $dry_run && ! DRT_Safety::is_protected_path( $full ) ) {
					if ( is_dir( $full ) ) {
						$this->delete_dir( $full );
				} else {
					wp_delete_file( $full );
				}
			}
		}
	}

	protected function custom_tables_action( $dry_run, $selected_tables, $drop_tables ) {
		global $wpdb;
		foreach ( $selected_tables as $table ) {
			$table = sanitize_text_field( $table );
			if ( 0 !== strpos( $table, $wpdb->prefix ) ) {
				continue;
			}
			if ( ! $dry_run ) {
				if ( $drop_tables ) {
					$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
				} else {
					$wpdb->query( "TRUNCATE TABLE `{$table}`" );
				}
			}
		}
	}

	protected function delete_htaccess( $dry_run ) {
		$file = ABSPATH . '.htaccess';
		if ( file_exists( $file ) && ! $dry_run && ! DRT_Safety::is_protected_path( $file ) ) {
			wp_delete_file( $file );
		}
	}

	protected function delete_dir( $path ) {
		if ( DRT_Safety::is_protected_path( $path ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				wp_delete_file( $item->getPathname() );
			}
		}
		@rmdir( $path );
	}
}
