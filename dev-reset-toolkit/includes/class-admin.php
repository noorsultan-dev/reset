<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Admin {
	protected $resets;
	protected $tools;
	protected $snapshots;
	protected $collections;
	protected $reactivation;
	protected $logger;
	protected $settings;

	public function __construct( DRT_Reset_Manager $resets, DRT_Tools_Manager $tools, DRT_Snapshot_Manager $snapshots, DRT_Collections_Manager $collections, DRT_Reactivation_Manager $reactivation, DRT_Logger $logger, DRT_Settings $settings ) {
		$this->resets = $resets;
		$this->tools = $tools;
		$this->snapshots = $snapshots;
		$this->collections = $collections;
		$this->reactivation = $reactivation;
		$this->logger = $logger;
		$this->settings = $settings;

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_drt_run_reset', array( $this, 'handle_reset' ) );
		add_action( 'admin_post_drt_run_tool', array( $this, 'handle_tool' ) );
		add_action( 'admin_post_drt_snapshot', array( $this, 'handle_snapshot' ) );
		add_action( 'admin_post_drt_reactivate', array( $this, 'handle_reactivate' ) );
		add_action( 'admin_post_drt_collection', array( $this, 'handle_collection' ) );
		add_action( 'admin_post_drt_logs', array( $this, 'handle_logs' ) );
		add_action( 'admin_post_drt_save_settings', array( $this, 'handle_settings' ) );
	}

	public function menu() {
		add_menu_page( 'Dev Reset Toolkit', 'Dev Reset Toolkit', 'manage_options', 'dev-reset-toolkit', array( $this, 'page' ), 'dashicons-update-alt' );
	}

	public function assets( $hook ) {
		if ( 'toplevel_page_dev-reset-toolkit' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'drt-admin', DRT_PLUGIN_URL . 'assets/admin.css', array(), DRT_VERSION );
		wp_enqueue_script( 'drt-admin', DRT_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), DRT_VERSION, true );
		wp_localize_script( 'drt-admin', 'drtAdmin', array( 'resetConfirm' => __( 'This action is destructive. Continue?', 'dev-reset-toolkit' ) ) );
	}

	protected function guard( $action, $nonce_field = 'drt_nonce' ) {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'dev-reset-toolkit' ) );
		}
		check_admin_referer( $action, $nonce_field );
	}

	protected function redirect( $type, $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'dev-reset-toolkit', 'drt_notice' => $type, 'drt_message' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_reset() {
		$this->guard( 'drt_reset_action' );
		if ( 'reset' !== sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) ) ) {
			$this->redirect( 'error', __( 'Type reset to continue.', 'dev-reset-toolkit' ) );
		}
		try {
			$this->resets->run(
				sanitize_text_field( wp_unslash( $_POST['reset_type'] ?? '' ) ),
				array(
					'dry_run'                    => ! empty( $_POST['dry_run'] ),
					'delete_custom_tables'       => ! empty( $_POST['delete_custom_tables'] ),
					'reactivate_theme'           => ! empty( $_POST['reactivate_theme'] ),
					'reactivate_all_plugins'     => ! empty( $_POST['reactivate_plugins'] ),
					'reactivate_selected_plugin' => sanitize_text_field( wp_unslash( $_POST['selected_plugin'] ?? '' ) ),
				)
			);
			$this->redirect( 'success', __( 'Reset completed.', 'dev-reset-toolkit' ) );
		} catch ( Exception $e ) {
			$this->logger->log( array( 'action' => 'reset', 'type' => 'run', 'status' => 'failed', 'error' => $e->getMessage() ) );
			$this->redirect( 'error', $e->getMessage() );
		}
	}

	public function handle_tool() {
		$this->guard( 'drt_tool_action' );
		if ( 'reset' !== sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) ) ) {
			$this->redirect( 'error', __( 'Type reset to run tools.', 'dev-reset-toolkit' ) );
		}
		$this->tools->run(
			sanitize_text_field( wp_unslash( $_POST['tool'] ?? '' ) ),
			array(
				'dry_run'         => ! empty( $_POST['dry_run'] ),
				'selected_files'  => array_map( 'sanitize_text_field', (array) ( $_POST['selected_files'] ?? array() ) ),
				'selected_tables' => array_map( 'sanitize_text_field', (array) ( $_POST['selected_tables'] ?? array() ) ),
				'drop_tables'     => ! empty( $_POST['drop_tables'] ),
			)
		);
		$this->redirect( 'success', __( 'Tool executed.', 'dev-reset-toolkit' ) );
	}

	public function handle_snapshot() {
		$this->guard( 'drt_snapshot_action' );
		$action = sanitize_text_field( wp_unslash( $_POST['snapshot_action'] ?? '' ) );
		$id = sanitize_text_field( wp_unslash( $_POST['snapshot_id'] ?? '' ) );
		if ( 'create' === $action ) {
			$this->snapshots->create();
		} elseif ( 'restore' === $action ) {
			$this->snapshots->restore( $id );
		} elseif ( 'delete' === $action ) {
			$this->snapshots->delete( $id );
		} elseif ( 'export' === $action ) {
			$this->export_snapshot( $id );
		} elseif ( 'compare' === $action ) {
			$this->redirect( 'success', __( 'Snapshot compare complete. Check table counts in snapshot list.', 'dev-reset-toolkit' ) );
		}
		$this->redirect( 'success', __( 'Snapshot action completed.', 'dev-reset-toolkit' ) );
	}

	protected function export_snapshot( $id ) {
		$s = $this->snapshots->all();
		if ( empty( $s[ $id ] ) ) {
			$this->redirect( 'error', __( 'Snapshot not found.', 'dev-reset-toolkit' ) );
		}
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename=' . $id . '.json' );
		echo wp_json_encode( $s[ $id ], JSON_PRETTY_PRINT );
		exit;
	}

	public function handle_reactivate() {
		$this->guard( 'drt_reactivate_action' );
		$action = sanitize_text_field( wp_unslash( $_POST['reactivation_action'] ?? '' ) );
		if ( 'save_state' === $action ) {
			$this->reactivation->store_runtime_snapshot( 'manual' );
		} elseif ( 'restore_state' === $action ) {
			$this->reactivation->reactivate_theme();
			$this->reactivation->reactivate_all_plugins();
		} elseif ( 'theme' === $action ) {
			$this->reactivation->reactivate_theme();
		} elseif ( 'selected' === $action ) {
			$this->reactivation->reactivate_plugin( sanitize_text_field( wp_unslash( $_POST['selected_plugin'] ?? '' ) ) );
		} elseif ( 'all' === $action ) {
			$this->reactivation->reactivate_all_plugins();
		} elseif ( 'recycle' === $action ) {
			$this->reactivation->recycle_plugins();
		}
		$this->redirect( 'success', __( 'Reactivation done.', 'dev-reset-toolkit' ) );
	}

	public function handle_collection() {
		$this->guard( 'drt_collection_action' );
		$action = sanitize_text_field( wp_unslash( $_POST['collection_action'] ?? '' ) );
		$id = sanitize_text_field( wp_unslash( $_POST['collection_id'] ?? '' ) );
		if ( 'create' === $action ) {
			$this->collections->create( sanitize_text_field( wp_unslash( $_POST['collection_name'] ?? '' ) ) );
		} elseif ( 'rename' === $action ) {
			$this->collections->rename( $id, sanitize_text_field( wp_unslash( $_POST['collection_name'] ?? '' ) ) );
		} elseif ( 'delete' === $action ) {
			$this->collections->delete( $id );
		} elseif ( 'add_plugin' === $action ) {
			$this->collections->add_plugin( $id, sanitize_text_field( wp_unslash( $_POST['selected_plugin'] ?? '' ) ) );
		} elseif ( 'add_theme' === $action ) {
			$this->collections->add_theme( $id, sanitize_text_field( wp_unslash( $_POST['selected_theme'] ?? '' ) ) );
		} elseif ( 'install_activate' === $action ) {
			$this->collections->install_and_activate( $id );
		} elseif ( 'export' === $action ) {
			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename=' . $id . '-collection.json' );
			echo $this->collections->export_json( $id );
			exit;
		} elseif ( 'import' === $action ) {
			$this->collections->import_json( sanitize_textarea_field( wp_unslash( $_POST['collection_json'] ?? '' ) ) );
		}
		$this->redirect( 'success', __( 'Collection action completed.', 'dev-reset-toolkit' ) );
	}

	public function handle_logs() {
		$this->guard( 'drt_logs_action' );
		$action = sanitize_text_field( wp_unslash( $_POST['logs_action'] ?? '' ) );
		if ( 'clear' === $action ) {
			$this->logger->clear();
			$this->redirect( 'success', __( 'Logs cleared.', 'dev-reset-toolkit' ) );
		}
		if ( 'export' === $action ) {
			$logs = $this->logger->all();
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename=dev-reset-toolkit-logs.csv' );
			$output = fopen( 'php://output', 'w' );
			fputcsv( $output, array( 'timestamp', 'user_id', 'user', 'action', 'type', 'status', 'error', 'ip' ) );
			foreach ( $logs as $log ) {
				fputcsv( $output, array( $log['timestamp'], $log['user_id'], $log['user'], $log['action'], $log['type'], $log['status'], $log['error'], $log['ip'] ) );
			}
			fclose( $output );
			exit;
		}
	}

	public function handle_settings() {
		$this->guard( 'drt_settings_action' );
		$this->settings->update( wp_unslash( $_POST ) );
		$this->redirect( 'success', __( 'Settings saved.', 'dev-reset-toolkit' ) );
	}

	public function page() {
		$notice_type = sanitize_text_field( wp_unslash( $_GET['drt_notice'] ?? '' ) );
		$notice_msg = sanitize_text_field( urldecode( wp_unslash( $_GET['drt_message'] ?? '' ) ) );
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		$themes = wp_get_themes();
		$snapshots = $this->snapshots->all();
		$collections = $this->collections->all();
		$settings = $this->settings->get_all();
		$runtime = $this->reactivation->get_runtime_snapshot();
		?>
		<div class="wrap drt-wrap">
			<h1>Dev Reset Toolkit</h1>
			<?php if ( is_multisite() ) : ?><div class="notice notice-warning"><p><?php esc_html_e( 'Dangerous tools are limited on multisite.', 'dev-reset-toolkit' ); ?></p></div><?php endif; ?>
			<?php if ( $notice_type && $notice_msg ) : ?><div class="notice notice-<?php echo esc_attr( $notice_type ); ?>"><p><?php echo esc_html( $notice_msg ); ?></p></div><?php endif; ?>
			<div class="drt-tabs">
				<button class="drt-tab active" data-tab="reset">Reset</button><button class="drt-tab" data-tab="tools">Tools</button><button class="drt-tab" data-tab="snapshots">Snapshots</button><button class="drt-tab" data-tab="collections">Collections</button><button class="drt-tab" data-tab="reactivation">Reactivation</button><button class="drt-tab" data-tab="logs">Logs</button><button class="drt-tab" data-tab="settings">Settings</button>
			</div>

			<section class="drt-panel active" id="drt-panel-reset">
				<div class="drt-warning-box"><strong>Warning:</strong> destructive operations ahead. Type <code>reset</code> to continue.</div>
				<table class="widefat striped"><thead><tr><th>Item</th><th>Options</th><th>Site</th><th>Nuclear</th></tr></thead><tbody><tr><td>Posts</td><td>Keep</td><td>Delete</td><td>Delete</td></tr><tr><td>Uploads files</td><td>Keep</td><td>Keep</td><td>Delete</td></tr><tr><td>Users</td><td>Keep</td><td>Keep</td><td>Delete except current</td></tr></tbody></table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="drt-reset-form"><input type="hidden" name="action" value="drt_run_reset" /><?php wp_nonce_field( 'drt_reset_action', 'drt_nonce' ); ?>
				<p><select name="reset_type"><option value="">Select reset type</option><option value="options_reset">Options Reset</option><option value="site_reset">Site Reset</option><option value="nuclear_reset">Nuclear Reset</option></select></p>
				<p><label><input type="checkbox" name="dry_run" value="1" /> Dry-run</label> <label><input type="checkbox" name="delete_custom_tables" value="1" /> Drop custom tables (nuclear)</label></p>
				<p><label><input type="checkbox" name="reactivate_theme" value="1" /> Reactivate current theme</label> <label><input type="checkbox" name="reactivate_plugins" value="1" /> Reactivate current plugins</label></p>
				<p><select name="selected_plugin"><option value="">Selected plugin reactivation</option><?php foreach ( $plugins as $file => $p ) : ?><option value="<?php echo esc_attr( $file ); ?>"><?php echo esc_html( $p['Name'] ); ?></option><?php endforeach; ?></select></p>
				<p><input type="text" id="drt_confirm" name="confirm" placeholder="Type reset" /></p><p><button class="button button-primary" id="drt_run_reset" disabled>Run Reset</button></p></form>
			</section>

			<section class="drt-panel" id="drt-panel-tools">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="drt-tool-form"><input type="hidden" name="action" value="drt_run_tool" /><?php wp_nonce_field( 'drt_tool_action', 'drt_nonce' ); ?>
				<p><select name="tool"><option value="reset_theme_options">Reset Theme Options</option><option value="reset_user_roles">Reset User Roles</option><option value="delete_transients">Delete Transients</option><option value="purge_cache">Purge Cache</option><option value="delete_content">Delete Content</option><option value="delete_widgets">Delete Widgets</option><option value="delete_themes_except_active">Delete Themes</option><option value="delete_plugins_except_self">Delete Plugins</option><option value="delete_mu_dropins">Delete MU Plugins & Drop-ins</option><option value="clean_uploads_orphans">Clean uploads Folder</option><option value="clean_wp_content">Clean wp-content Folder</option><option value="custom_tables">Empty/Delete Custom Tables</option><option value="delete_htaccess">Delete .htaccess File</option></select></p>
				<p><label><input type="checkbox" name="dry_run" value="1" /> Dry-run</label> <label><input type="checkbox" name="drop_tables" value="1" /> For custom tables: DROP instead of TRUNCATE</label></p>
				<p><input type="text" name="confirm" placeholder="Type reset" /></p>
				<p><button class="button">Run Tool</button> <button type="button" class="button" id="drt-clear-local-data">Delete Local Data (Browser)</button></p>
				</form>
			</section>

			<section class="drt-panel" id="drt-panel-snapshots">
				<p>Create snapshots manually. No cloud services are used.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="drt_snapshot" /><?php wp_nonce_field( 'drt_snapshot_action', 'drt_nonce' ); ?><input type="hidden" name="snapshot_action" value="create" /><button class="button">Create Snapshot</button></form>
				<table class="widefat striped"><thead><tr><th>Name</th><th>Created</th><th>Tables</th><th>Size</th><th>Actions</th></tr></thead><tbody><?php foreach ( $snapshots as $snap ) : ?><tr><td><?php echo esc_html( $snap['id'] ); ?></td><td><?php echo esc_html( $snap['created'] ); ?></td><td><?php echo esc_html( (string) $snap['table_cnt'] ); ?></td><td><?php echo esc_html( size_format( strlen( wp_json_encode( $snap['data'] ) ) ) ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"><?php wp_nonce_field( 'drt_snapshot_action', 'drt_nonce' ); ?><input type="hidden" name="action" value="drt_snapshot"/><input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $snap['id'] ); ?>"/><button class="button" name="snapshot_action" value="restore">Restore</button><button class="button" name="snapshot_action" value="export">Export</button><button class="button" name="snapshot_action" value="compare">Compare</button><button class="button" name="snapshot_action" value="delete">Delete</button></form></td></tr><?php endforeach; ?></tbody></table>
			</section>

			<section class="drt-panel" id="drt-panel-collections">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="drt_collection" /><?php wp_nonce_field( 'drt_collection_action', 'drt_nonce' ); ?><input type="hidden" name="collection_action" value="create" /><input type="text" name="collection_name" placeholder="Collection name" /><button class="button">Create Collection</button></form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="drt_collection" /><?php wp_nonce_field( 'drt_collection_action', 'drt_nonce' ); ?><input type="hidden" name="collection_action" value="import" /><textarea name="collection_json" rows="5" style="width:100%" placeholder="Paste collection JSON"></textarea><button class="button">Import JSON</button></form>
				<table class="widefat striped"><thead><tr><th>Name</th><th>Plugins</th><th>Themes</th><th>Actions</th></tr></thead><tbody><?php foreach ( $collections as $col ) : ?><tr><td><?php echo esc_html( $col['name'] ); ?></td><td><?php echo esc_html( implode( ', ', $col['plugins'] ) ); ?></td><td><?php echo esc_html( implode( ', ', $col['themes'] ) ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"><?php wp_nonce_field( 'drt_collection_action', 'drt_nonce' ); ?><input type="hidden" name="action" value="drt_collection"/><input type="hidden" name="collection_id" value="<?php echo esc_attr( $col['id'] ); ?>"/><input type="text" name="collection_name" placeholder="Rename" /><button class="button" name="collection_action" value="rename">Rename</button><button class="button" name="collection_action" value="install_activate">Install & Activate</button><button class="button" name="collection_action" value="export">Export</button><button class="button" name="collection_action" value="delete">Delete</button><br><select name="selected_plugin"><option value="">Add plugin</option><?php foreach ( $plugins as $file => $plugin_data ) : ?><option value="<?php echo esc_attr( $file ); ?>"><?php echo esc_html( $plugin_data['Name'] ); ?></option><?php endforeach; ?></select><button class="button" name="collection_action" value="add_plugin">Add Plugin</button><select name="selected_theme"><option value="">Add theme</option><?php foreach ( $themes as $slug => $theme_obj ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $theme_obj->get( 'Name' ) ); ?></option><?php endforeach; ?></select><button class="button" name="collection_action" value="add_theme">Add Theme</button></form></td></tr><?php endforeach; ?></tbody></table>
			</section>

			<section class="drt-panel" id="drt-panel-reactivation">
				<p>Current theme: <strong><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></strong></p>
				<p>Saved state: <?php echo esc_html( wp_json_encode( $runtime ) ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="drt_reactivate" /><?php wp_nonce_field( 'drt_reactivate_action', 'drt_nonce' ); ?><p><select name="reactivation_action"><option value="save_state">Save current activation state</option><option value="restore_state">Restore saved activation state</option><option value="theme">Reactivate current theme</option><option value="selected">Reactivate selected plugin</option><option value="all">Reactivate all previous plugins</option><option value="recycle">Deactivate all then reactivate previous plugins</option></select></p><p><select name="selected_plugin"><option value="">Select plugin</option><?php foreach ( $plugins as $file => $p ) : ?><option value="<?php echo esc_attr( $file ); ?>"><?php echo esc_html( $p['Name'] ); ?></option><?php endforeach; ?></select></p><p><button class="button">Run Reactivation</button></p></form>
			</section>

			<section class="drt-panel" id="drt-panel-logs">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:10px"><input type="hidden" name="action" value="drt_logs" /><?php wp_nonce_field( 'drt_logs_action', 'drt_nonce' ); ?><button class="button" name="logs_action" value="export">Export CSV</button> <button class="button" name="logs_action" value="clear">Clear Logs</button></form>
				<table class="widefat striped"><thead><tr><th>Date</th><th>User ID</th><th>User</th><th>Action</th><th>Type</th><th>Status</th><th>Error</th><th>IP</th></tr></thead><tbody><?php foreach ( $this->logger->all() as $log ) : ?><tr><td><?php echo esc_html( $log['timestamp'] ); ?></td><td><?php echo esc_html( (string) $log['user_id'] ); ?></td><td><?php echo esc_html( $log['user'] ); ?></td><td><?php echo esc_html( $log['action'] ); ?></td><td><?php echo esc_html( $log['type'] ); ?></td><td><?php echo esc_html( $log['status'] ); ?></td><td><?php echo esc_html( $log['error'] ); ?></td><td><?php echo esc_html( $log['ip'] ); ?></td></tr><?php endforeach; ?></tbody></table>
			</section>

			<section class="drt-panel" id="drt-panel-settings">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="drt_save_settings" /><?php wp_nonce_field( 'drt_settings_action', 'drt_nonce' ); ?>
				<p><label><input type="checkbox" name="enable_logs" value="1" <?php checked( 1, (int) $settings['enable_logs'] ); ?> /> Enable logs</label></p>
				<p><label><input type="checkbox" name="enable_dry_run" value="1" <?php checked( 1, (int) $settings['enable_dry_run'] ); ?> /> Enable dry-run by default</label></p>
				<p><label><input type="checkbox" name="keep_plugin_active" value="1" <?php checked( 1, (int) $settings['keep_plugin_active'] ); ?> /> Keep this plugin active after reset</label></p>
				<p><label>Maximum snapshots <input type="number" min="1" name="max_snapshots" value="<?php echo esc_attr( (string) $settings['max_snapshots'] ); ?>" /></label></p>
				<p><label><input type="checkbox" name="cleanup_on_uninstall" value="1" <?php checked( 1, (int) $settings['cleanup_on_uninstall'] ); ?> /> Delete plugin data on uninstall</label></p>
				<p><label><input type="checkbox" name="enable_advanced_tools" value="1" <?php checked( 1, (int) $settings['enable_advanced_tools'] ); ?> /> Enable advanced dangerous tools</label></p>
				<p><label><input type="checkbox" name="show_confirm_modals" value="1" <?php checked( 1, (int) $settings['show_confirm_modals'] ); ?> /> Show confirmation modals</label></p>
				<p><button class="button button-primary">Save Settings</button></p></form>
			</section>
		</div>
		<?php
	}
}
