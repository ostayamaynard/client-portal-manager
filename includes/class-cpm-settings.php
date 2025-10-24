<?php
/**
 * Client Portal Manager - Settings
 * Admin settings page for plugin configuration
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Add settings page under Portals menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=portal',
            __( 'Client Portal Settings', 'client-portal-manager' ),
            __( 'Settings', 'client-portal-manager' ),
            'manage_options',
            'cpm-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 
            'cpm_settings_group', 
            'cpm_settings',
            [ $this, 'sanitize_settings' ]
        );

        add_settings_section(
            'cpm_general_section',
            __( 'General Settings', 'client-portal-manager' ),
            [ $this, 'render_section_description' ],
            'cpm-settings'
        );

        add_settings_field(
            'default_menu',
            __( 'Default Portal Menu', 'client-portal-manager' ),
            [ $this, 'render_default_menu_field' ],
            'cpm-settings',
            'cpm_general_section'
        );

        add_settings_field(
            'redirect_url',
            __( 'Unauthorized Access Redirect', 'client-portal-manager' ),
            [ $this, 'render_redirect_field' ],
            'cpm-settings',
            'cpm_general_section'
        );
    }

    /**
     * Render section description
     */
    public function render_section_description() {
        echo '<p>' . __( 'Configure global settings for the Client Portal Manager plugin.', 'client-portal-manager' ) . '</p>';
    }

    /**
     * Render default menu field
     */
    public function render_default_menu_field() {
        $settings = get_option( 'cpm_settings', [] );
        $default_menu = isset( $settings['default_menu'] ) ? $settings['default_menu'] : '';
        $menus = wp_get_nav_menus();

        ?>
        <select name="cpm_settings[default_menu]" id="cpm_default_menu" style="min-width:300px;">
            <option value=""><?php _e( '— None —', 'client-portal-manager' ); ?></option>
            <?php foreach ( $menus as $menu ) : ?>
                <option value="<?php echo esc_attr( $menu->term_id ); ?>" <?php selected( $default_menu, $menu->term_id ); ?>>
                    <?php echo esc_html( $menu->name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e( 'Fallback menu to display if a portal has no assigned menu.', 'client-portal-manager' ); ?>
        </p>
        <?php
    }

    /**
     * Render redirect URL field
     */
    public function render_redirect_field() {
        $settings = get_option( 'cpm_settings', [] );
        $redirect_url = isset( $settings['redirect_url'] ) ? $settings['redirect_url'] : home_url( '/' );

        ?>
        <input 
            type="url" 
            name="cpm_settings[redirect_url]" 
            id="cpm_redirect_url" 
            value="<?php echo esc_url( $redirect_url ); ?>" 
            class="regular-text"
            placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>"
        >
        <p class="description">
            <?php _e( 'URL to redirect users when they attempt to access unauthorized content.', 'client-portal-manager' ); ?>
        </p>
        <?php
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = [];

        if ( isset( $input['default_menu'] ) ) {
            $sanitized['default_menu'] = intval( $input['default_menu'] );
        }

        if ( isset( $input['redirect_url'] ) ) {
            $sanitized['redirect_url'] = esc_url_raw( $input['redirect_url'] );
        }

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'client-portal-manager' ) );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'cpm_settings_group' );
                do_settings_sections( 'cpm-settings' );
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php _e( 'Documentation', 'client-portal-manager' ); ?></h2>
            <div class="card">
                <h3><?php _e( 'How to Use', 'client-portal-manager' ); ?></h3>
                <ol>
                    <li><?php _e( 'Create a Portal under Portals > Add New', 'client-portal-manager' ); ?></li>
                    <li><?php _e( 'Assign users to the portal in the Portal Access meta box', 'client-portal-manager' ); ?></li>
                    <li><?php _e( 'Create a menu for the portal under Appearance > Menus', 'client-portal-manager' ); ?></li>
                    <li><?php _e( 'Assign the menu to the portal in the Portal Menu meta box', 'client-portal-manager' ); ?></li>
                    <li><?php _e( 'Create pages and assign them to the portal in the Portal Access meta box', 'client-portal-manager' ); ?></li>
                    <li><?php _e( 'Users will be redirected to their portal upon login', 'client-portal-manager' ); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
}