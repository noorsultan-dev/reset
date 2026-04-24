<?php
/**
 * Reset manager.
 *
 * @package DevResetToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs reset operations.
 */
class DRT_Reset_Manager {
	/**
	 * Logger.
	 *
	 * @var DRT_Logger
	 */
	protected $logger;

	/**
	 * Safety helper.
	 *
	 * @var DRT_Safety
	 */
	protected $safety;

	/**
	 * Reactivation manager.
	 *
	 * @var DRT_Reactivation_Manager
	 */
	protected $reactivation_manager;

	/**
	 * Constructor.
	 *
	 * @param DRT_Logger               $logger Logger.
	 * @param DRT_Safety               $safety Safety class.
	 * @param DRT_Reactivation_Manager $reactivation_manager Reactivation manager.
	 */
	public function __construct( DRT_Logger $logger, DRT_Safety $safety, DRT_Reactivation_Manager $reactivation_manager ) {
		$this->logger = $logger;
		$this->safety = $safety;
		$this->reactivation_manager = $reactivation_manager;
	}

	/**
	 * Run reset.
	 *
	 * @param string $reset_type Reset type.
	 * @param array  $args Extra args.
	 * @return array
	 */
	public function run_reset( $reset_type, $args = array() ) {
		$defaults = array(
			'reactivate_theme'          => false,
			'reactivate_all_plugins'    => false,
			'reactivate_selected_plugin'=> '',
			'dry_run'                   => false,
			'include_custom_tables'     => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$this->reactivation_manager->create_snapshot( $reset_type );

		$result = array(
			'success' => false,
			'errors'  => array(),
			'details' => array(),
		);

		try {
			switch ( $reset_type ) {
				case 'options_reset':
					$result['details'] = $this->run_options_reset( (bool) $args['dry_run'] );
					break;
				case 'site_reset':
					$result['details'] = $this->run_site_reset( (bool) $args['dry_run'] );
					break;
				case 'nuclear_reset':
					$result['details'] = $this->run_nuclear_reset( (bool) $args['dry_run'], (bool) $args['include_custom_tables'] );
					break;
				default:
					throw new Exception( __( 'Invalid reset type.', 'dev-reset-toolkit' ) );
			}

			$reactivation = $this->run_requested_reactivation( $args );
			$result['details']['reactivation'] = $reactivation;
			$result['success'] = true;
		} catch ( Exception $exception ) {
			$result['errors'][] = $exception->getMessage();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Dev Reset Toolkit] ' . $exception->getMessage() );
			}
		}

		$this->logger->log(
			array(
				'action'     => 'run_reset',
				'reset_type' => $reset_type,
				'reactivate' => array(
					'theme'           => (bool) $args['reactivate_theme'],
					'all_plugins'     => (bool) $args['reactivate_all_plugins'],
					'selected_plugin' => sanitize_text_field( (string) $args['reactivate_selected_plugin'] ),
				),
				'dry_run'    => (bool) $args['dry_run'],
				'success'    => $result['success'],
				'errors'     => $result['errors'],
			),
		);

