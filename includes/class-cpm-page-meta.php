<?php
/**
 * Client Portal Manager - Page Meta Boxes
 * Handles portal assignment for pages
 * REMOVED - Pages are managed through menu assignment only
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Page_Meta {

    public function __construct() {
        // DISABLED: Pages are now managed through portal menus only
        // Pages don't need individual portal assignment
        // Access is controlled by menu items
        
        // If you want to enable page-level portal assignment, uncomment below:
        // add_action( 'add_meta_boxes', [ $this, 'add_portal_meta_box' ] );
        // add_action( 'save_post_page', [ $this, 'save_page_portals' ] );
    }

    // Methods kept for future use if needed
    private function add_portal_meta_box() {
        // Disabled
    }

    private function render_portal_meta_box( $post ) {
        // Disabled
    }

    private function save_page_portals( $post_id ) {
        // Disabled
    }
}