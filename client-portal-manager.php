<?php
/**
 * Plugin Name: Client Portal Manager
 * Description: Enables secure, personalized client portals within WordPress.
 * Version: 1.0.0
 * Author: Maynard
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// -------------------------------------------------
// 🔧 DEFINE CONSTANTS
// -------------------------------------------------
define( 'CPM_VERSION', '1.0.0' );
define( 'CPM_PATH', plugin_dir_path( __FILE__ ) );
define( 'CPM_URL', plugin_dir_url( __FILE__ ) );

// -------------------------------------------------
// 🚀 CORE INITIALIZATION
// -------------------------------------------------
function cpm_init_plugin() {
    error_log('🚀 Client Portal Manager initialized');

    // --- CORE FRONTEND CLASSES ---
    require_once CPM_PATH . 'includes/class-cpm-portal-cpt.php';
    new CPM_Portal_CPT();

    require_once CPM_PATH . 'includes/class-cpm-access-control.php';
    new CPM_Access_Control();

    require_once CPM_PATH . 'includes/class-cpm-menus.php';
    new CPM_Menus();

    require_once CPM_PATH . 'includes/class-cpm-page-access.php';
    new CPM_Page_Access();

    require_once CPM_PATH . 'includes/class-cpm-login-redirect.php';
    new CPM_Login_Redirect();

    require_once CPM_PATH . 'includes/class-cpm-query-filters.php';
    new CPM_Query_Filters();

    require_once CPM_PATH . 'includes/class-cpm-sitemaps.php';
    new CPM_Sitemaps();

    require_once CPM_PATH . 'includes/class-cpm-menu-filter.php';
    new CPM_Menu_Filter();

    // --- PORTAL SWITCHER (multi-portal chooser) ---
    require_once CPM_PATH . 'includes/class-cpm-portal-switcher.php';
    new CPM_Portal_Switcher();

    // --- ADMIN-ONLY CLASSES ---
    if ( is_admin() ) {
        require_once CPM_PATH . 'includes/class-cpm-access-logger.php';
        new CPM_Access_Logger();

        require_once CPM_PATH . 'includes/class-cpm-access-log-admin.php';
        new CPM_Access_Log_Admin();

        require_once CPM_PATH . 'includes/class-cpm-settings.php';
        new CPM_Settings_Page();
    }
}
add_action( 'plugins_loaded', 'cpm_init_plugin' );

// -------------------------------------------------
// 🔁 FLUSH REWRITE RULES ON ACTIVATE / DEACTIVATE
// -------------------------------------------------
register_activation_hook(__FILE__, function() {
    cpm_init_plugin();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// -------------------------------------------------
// 🧭 Quick link to settings page
// -------------------------------------------------
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=cpm-settings') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
});

if ( session_status() === PHP_SESSION_ACTIVE ) {
    add_action( 'wp_logout', function() {
        session_destroy();
    });
}