		return $result;
	}

	/**
	 * Options reset.
	 *
	 * @param bool $dry_run Dry-run.
	 * @return array
	 */
	protected function run_options_reset( $dry_run ) {
		global $wpdb;

		$protected_options = $this->safety->get_protected_options();
		$deleted_options = array();

		$all_options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options}" );
		foreach ( $all_options as $option_name ) {
			if ( $this->safety->is_protected_option( $option_name ) ) {
				continue;
			}
			if ( 0 === strpos( $option_name, '_transient_' ) || 0 === strpos( $option_name, '_site_transient_' ) ) {
				$deleted_options[] = $option_name;
				if ( ! $dry_run ) {
					delete_option( $option_name );
				}
				continue;
			}
			if ( 0 === strpos( $option_name, 'theme_mods_' ) || 0 === strpos( $option_name, 'widget_' ) ) {
				$deleted_options[] = $option_name;
				if ( ! $dry_run ) {
					delete_option( $option_name );
				}
				continue;
			}
			if ( ! in_array( $option_name, $protected_options, true ) ) {
				$deleted_options[] = $option_name;
				if ( ! $dry_run ) {
					delete_option( $option_name );
				}
			}
		}

		if ( ! $dry_run ) {
			wp_cache_flush();
		}

		return array(
			'operation'       => 'options_reset',
			'deleted_options' => $deleted_options,
			'count'           => count( $deleted_options ),
		);
	}

	/**
	 * Site reset.
	 *
	 * @param bool $dry_run Dry-run.
	 * @return array
	 */
	protected function run_site_reset( $dry_run ) {
		$details = array();
		$details['deleted_posts'] = $this->delete_posts_and_attachments( $dry_run, false );
		$details['deleted_comments'] = $this->delete_comments( $dry_run );
		$details['deleted_terms'] = $this->delete_terms( $dry_run );
		$details['cleaned_options'] = $this->cleanup_settings_and_transients( $dry_run );
		$details['menus_reset'] = $this->reset_menus( $dry_run );
		return $details;
	}

	/**
	 * Nuclear reset.
	 *
	 * @param bool $dry_run Dry-run.
	 * @param bool $include_custom_tables Include custom tables.
	 * @return array
	 */
	protected function run_nuclear_reset( $dry_run, $include_custom_tables ) {
		$details = $this->run_site_reset( $dry_run );
		$details['deleted_uploads'] = $this->delete_uploads( $dry_run );
		$details['deleted_users'] = $this->safety->remove_users_except_current( $dry_run );
		$this->safety->reset_roles_to_defaults( $dry_run );
		$details['roles_reset'] = true;

		if ( $include_custom_tables ) {
			$details['custom_tables'] = $this->drop_custom_tables( $dry_run );
		}

		return $details;
	}

	/**
	 * Delete posts and attachments.
	 *
	 * @param bool $dry_run Dry-run.
	 * @param bool $delete_files Delete files.
	 * @return array
	 */
	protected function delete_posts_and_attachments( $dry_run, $delete_files ) {
		$post_ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);

		$deleted = array();
		foreach ( $post_ids as $post_id ) {
			$deleted[] = (int) $post_id;
			if ( ! $dry_run ) {
				wp_delete_post( (int) $post_id, true );
			}
		}

		if ( $delete_files && ! $dry_run ) {
			$this->delete_uploads( false );
		}

		return $deleted;
	}

	/**
	 * Delete comments.
	 *
	 * @param bool $dry_run Dry-run.
	 * @return array
	 */
	protected function delete_comments( $dry_run ) {
		$comment_ids = get_comments(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);
		foreach ( $comment_ids as $comment_id ) {
			if ( ! $dry_run ) {
				wp_delete_comment( (int) $comment_id, true );
			}
		}
		return array_map( 'intval', $comment_ids );
	}

	/**
	 * Delete terms safely.
	 *
	 * @param bool $dry_run Dry-run.
	 * @return array
	 */
	protected function delete_terms( $dry_run ) {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		$deleted = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( in_array( $taxonomy, array( 'nav_menu', 'link_category' ), true ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			foreach ( $terms as $term_id ) {
				$deleted[] = array(
					'taxonomy' => $taxonomy,
					'term_id'  => (int) $term_id,
				);
				if ( ! $dry_run ) {
					wp_delete_term( (int) $term_id, $taxonomy );
				}
			}
		}

		return $deleted;
	}

	/**
	 * Cleanup plugin/theme settings and transients.
	 *
	 * @param bool $dry_run Dry-run.
	 * @return array
	 */
	protected function cleanup_settings_and_transients( $dry_run ) {
		global $wpdb;
		$protected = $this->safety->get_protected_options();
		$candidates = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options}" );
		$deleted = array();

		foreach ( $candidates as $option_name ) {
			if ( $this->safety->is_protected_option( $option_name ) ) {
				continue;
			}

			$is_transient = ( 0 === strpos( $option_name, '_transient_' ) || 0 === strpos( $option_name, '_site_transient_' ) );
			$is_widget    = ( 0 === strpos( $option_name, 'widget_' ) );
			$is_theme_mod = ( 0 === strpos( $option_name, 'theme_mods_' ) );
			$is_nav       = ( 0 === strpos( $option_name, 'nav_menu_options' ) || 0 === strpos( $option_name, 'theme_mods' ) );
			$customish    = ( false !== strpos( $option_name, '_' ) && ! in_array( $option_name, $protected, true ) );

			if ( $is_transient || $is_widget || $is_theme_mod || $is_nav || $customish ) {
				$deleted[] = $option_name;
				if ( ! $dry_run ) {
					delete_option( $option_name );
				}
			}
		}

		return $deleted;
	}

	/**
	 * Reset menus.
	 *
	 * @param bool $dry_run Dry-run.
	 * @return array
	 */
	protected function reset_menus( $dry_run ) {
		$menus = wp_get_nav_menus();
		$deleted = array();
		foreach ( $menus as $menu ) {
			$deleted[] = (int) $menu->term_id;
			if ( ! $dry_run ) {
				wp_delete_nav_menu( $menu->term_id );
			}
		}
		return $deleted;
	}

	/**
	 * Delete uploads files.
	 *
	 * @param bool $dry_run Dry-run.
	 * @return array
	 */
	protected function delete_uploads( $dry_run ) {
		$upload_dir = wp_get_upload_dir();
		$base = trailingslashit( $upload_dir['basedir'] );
		$deleted = array();

		if ( ! is_dir( $base ) ) {
			return $deleted;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$path = $item->getPathname();
			if ( is_file( $path ) ) {
				$deleted[] = $path;
				if ( ! $dry_run ) {
					wp_delete_file( $path );
				}
			}
		}

		return $deleted;
	}

	/**
	 * Drop custom plugin tables with opt-in.
	 *
	 * @param bool $dry_run Dry-run.
	 * @return array
	 */
	protected function drop_custom_tables( $dry_run ) {
		global $wpdb;
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		$core_prefixes = array(
			$wpdb->prefix . 'posts',
			$wpdb->prefix . 'postmeta',
			$wpdb->prefix . 'users',
			$wpdb->prefix . 'usermeta',
			$wpdb->prefix . 'terms',
			$wpdb->prefix . 'termmeta',
			$wpdb->prefix . 'term_taxonomy',
			$wpdb->prefix . 'term_relationships',
			$wpdb->prefix . 'comments',
			$wpdb->prefix . 'commentmeta',
			$wpdb->prefix . 'options',
			$wpdb->prefix . 'links',
		);

		$dropped = array();
		foreach ( $tables as $table ) {
			if ( in_array( $table, $core_prefixes, true ) ) {
				continue;
			}
			if ( 0 !== strpos( $table, $wpdb->prefix ) ) {
				continue;
			}
			$dropped[] = $table;
			if ( ! $dry_run ) {
				$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
			}
		}
		return $dropped;
	}

	/**
	 * Perform requested reactivation actions.
	 *
	 * @param array $args Action args.
	 * @return array
	 */
	protected function run_requested_reactivation( $args ) {
		$summary = array();
		$dry_run = (bool) $args['dry_run'];

		if ( ! empty( $args['reactivate_theme'] ) ) {
			$summary['theme'] = $this->reactivation_manager->reactivate_previous_theme( $dry_run );
		}

		if ( ! empty( $args['reactivate_all_plugins'] ) ) {
			$summary['all_plugins'] = $this->reactivation_manager->reactivate_previous_plugins( $dry_run );
		}

		if ( ! empty( $args['reactivate_selected_plugin'] ) ) {
			$summary['selected_plugin'] = $this->reactivation_manager->reactivate_selected_plugin( $args['reactivate_selected_plugin'], $dry_run );
		}

		return $summary;
	}
}
