<?php
/**
 * Plugin Name: Client Portal Manager
 * Description: Enables secure, personalized client portals within WordPress.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: client-portal-manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define constants
define( 'CPM_VERSION', '1.0.0' );
define( 'CPM_PATH', plugin_dir_path( __FILE__ ) );
define( 'CPM_URL', plugin_dir_url( __FILE__ ) );
define( 'CPM_DEBUG', true ); // Set to false in production

/**
 * Main plugin initialization
 */
function cpm_init_plugin() {
    
    // Core components (order matters)
    require_once CPM_PATH . 'includes/class-cpm-portal-cpt.php';
    new CPM_Portal_CPT();
    
    require_once CPM_PATH . 'includes/class-cpm-page-meta.php';
    new CPM_Page_Meta();
    
    require_once CPM_PATH . 'includes/class-cpm-access-control.php';
    new CPM_Access_Control();
    
    require_once CPM_PATH . 'includes/class-cpm-login-redirect.php';
    new CPM_Login_Redirect();
    
    require_once CPM_PATH . 'includes/class-cpm-menu-manager.php';
    new CPM_Menu_Manager();
    
    require_once CPM_PATH . 'includes/class-cpm-query-filters.php';
    new CPM_Query_Filters();
    
    require_once CPM_PATH . 'includes/class-cpm-sitemaps.php';
    new CPM_Sitemaps();
    
    // Portal switcher for multiple portals
    require_once CPM_PATH . 'includes/class-cpm-portal-switcher.php';
    new CPM_Portal_Switcher();
    
    // Admin-only components
    if ( is_admin() ) {
        require_once CPM_PATH . 'includes/class-cpm-settings.php';
        new CPM_Settings();
        
        require_once CPM_PATH . 'includes/class-cpm-access-logger.php';
        new CPM_Access_Logger();
        
        require_once CPM_PATH . 'includes/class-cpm-access-log-admin.php';
        new CPM_Access_Log_Admin();
    }
}
add_action( 'plugins_loaded', 'cpm_init_plugin' );

/**
 * Activation hook
 */
register_activation_hook( __FILE__, function() {
    // Register CPT first
    require_once CPM_PATH . 'includes/class-cpm-portal-cpt.php';
    $cpt = new CPM_Portal_CPT();
    $cpt->register_portal_cpt();
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    error_log( '✅ Client Portal Manager activated' );
});

/**
 * Deactivation hook
 */
register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
    error_log( '🧹 Client Portal Manager deactivated' );
});

/**
 * Add settings link to plugins page
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
    $settings_link = '<a href="' . admin_url( 'edit.php?post_type=portal&page=cpm-settings' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
});