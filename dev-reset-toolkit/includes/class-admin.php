<?php
/**
 * Admin UI.
 *
 * @package DevResetToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page controller.
 */
class DRT_Admin {
	/**
	 * Reset manager.
	 *
	 * @var DRT_Reset_Manager
	 */
	protected $reset_manager;

	/**
	 * Reactivation manager.
	 *
	 * @var DRT_Reactivation_Manager
	 */
	protected $reactivation_manager;

	/**
	 * Logger.
	 *
	 * @var DRT_Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param DRT_Reset_Manager        $reset_manager Reset manager.
	 * @param DRT_Reactivation_Manager $reactivation_manager Reactivation manager.
	 * @param DRT_Logger               $logger Logger.
	 */
	public function __construct( DRT_Reset_Manager $reset_manager, DRT_Reactivation_Manager $reactivation_manager, DRT_Logger $logger ) {
		$this->reset_manager = $reset_manager;
		$this->reactivation_manager = $reactivation_manager;
		$this->logger = $logger;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_drt_run_reset', array( $this, 'handle_run_reset' ) );
		add_action( 'admin_post_drt_reactivation_action', array( $this, 'handle_reactivation_action' ) );
	}

	/**
	 * Register menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Dev Reset Toolkit', 'dev-reset-toolkit' ),
			__( 'Dev Reset Toolkit', 'dev-reset-toolkit' ),
			'manage_options',
			'dev-reset-toolkit',
			array( $this, 'render_page' ),
			'dashicons-update-alt'
		);
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_dev-reset-toolkit' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'drt-admin', DRT_PLUGIN_URL . 'assets/admin.css', array(), DRT_VERSION );
		wp_enqueue_script( 'drt-admin', DRT_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), DRT_VERSION, true );
		wp_localize_script(
			'drt-admin',
			'drtAdmin',
			array(
				'confirmMessages' => array(
					'options_reset' => __( 'Options Reset will remove most options, transients, widgets and customizer values. Continue?', 'dev-reset-toolkit' ),
					'site_reset'    => __( 'Site Reset will delete posts, pages, comments, media records, menus, and many settings. Continue?', 'dev-reset-toolkit' ),
					'nuclear_reset' => __( 'Nuclear Reset will wipe content, uploads files, users except current admin, and optionally custom plugin tables. Continue?', 'dev-reset-toolkit' ),
				),
			)
		);
	}

	/**
	 * Handle reset form submission.
	 *
	 * @return void
	 */
	public function handle_run_reset() {
		if ( ! is_admin() ) {
			wp_die( esc_html__( 'Invalid request context.', 'dev-reset-toolkit' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'dev-reset-toolkit' ) );
		}

		check_admin_referer( 'drt_run_reset_nonce', 'drt_nonce' );

		$confirm = isset( $_POST['drt_confirm_word'] ) ? sanitize_text_field( wp_unslash( $_POST['drt_confirm_word'] ) ) : '';
		$reset_type = isset( $_POST['drt_reset_type'] ) ? sanitize_text_field( wp_unslash( $_POST['drt_reset_type'] ) ) : '';
		$allowed_reset_types = array( 'options_reset', 'site_reset', 'nuclear_reset' );

		if ( 'reset' !== $confirm ) {
			$this->redirect_with_notice( 'error', __( 'You must type exactly "reset" to run this action.', 'dev-reset-toolkit' ) );
		}

		if ( empty( $reset_type ) || ! in_array( $reset_type, $allowed_reset_types, true ) ) {
			$this->redirect_with_notice( 'error', __( 'Please select a reset type.', 'dev-reset-toolkit' ) );
		}

		$args = array(
			'reactivate_theme'           => ! empty( $_POST['drt_reactivate_theme'] ),
			'reactivate_all_plugins'     => ! empty( $_POST['drt_reactivate_plugins'] ),
			'reactivate_selected_plugin' => isset( $_POST['drt_selected_plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['drt_selected_plugin'] ) ) : '',
			'dry_run'                    => ! empty( $_POST['drt_dry_run'] ),
			'include_custom_tables'      => ! empty( $_POST['drt_include_custom_tables'] ),
		);

		$result = $this->reset_manager->run_reset( $reset_type, $args );
		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'success', __( 'Reset completed successfully.', 'dev-reset-toolkit' ) );
		}

		$this->redirect_with_notice( 'error', implode( '; ', $result['errors'] ) );
	}

