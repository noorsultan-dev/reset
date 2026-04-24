<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DRT_Admin {
	protected $resets;
	protected $tools;
	protected $snapshots;
	protected $reactivation;
	protected $logger;
	protected $settings;

	public function __construct( DRT_Reset_Manager $resets, DRT_Tools_Manager $tools, DRT_Snapshot_Manager $snapshots, DRT_Reactivation_Manager $reactivation, DRT_Logger $logger, DRT_Settings $settings ) {
		$this->resets = $resets;
		$this->tools = $tools;
		$this->snapshots = $snapshots;
		$this->reactivation = $reactivation;
		$this->logger = $logger;
		$this->settings = $settings;

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_drt_run_reset', array( $this, 'handle_reset' ) );
		add_action( 'admin_post_drt_run_tool', array( $this, 'handle_tool' ) );
		add_action( 'admin_post_drt_snapshot', array( $this, 'handle_snapshot' ) );
		add_action( 'admin_post_drt_reactivate', array( $this, 'handle_reactivate' ) );
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
		wp_localize_script(
			'drt-admin',
			'drtAdmin',
			array(
				'resetConfirm' => __( 'This action is destructive. Continue?', 'dev-reset-toolkit' ),
			)
		);
	}

	protected function redirect( $type, $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'dev-reset-toolkit', 'drt_notice' => $type, 'drt_message' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_reset() {
		$safety = new DRT_Safety();
		$safety->require_capability_and_nonce( 'drt_reset_action', 'drt_nonce' );
		$confirm = sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) );
		if ( 'reset' !== $confirm ) {
			$this->redirect( 'error', __( 'Type reset to continue.', 'dev-reset-toolkit' ) );
		}
		try {
			$this->resets->run(
				sanitize_text_field( wp_unslash( $_POST['reset_type'] ?? '' ) ),
				array(
					'dry_run'                   => ! empty( $_POST['dry_run'] ),
					'delete_custom_tables'      => ! empty( $_POST['delete_custom_tables'] ),
					'reactivate_theme'          => ! empty( $_POST['reactivate_theme'] ),
					'reactivate_all_plugins'    => ! empty( $_POST['reactivate_plugins'] ),
					'reactivate_selected_plugin'=> sanitize_text_field( wp_unslash( $_POST['selected_plugin'] ?? '' ) ),
				)
			);
			$this->redirect( 'success', __( 'Reset completed.', 'dev-reset-toolkit' ) );
		} catch ( Exception $e ) {
			$this->logger->log( array( 'action' => 'reset', 'type' => 'error', 'status' => 'failed', 'error' => $e->getMessage() ) );
			$this->redirect( 'error', $e->getMessage() );
		}
	}

	public function handle_tool() {
		$safety = new DRT_Safety();
		$safety->require_capability_and_nonce( 'drt_tool_action', 'drt_nonce' );
		if ( 'reset' !== sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) ) ) {
			$this->redirect( 'error', __( 'Type reset to run tools.', 'dev-reset-toolkit' ) );
		}
		$this->tools->run( sanitize_text_field( wp_unslash( $_POST['tool'] ?? '' ) ), array( 'dry_run' => ! empty( $_POST['dry_run'] ) ) );
		$this->redirect( 'success', __( 'Tool executed.', 'dev-reset-toolkit' ) );
	}

	public function handle_snapshot() {
		$safety = new DRT_Safety();
		$safety->require_capability_and_nonce( 'drt_snapshot_action', 'drt_nonce' );
		$action = sanitize_text_field( wp_unslash( $_POST['snapshot_action'] ?? '' ) );
		$id = sanitize_text_field( wp_unslash( $_POST['snapshot_id'] ?? '' ) );
		if ( 'create' === $action ) {
			$this->snapshots->create();
		} elseif ( 'restore' === $action ) {
			$this->snapshots->restore( $id );
		} elseif ( 'delete' === $action ) {
			$this->snapshots->delete( $id );
		}
		$this->redirect( 'success', __( 'Snapshot action completed.', 'dev-reset-toolkit' ) );
	}

	public function handle_reactivate() {
		$safety = new DRT_Safety();
		$safety->require_capability_and_nonce( 'drt_reactivate_action', 'drt_nonce' );
		$action = sanitize_text_field( wp_unslash( $_POST['reactivation_action'] ?? '' ) );
		if ( 'theme' === $action ) {
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

	public function handle_settings() {
		$safety = new DRT_Safety();
		$safety->require_capability_and_nonce( 'drt_settings_action', 'drt_nonce' );
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
		$snapshots = $this->snapshots->all();
		$settings = $this->settings->get_all();
		?>
		<div class="wrap drt-wrap">
			<h1>Dev Reset Toolkit</h1>
			<?php if ( $notice_type && $notice_msg ) : ?><div class="notice notice-<?php echo esc_attr( $notice_type ); ?>"><p><?php echo esc_html( $notice_msg ); ?></p></div><?php endif; ?>
			<div class="drt-tabs">
				<button class="drt-tab active" data-tab="reset">Reset</button>
				<button class="drt-tab" data-tab="tools">Tools</button>
				<button class="drt-tab" data-tab="snapshots">Snapshots</button>
				<button class="drt-tab" data-tab="reactivation">Reactivation</button>
				<button class="drt-tab" data-tab="logs">Logs</button>
				<button class="drt-tab" data-tab="settings">Settings</button>
			</div>

			<section class="drt-panel active" id="drt-panel-reset">
				<div class="drt-warning-box"><strong>Warning:</strong> Always backup your site before destructive resets.</div>
				<table class="widefat striped"><thead><tr><th>Item</th><th>Options Reset</th><th>Site Reset</th><th>Nuclear Reset</th></tr></thead><tbody>
				<tr><td>Posts/Pages</td><td>Keep</td><td>Delete</td><td>Delete</td></tr>
				<tr><td>Media Files</td><td>Keep</td><td>Keep files, delete DB records</td><td>Delete</td></tr>
				<tr><td>Users</td><td>Keep</td><td>Keep</td><td>Delete except current admin</td></tr>
				</tbody></table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="drt-reset-form">
					<input type="hidden" name="action" value="drt_run_reset" />
					<?php wp_nonce_field( 'drt_reset_action', 'drt_nonce' ); ?>
					<p><select name="reset_type" id="drt_reset_type"><option value="">Select reset type</option><option value="options_reset">Options Reset</option><option value="site_reset">Site Reset</option><option value="nuclear_reset">Nuclear Reset</option></select></p>
					<p><label><input type="checkbox" name="dry_run" value="1" /> Dry-run</label> <label><input type="checkbox" name="delete_custom_tables" value="1" /> Nuclear: delete custom prefix tables</label></p>
					<p><label><input type="checkbox" name="reactivate_theme" value="1" /> Reactivate current theme</label><br><label><input type="checkbox" name="reactivate_plugins" value="1" /> Reactivate all current plugins</label></p>
					<p><select name="selected_plugin"><option value="">Select plugin to reactivate</option><?php foreach ( $plugins as $file => $p ) : ?><option value="<?php echo esc_attr( $file ); ?>"><?php echo esc_html( $p['Name'] ); ?></option><?php endforeach; ?></select></p>
					<p><input type="text" id="drt_confirm" name="confirm" placeholder="Type reset" /></p>
					<p><button class="button button-primary" id="drt_run_reset" disabled>Run Reset</button></p>
				</form>
			</section>

			<section class="drt-panel" id="drt-panel-tools">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="drt-tool-form">
					<input type="hidden" name="action" value="drt_run_tool" /><?php wp_nonce_field( 'drt_tool_action', 'drt_nonce' ); ?>
					<p><select name="tool"><option value="reset_theme_options">Reset theme options</option><option value="reset_user_roles">Reset user roles</option><option value="delete_transients">Delete transients</option><option value="purge_cache">Purge cache</option><option value="delete_widgets">Delete widgets</option><option value="delete_themes_except_active">Delete themes except active</option><option value="delete_plugins_except_self">Delete plugins except this plugin</option><option value="delete_htaccess">Delete .htaccess</option></select></p>
					<p><label><input type="checkbox" name="dry_run" value="1" /> Dry-run</label></p><p><input type="text" name="confirm" placeholder="Type reset" /></p><p><button class="button">Run Tool</button></p>
				</form>
			</section>

			<section class="drt-panel" id="drt-panel-snapshots">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="drt_snapshot" /><?php wp_nonce_field( 'drt_snapshot_action', 'drt_nonce' ); ?><input type="hidden" name="snapshot_action" value="create" /><button class="button">Create Snapshot</button></form>
				<table class="widefat striped"><thead><tr><th>ID</th><th>Created</th><th>Tables</th><th>Actions</th></tr></thead><tbody><?php foreach ( $snapshots as $snap ) : ?><tr><td><?php echo esc_html( $snap['id'] ); ?></td><td><?php echo esc_html( $snap['created'] ); ?></td><td><?php echo esc_html( (string) $snap['table_cnt'] ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;"><?php wp_nonce_field( 'drt_snapshot_action', 'drt_nonce' ); ?><input type="hidden" name="action" value="drt_snapshot"/><input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $snap['id'] ); ?>"/><button name="snapshot_action" value="restore" class="button">Restore</button><button name="snapshot_action" value="delete" class="button">Delete</button></form></td></tr><?php endforeach; ?></tbody></table>
			</section>

			<section class="drt-panel" id="drt-panel-reactivation">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="drt_reactivate" /><?php wp_nonce_field( 'drt_reactivate_action', 'drt_nonce' ); ?>
				<p><select name="reactivation_action"><option value="theme">Reactivate current theme</option><option value="selected">Reactivate selected plugin</option><option value="all">Reactivate all previous plugins</option><option value="recycle">Deactivate all then reactivate previous plugins</option></select></p>
				<p><select name="selected_plugin"><option value="">Select plugin</option><?php foreach ( $plugins as $file => $p ) : ?><option value="<?php echo esc_attr( $file ); ?>"><?php echo esc_html( $p['Name'] ); ?></option><?php endforeach; ?></select></p><p><button class="button">Run Reactivation</button></p></form>
			</section>

			<section class="drt-panel" id="drt-panel-logs">
				<table class="widefat striped"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Type</th><th>Status</th><th>Error</th></tr></thead><tbody><?php foreach ( $this->logger->all() as $log ) : ?><tr><td><?php echo esc_html( $log['timestamp'] ); ?></td><td><?php echo esc_html( $log['user'] ); ?></td><td><?php echo esc_html( $log['action'] ); ?></td><td><?php echo esc_html( $log['type'] ); ?></td><td><?php echo esc_html( $log['status'] ); ?></td><td><?php echo esc_html( $log['error'] ); ?></td></tr><?php endforeach; ?></tbody></table>
			</section>

			<section class="drt-panel" id="drt-panel-settings">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="drt_save_settings" /><?php wp_nonce_field( 'drt_settings_action', 'drt_nonce' ); ?>
				<p><label><input type="checkbox" name="enable_logs" value="1" <?php checked( 1, (int) $settings['enable_logs'] ); ?> /> Enable logs</label></p>
				<p><label><input type="checkbox" name="enable_dry_run" value="1" <?php checked( 1, (int) $settings['enable_dry_run'] ); ?> /> Enable dry-run mode globally</label></p>
				<p><label><input type="checkbox" name="keep_plugin_active" value="1" <?php checked( 1, (int) $settings['keep_plugin_active'] ); ?> /> Keep this plugin active after reset</label></p>
				<p><label>Max snapshots <input type="number" min="1" name="max_snapshots" value="<?php echo esc_attr( (string) $settings['max_snapshots'] ); ?>" /></label></p>
				<p><label><input type="checkbox" name="cleanup_on_uninstall" value="1" <?php checked( 1, (int) $settings['cleanup_on_uninstall'] ); ?> /> Cleanup plugin data on uninstall</label></p>
				<p><button class="button button-primary">Save Settings</button></p></form>
			</section>
		</div>
		<?php
	}
}
