<?php
/**
 * Plugin Name: Client Portal Manager
 * Description: Secure, personalized client portals with access control and custom menus.
 * Version: 1.0.0
 * Author: Maynard
 * Text Domain: client-portal-manager
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Prevent direct access

// Define constants
define( 'CPM_PATH', plugin_dir_path( __FILE__ ) );
define( 'CPM_URL', plugin_dir_url( __FILE__ ) );
define( 'CPM_VERSION', '1.0.0' );

// Include class files
require_once CPM_PATH . 'includes/class-cpm-portal-cpt.php';

// Initialize the plugin
function cpm_init() {
    new CPM_Portal_CPT();
}
add_action( 'plugins_loaded', 'cpm_init' );

// Flush rewrite rules on activation/deactivation
register_activation_hook( __FILE__, function() { flush_rewrite_rules(); });
register_deactivation_hook( __FILE__, function() { flush_rewrite_rules(); });