	/**
	 * Handle reactivation tab actions.
	 *
	 * @return void
	 */
	public function handle_reactivation_action() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Invalid permissions.', 'dev-reset-toolkit' ) );
		}

		check_admin_referer( 'drt_reactivation_nonce', 'drt_nonce' );

		$action = isset( $_POST['drt_reactivation_action'] ) ? sanitize_text_field( wp_unslash( $_POST['drt_reactivation_action'] ) ) : '';
		$plugin = isset( $_POST['drt_selected_plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['drt_selected_plugin'] ) ) : '';
		$prefix = isset( $_POST['drt_plugin_option_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['drt_plugin_option_prefix'] ) ) : '';
		$allowed_actions = array( 'theme', 'plugin', 'all_plugins', 'recycle_plugins', 'reset_plugin' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			$this->redirect_with_notice( 'error', __( 'Invalid reactivation action requested.', 'dev-reset-toolkit' ) );
		}

		$response = null;
		switch ( $action ) {
			case 'theme':
				$response = $this->reactivation_manager->reactivate_previous_theme();
				break;
			case 'plugin':
				$response = $this->reactivation_manager->reactivate_selected_plugin( $plugin );
				break;
			case 'all_plugins':
				$response = $this->reactivation_manager->reactivate_previous_plugins();
				break;
			case 'recycle_plugins':
				$response = $this->reactivation_manager->recycle_plugins();
				break;
			case 'reset_plugin':
				$response = $this->reactivation_manager->reset_selected_plugin( $plugin, $prefix );
				break;
		}

		$has_error = is_wp_error( $response ) || ( is_array( $response ) && ! empty( $response['errors'] ) );
		$this->logger->log(
			array(
				'action'     => 'reactivation',
				'reset_type' => $action,
				'success'    => ! $has_error,
				'errors'     => is_wp_error( $response ) ? array( $response->get_error_message() ) : ( $response['errors'] ?? array() ),
			),
		);

		if ( $has_error ) {
			$this->redirect_with_notice( 'error', __( 'Reactivation action completed with errors.', 'dev-reset-toolkit' ) );
		}
		$this->redirect_with_notice( 'success', __( 'Reactivation action completed.', 'dev-reset-toolkit' ) );
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'reset';
		$notice_type = isset( $_GET['drt_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['drt_notice'] ) ) : '';
		$notice_msg = isset( $_GET['drt_message'] ) ? sanitize_text_field( urldecode( wp_unslash( $_GET['drt_message'] ) ) ) : '';
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugins = get_plugins();
		}
		?>
		<div class="wrap drt-wrap">
			<h1><?php esc_html_e( 'Dev Reset Toolkit', 'dev-reset-toolkit' ); ?></h1>
			<p class="drt-subheading"><?php esc_html_e( 'Safe reset toolkit for developers. Always back up your database and files before destructive actions.', 'dev-reset-toolkit' ); ?></p>

			<?php if ( $notice_type && $notice_msg ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice_msg ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<?php
				$tabs = array(
					'reset'        => __( 'Reset', 'dev-reset-toolkit' ),
					'tools'        => __( 'Tools', 'dev-reset-toolkit' ),
					'snapshots'    => __( 'Snapshots', 'dev-reset-toolkit' ),
					'reactivation' => __( 'Reactivation', 'dev-reset-toolkit' ),
					'logs'         => __( 'Logs', 'dev-reset-toolkit' ),
					'settings'     => __( 'Settings', 'dev-reset-toolkit' ),
				);
				foreach ( $tabs as $tab_key => $tab_label ) {
					$tab_class = ( $active_tab === $tab_key ) ? ' nav-tab-active' : '';
					printf( '<a class="nav-tab%s" href="%s">%s</a>', esc_attr( $tab_class ), esc_url( admin_url( 'admin.php?page=dev-reset-toolkit&tab=' . $tab_key ) ), esc_html( $tab_label ) );
				}
				?>
			</h2>

			<div class="drt-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'tools':
						$this->render_tools_tab();
						break;
					case 'snapshots':
						$this->render_snapshots_tab();
						break;
					case 'reactivation':
						$this->render_reactivation_tab( $plugins );
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
					case 'settings':
						$this->render_settings_tab();
						break;
					case 'reset':
					default:
						$this->render_reset_tab( $plugins );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render reset tab.
	 *
	 * @param array $plugins Installed plugins.
	 * @return void
	 */
	protected function render_reset_tab( $plugins ) {
		?>
		<div class="drt-warning-box">
			<h3><?php esc_html_e( 'Warning', 'dev-reset-toolkit' ); ?></h3>
			<p><?php esc_html_e( 'Resets can remove large portions of your site data. Use dry-run first and create full backups before running a destructive reset.', 'dev-reset-toolkit' ); ?></p>
		</div>

		<table class="widefat striped drt-comparison-table">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Item', 'dev-reset-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Options Reset', 'dev-reset-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Site Reset', 'dev-reset-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Nuclear Reset', 'dev-reset-toolkit' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<tr><td><?php esc_html_e( 'Options / settings', 'dev-reset-toolkit' ); ?></td><td><span class="drt-delete">✖ Deleted</span></td><td><span class="drt-delete">✖ Deleted</span></td><td><span class="drt-delete">✖ Deleted</span></td></tr>
			<tr><td><?php esc_html_e( 'Posts / pages / CPT', 'dev-reset-toolkit' ); ?></td><td><span class="drt-keep">✔ Not touched</span></td><td><span class="drt-delete">✖ Deleted</span></td><td><span class="drt-delete">✖ Deleted</span></td></tr>
			<tr><td><?php esc_html_e( 'Uploads files', 'dev-reset-toolkit' ); ?></td><td><span class="drt-keep">✔ Not touched</span></td><td><span class="drt-keep">✔ Not touched</span></td><td><span class="drt-delete">✖ Deleted</span></td></tr>
			<tr><td><?php esc_html_e( 'Users', 'dev-reset-toolkit' ); ?></td><td><span class="drt-keep">✔ Not touched</span></td><td><span class="drt-keep">✔ Current user kept</span></td><td><span class="drt-delete">✖ All except current admin</span></td></tr>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="drt-reset-form" class="drt-card">
			<input type="hidden" name="action" value="drt_run_reset">
			<?php wp_nonce_field( 'drt_run_reset_nonce', 'drt_nonce' ); ?>

			<label for="drt_reset_type"><strong><?php esc_html_e( 'Reset type', 'dev-reset-toolkit' ); ?></strong></label>
			<select id="drt_reset_type" name="drt_reset_type" required>
				<option value=""><?php esc_html_e( 'Select reset type', 'dev-reset-toolkit' ); ?></option>
				<option value="options_reset"><?php esc_html_e( 'Options Reset', 'dev-reset-toolkit' ); ?></option>
				<option value="site_reset"><?php esc_html_e( 'Site Reset', 'dev-reset-toolkit' ); ?></option>
				<option value="nuclear_reset"><?php esc_html_e( 'Nuclear Reset', 'dev-reset-toolkit' ); ?></option>
			</select>

			<div class="drt-inline-options">
				<label><input type="checkbox" name="drt_dry_run" value="1"> <?php esc_html_e( 'Dry-run mode (show what would be deleted only)', 'dev-reset-toolkit' ); ?></label>
				<label><input type="checkbox" name="drt_reactivate_theme" value="1"> <?php esc_html_e( 'Reactivate current theme after reset', 'dev-reset-toolkit' ); ?></label>
				<label><input type="checkbox" name="drt_reactivate_plugins" value="1"> <?php esc_html_e( 'Reactivate all current plugins after reset', 'dev-reset-toolkit' ); ?></label>
				<label><input type="checkbox" name="drt_include_custom_tables" value="1"> <?php esc_html_e( 'Nuclear: include custom plugin tables (dangerous)', 'dev-reset-toolkit' ); ?></label>
			</div>

			<label for="drt_selected_plugin"><strong><?php esc_html_e( 'Selected plugin to reactivate (optional)', 'dev-reset-toolkit' ); ?></strong></label>
			<select id="drt_selected_plugin" name="drt_selected_plugin">
				<option value=""><?php esc_html_e( 'No selected plugin', 'dev-reset-toolkit' ); ?></option>
				<?php foreach ( $plugins as $plugin_file => $plugin_data ) : ?>
					<option value="<?php echo esc_attr( $plugin_file ); ?>"><?php echo esc_html( $plugin_data['Name'] . ' (' . $plugin_file . ')' ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="drt_confirm_word"><strong><?php esc_html_e( 'Type reset to confirm', 'dev-reset-toolkit' ); ?></strong></label>
			<input id="drt_confirm_word" name="drt_confirm_word" type="text" autocomplete="off" placeholder="reset" required>

			<button class="button button-primary" id="drt-run-reset" type="submit" disabled><?php esc_html_e( 'Run Reset', 'dev-reset-toolkit' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render tools tab.
	 *
	 * @return void
	 */
	protected function render_tools_tab() {
		echo '<div class="drt-card"><h3>' . esc_html__( 'Tools', 'dev-reset-toolkit' ) . '</h3><p>' . esc_html__( 'Use dry-run mode in Reset tab to preview deletions. Future utility tools can be added here.', 'dev-reset-toolkit' ) . '</p></div>';
	}

	/**
	 * Render snapshots tab.
	 *
	 * @return void
	 */
	protected function render_snapshots_tab() {
		$snapshot = $this->reactivation_manager->get_snapshot();
		echo '<div class="drt-card"><h3>' . esc_html__( 'Latest Snapshot', 'dev-reset-toolkit' ) . '</h3>';
		if ( empty( $snapshot ) ) {
			echo '<p>' . esc_html__( 'No snapshot found yet.', 'dev-reset-toolkit' ) . '</p></div>';
			return;
		}
		echo '<pre>' . esc_html( wp_json_encode( $snapshot, JSON_PRETTY_PRINT ) ) . '</pre></div>';
	}

	/**
	 * Render reactivation tab.
	 *
	 * @param array $plugins Plugins list.
	 * @return void
	 */
	protected function render_reactivation_tab( $plugins ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="drt-card">
			<input type="hidden" name="action" value="drt_reactivation_action">
			<?php wp_nonce_field( 'drt_reactivation_nonce', 'drt_nonce' ); ?>

			<h3><?php esc_html_e( 'Reactivation Tools', 'dev-reset-toolkit' ); ?></h3>
			<p><?php esc_html_e( 'Choose an action to restore themes/plugins after a reset.', 'dev-reset-toolkit' ); ?></p>
			<select name="drt_reactivation_action" required>
				<option value="theme"><?php esc_html_e( 'Reactivate current theme', 'dev-reset-toolkit' ); ?></option>
				<option value="plugin"><?php esc_html_e( 'Reactivate selected plugin', 'dev-reset-toolkit' ); ?></option>
				<option value="all_plugins"><?php esc_html_e( 'Reactivate all previously active plugins', 'dev-reset-toolkit' ); ?></option>
				<option value="recycle_plugins"><?php esc_html_e( 'Deactivate all then restore previous plugins', 'dev-reset-toolkit' ); ?></option>
				<option value="reset_plugin"><?php esc_html_e( 'Reset selected plugin (deactivate, clear options, reactivate)', 'dev-reset-toolkit' ); ?></option>
			</select>

			<label for="drt-reactivation-plugin"><strong><?php esc_html_e( 'Plugin', 'dev-reset-toolkit' ); ?></strong></label>
			<select id="drt-reactivation-plugin" name="drt_selected_plugin">
				<option value=""><?php esc_html_e( 'Select plugin', 'dev-reset-toolkit' ); ?></option>
				<?php foreach ( $plugins as $plugin_file => $plugin_data ) : ?>
					<option value="<?php echo esc_attr( $plugin_file ); ?>"><?php echo esc_html( $plugin_data['Name'] . ' (' . $plugin_file . ')' ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="drt_plugin_option_prefix"><strong><?php esc_html_e( 'Plugin option prefix (for reset selected plugin)', 'dev-reset-toolkit' ); ?></strong></label>
			<input type="text" id="drt_plugin_option_prefix" name="drt_plugin_option_prefix" placeholder="example_plugin_">

			<button class="button button-secondary" type="submit"><?php esc_html_e( 'Run Reactivation Action', 'dev-reset-toolkit' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render logs tab.
	 *
	 * @return void
	 */
	protected function render_logs_tab() {
		$logs = $this->logger->get_logs();
		echo '<div class="drt-card"><h3>' . esc_html__( 'Reset Logs', 'dev-reset-toolkit' ) . '</h3>';
		if ( empty( $logs ) ) {
			echo '<p>' . esc_html__( 'No logs available.', 'dev-reset-toolkit' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date/Time', 'dev-reset-toolkit' ) . '</th><th>' . esc_html__( 'User', 'dev-reset-toolkit' ) . '</th><th>' . esc_html__( 'Action', 'dev-reset-toolkit' ) . '</th><th>' . esc_html__( 'Reset Type', 'dev-reset-toolkit' ) . '</th><th>' . esc_html__( 'Dry Run', 'dev-reset-toolkit' ) . '</th><th>' . esc_html__( 'Status', 'dev-reset-toolkit' ) . '</th><th>' . esc_html__( 'Errors', 'dev-reset-toolkit' ) . '</th></tr></thead><tbody>';
		foreach ( $logs as $entry ) {
			echo '<tr>';
			echo '<td>' . esc_html( $entry['timestamp'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( $entry['username'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( $entry['action'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( $entry['reset_type'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( ! empty( $entry['dry_run'] ) ? 'Yes' : 'No' ) . '</td>';
			echo '<td>' . esc_html( ! empty( $entry['success'] ) ? 'Success' : 'Failed' ) . '</td>';
			echo '<td>' . esc_html( implode( '; ', $entry['errors'] ?? array() ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render settings tab.
	 *
	 * @return void
	 */
	protected function render_settings_tab() {
		echo '<div class="drt-card"><h3>' . esc_html__( 'Settings', 'dev-reset-toolkit' ) . '</h3><p>' . esc_html__( 'This version includes sensible defaults focused on safety. More granular settings can be added in future updates.', 'dev-reset-toolkit' ) . '</p></div>';
	}

	/**
	 * Redirect with notice.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'dev-reset-toolkit',
					'tab'        => 'reset',
					'drt_notice' => sanitize_text_field( $type ),
					'drt_message'=> rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
