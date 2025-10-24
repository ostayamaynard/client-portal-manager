<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class CPM_Settings_Page
 * Adds a settings page for global Client Portal Manager options.
 */
class CPM_Settings_Page {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/** ✅ Add submenu under the Portal CPT */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=portal',      // parent menu (Portals)
			'Client Portal Settings',         // page title
			'Settings',                       // menu title
			'manage_options',                 // capability required
			'cpm-settings',                   // slug
			[ $this, 'render_settings_page' ] // callback
		);
	}

	/** ✅ Register the settings option */
	public function register_settings() {
		register_setting( 'cpm_settings_group', 'cpm_settings' );
	}

	/** ✅ Render the settings UI */
	public function render_settings_page() {
		// 🔒 security: only admins
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// Get current values or defaults
		$settings = get_option( 'cpm_settings', [
			'redirect_page'  => '',
			'denied_message' => 'Access denied. Please contact the administrator.',
			'default_menu'   => ''
		]);
		?>
		<div class="wrap">
			<h1>Client Portal Settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'cpm_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="redirect_page">Redirect Page</label></th>
						<td>
							<input type="text" id="redirect_page" name="cpm_settings[redirect_page]"
								value="<?php echo esc_attr( $settings['redirect_page'] ); ?>" class="regular-text" />
							<p class="description">URL to redirect unauthorized users.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="denied_message">Access Denied Message</label></th>
						<td>
							<textarea id="denied_message" name="cpm_settings[denied_message]" rows="3"
								class="large-text"><?php echo esc_textarea( $settings['denied_message'] ); ?></textarea>
							<p class="description">Displayed when users lack permission to view a portal.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="default_menu">Default Portal Menu</label></th>
						<td>
							<?php
							$menus = wp_get_nav_menus();
							echo '<select id="default_menu" name="cpm_settings[default_menu]">';
							echo '<option value="">— None —</option>';
							foreach ( $menus as $menu ) {
								$selected = selected( $settings['default_menu'], $menu->term_id, false );
								echo "<option value='{$menu->term_id}' {$selected}>{$menu->name}</option>";
							}
							echo '</select>';
							?>
							<p class="description">Optional menu to display if a portal has none assigned.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		<?php
	}
}
