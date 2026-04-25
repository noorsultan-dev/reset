<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Reset_Manager {
	protected $logger;
	protected $safety;
	protected $reactivation;
	protected $settings;

	public function __construct( DRT_Logger $logger, DRT_Safety $safety, DRT_Reactivation_Manager $reactivation, DRT_Settings $settings ) {
		$this->logger = $logger;
		$this->safety = $safety;
		$this->reactivation = $reactivation;
		$this->settings = $settings;
	}

	public function run( $type, $args ) {
		$dry_run = ! empty( $args['dry_run'] ) || $this->settings->get( 'enable_dry_run' );
		$this->reactivation->store_runtime_snapshot( $type );

		if ( 'options_reset' === $type ) {
			$this->options_reset( $dry_run );
		} elseif ( 'site_reset' === $type ) {
			$this->site_reset( $dry_run );
			} elseif ( 'nuclear_reset' === $type ) {
				$this->nuclear_reset( $dry_run, ! empty( $args['delete_custom_tables'] ), ! empty( $args['delete_upload_files'] ) );
		} else {
			throw new Exception( __( 'Invalid reset type.', 'dev-reset-toolkit' ) );
		}

		if ( ! empty( $args['reactivate_theme'] ) && ! $dry_run ) {
			$this->reactivation->reactivate_theme();
		}
		if ( ! empty( $args['reactivate_all_plugins'] ) && ! $dry_run ) {
			$this->reactivation->reactivate_all_plugins();
		}
		if ( ! empty( $args['reactivate_selected_plugin'] ) && ! $dry_run ) {
			$this->reactivation->reactivate_plugin( $args['reactivate_selected_plugin'] );
		}

		$this->logger->log( array( 'action' => 'reset', 'type' => $type, 'status' => 'success' ) );
	}

	protected function options_reset( $dry_run ) {
		global $wpdb;
		$options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options}" );
		foreach ( $options as $opt ) {
			if ( in_array( $opt, $this->safety->protected_options(), true ) ) {
				continue;
			}
			if ( 0 === strpos( $opt, '_transient_' ) || 0 === strpos( $opt, '_site_transient_' ) || 0 === strpos( $opt, 'theme_mods_' ) || 0 === strpos( $opt, 'widget_' ) ) {
				if ( ! $dry_run ) {
					delete_option( $opt );
				}
			}
		}
	}

	protected function site_reset( $dry_run ) {
		$this->delete_posts( $dry_run );
		$this->delete_comments( $dry_run );
		$this->delete_terms_and_menus( $dry_run );
		$this->options_reset( $dry_run );
	}

	protected function nuclear_reset( $dry_run, $delete_custom_tables, $delete_upload_files ) {
		$this->site_reset( $dry_run );
		if ( $delete_upload_files ) {
			$this->delete_upload_files( $dry_run );
		}
		$this->delete_users_except_current( $dry_run );
		$this->safety->reset_roles( $dry_run );
		if ( $delete_custom_tables ) {
			$this->drop_custom_tables( $dry_run );
		}
	}

	protected function delete_posts( $dry_run ) {
		$ids = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) );
		foreach ( $ids as $id ) {
			if ( ! $dry_run ) {
				wp_delete_post( (int) $id, true );
			}
		}
	}

	protected function delete_comments( $dry_run ) {
		foreach ( get_comments( array( 'fields' => 'ids', 'number' => 0 ) ) as $id ) {
			if ( ! $dry_run ) {
				wp_delete_comment( (int) $id, true );
			}
		}
	}

	protected function delete_terms_and_menus( $dry_run ) {
		$taxonomies = get_taxonomies( array(), 'names' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( 'nav_menu' === $taxonomy ) {
				continue;
			}
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
			foreach ( $terms as $term ) {
				if ( ! $dry_run ) {
					wp_delete_term( (int) $term, $taxonomy );
				}
			}
		}
		foreach ( wp_get_nav_menus() as $menu ) {
			if ( ! $dry_run ) {
				wp_delete_nav_menu( $menu );
			}
		}
	}

	protected function delete_upload_files( $dry_run ) {
		$base = wp_get_upload_dir()['basedir'];
		if ( ! is_dir( $base ) ) {
			return;
		}
		$base = wp_normalize_path( $base );
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $item ) {
			$path = wp_normalize_path( $item->getPathname() );
			if ( 0 !== strpos( $path, trailingslashit( $base ) ) ) {
				continue;
			}
			if ( $item->isFile() && ! $dry_run ) {
				wp_delete_file( $path );
			}
		}
	}

	protected function delete_users_except_current( $dry_run ) {
		$current = get_current_user_id();
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( get_users( array( 'fields' => 'ID' ) ) as $uid ) {
			if ( (int) $uid === (int) $current ) {
				continue;
			}
			if ( ! $dry_run ) {
				wp_delete_user( (int) $uid, $current );
			}
		}
	}

	protected function drop_custom_tables( $dry_run ) {
		global $wpdb;
		$core = array( $wpdb->posts, $wpdb->postmeta, $wpdb->users, $wpdb->usermeta, $wpdb->comments, $wpdb->commentmeta, $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->termmeta, $wpdb->options, $wpdb->links );
		foreach ( $wpdb->get_col( 'SHOW TABLES' ) as $table ) {
			if ( 0 !== strpos( $table, $wpdb->prefix ) || in_array( $table, $core, true ) ) {
				continue;
			}
			if ( ! $dry_run ) {
				$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
			}
		}
	}
}
