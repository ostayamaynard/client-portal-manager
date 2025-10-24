<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Admin_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=portal', // under Portals menu
            'Client Portal Settings',
            'Settings',
            'manage_options',
            'cpm-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'cpm_settings_group', 'cpm_settings' );

        add_settings_section( 'cpm_general_section', 'General Settings', '__return_false', 'cpm-settings' );

        // Redirect page
        add_settings_field(
            'redirect_page',
            'Redirect Page',
            [ $this, 'render_redirect_field' ],
            'cpm-settings',
            'cpm_general_section'
        );

        // Access denied message
        add_settings_field(
            'denied_message',
            'Access Denied Message',
            [ $this, 'render_denied_message_field' ],
            'cpm-settings',
            'cpm_general_section'
        );

        // Default portal menu
        add_settings_field(
            'default_menu',
            'Default Portal Menu',
            [ $this, 'render_default_menu_field' ],
            'cpm-settings',
            'cpm_general_section'
        );
    }

    public function render_redirect_field() {
        $settings = get_option( 'cpm_settings', [] );
        $redirect_page = isset( $settings['redirect_page'] ) ? $settings['redirect_page'] : '';
        $pages = get_pages();
        echo '<select name="cpm_settings[redirect_page]" style="width: 300px;">';
        echo '<option value="">— Select Page —</option>';
        foreach ( $pages as $page ) {
            $selected = selected( $redirect_page, $page->ID, false );
            echo "<option value='{$page->ID}' {$selected}>{$page->post_title}</option>";
        }
        echo '</select>';
        echo '<p class="description">Where users are redirected if unauthorized (optional).</p>';
    }

    public function render_denied_message_field() {
        $settings = get_option( 'cpm_settings', [] );
        $message = isset( $settings['denied_message'] ) ? esc_textarea( $settings['denied_message'] ) : 'Access Denied. Please contact your administrator.';
        echo "<textarea name='cpm_settings[denied_message]' rows='3' style='width: 100%; max-width: 600px;'>{$message}</textarea>";
    }

    public function render_default_menu_field() {
        $settings = get_option( 'cpm_settings', [] );
        $default_menu = isset( $settings['default_menu'] ) ? $settings['default_menu'] : '';
        $menus = wp_get_nav_menus();
        echo '<select name="cpm_settings[default_menu]" style="width: 300px;">';
        echo '<option value="">— None —</option>';
        foreach ( $menus as $menu ) {
            $selected = selected( $default_menu, $menu->term_id, false );
            echo "<option value='{$menu->term_id}' {$selected}>{$menu->name}</option>";
        }
        echo '</select>';
        echo '<p class="description">Used when a portal has no assigned menu.</p>';
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Client Portal Manager Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'cpm_settings_group' );
                do_settings_sections( 'cpm-settings' );
                submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
        <?php
    }
}
