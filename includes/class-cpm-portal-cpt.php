<?php
/**
 * Class: CPM_Portal_CPT
 * Description: Handles the registration of the "Portal" custom post type (CPT),
 *              along with associated meta boxes for user assignment,
 *              menu selection, and home page designation.
 *
 * This file is loaded automatically from the main plugin file (client-portal-manager.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit; // 🔒 Prevent direct access to file

class CPM_Portal_CPT {

    /**
     * Constructor
     * Hooks WordPress actions for registering the CPT,
     * adding meta boxes, and saving post metadata.
     */
    public function __construct() {
        // Register the Portal CPT when WordPress initializes
        add_action( 'init', [ $this, 'register_portal_cpt' ] );

        // Add custom meta boxes in the admin editor
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );

        // Save portal meta data when the post is saved
        add_action( 'save_post_portal', [ $this, 'save_portal_meta' ] );
    }

    /**
     * Registers the "Portal" custom post type.
     * Portals represent private areas for assigned users.
     */
    public function register_portal_cpt() {
        $labels = [
            'name'               => 'Portals',
            'singular_name'      => 'Portal',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Portal',
            'edit_item'          => 'Edit Portal',
            'new_item'           => 'New Portal',
            'all_items'          => 'All Portals',
            'menu_name'          => 'Portals',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,               // Not publicly accessible
            'show_ui'            => true,                // Show in admin menu
            'menu_icon'          => 'dashicons-shield',  // WordPress icon
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,                // Use WP’s default capability mapping
            'rewrite'            => false,               // No public rewrite URLs
        ];

        // 🧱 Register the CPT
        register_post_type( 'portal', $args );
    }

    /**
     * Adds meta boxes for additional portal data.
     * These appear on the portal edit screen in the admin.
     */
    public function add_meta_boxes() {
        // 👥 Assign Users
        add_meta_box(
            'portal_users',
            'Assigned Users',
            [ $this, 'render_users_box' ],
            'portal',
            'normal'
        );

        // 📜 Assign Menu
        add_meta_box(
            'portal_menu',
            'Assigned Menu',
            [ $this, 'render_menu_box' ],
            'portal',
            'normal'
        );

        // 🏠 Home Page setting
        add_meta_box(
            'portal_home',
            'Home Page',
            [ $this, 'render_home_page_box' ],
            'portal',
            'side'
        );
    }

    /**
     * Renders the "Assigned Users" multi-select dropdown.
     */
    public function render_users_box( $post ) {
        // Get all WordPress users (ID + display name)
        $users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

        // Retrieve saved assigned users (from post meta)
        $assigned = get_post_meta( $post->ID, '_portal_users', true ) ?: [];

        echo '<select multiple name="portal_users[]" style="width:100%;height:100px;">';
        foreach ( $users as $u ) {
            $selected = in_array( $u->ID, $assigned ) ? 'selected' : '';
            echo "<option value='{$u->ID}' $selected>{$u->display_name}</option>";
        }
        echo '</select>';

        echo '<p style="font-size:12px;color:#666;">Hold Ctrl (Windows) or Command (Mac) to select multiple users.</p>';
    }

    /**
     * Renders the "Assigned Menu" dropdown for custom navigation.
     */
    public function render_menu_box( $post ) {
        $menus = wp_get_nav_menus(); // Get all WP menus
        $selected = get_post_meta( $post->ID, '_portal_menu_id', true );

        echo '<select name="portal_menu_id" style="width:100%;">';
        echo '<option value="">-- Select Menu --</option>';
        foreach ( $menus as $menu ) {
            printf(
                '<option value="%d" %s>%s</option>',
                $menu->term_id,
                selected( $selected, $menu->term_id, false ),
                esc_html( $menu->name )
            );
        }
        echo '</select>';

        echo '<p style="font-size:12px;color:#666;">This menu will be used as the portal navigation.</p>';
    }

    /**
     * Renders the "Home Page" selector dropdown.
     * Lets admins choose which page acts as the portal’s main landing page.
     */
    public function render_home_page_box( $post ) {
        $pages = get_pages(); // Retrieve all site pages
        $selected = get_post_meta( $post->ID, '_portal_home_page', true );

        echo '<select name="portal_home_page" style="width:100%;">';
        echo '<option value="">-- Select Home Page --</option>';
        foreach ( $pages as $page ) {
            printf(
                '<option value="%d" %s>%s</option>',
                $page->ID,
                selected( $selected, $page->ID, false ),
                esc_html( $page->post_title )
            );
        }
        echo '</select>';

        echo '<p style="font-size:12px;color:#666;">Users will be redirected here after login.</p>';
    }

    /**
     * Saves all meta box data when the portal post is saved.
     */
    public function save_portal_meta( $post_id ) {
        // Save assigned users
        if ( isset( $_POST['portal_users'] ) ) {
            update_post_meta( $post_id, '_portal_users', array_map( 'intval', $_POST['portal_users'] ) );
        }

        // Save assigned menu ID
        if ( isset( $_POST['portal_menu_id'] ) ) {
            update_post_meta( $post_id, '_portal_menu_id', intval( $_POST['portal_menu_id'] ) );
        }

        // Save assigned home page ID
        if ( isset( $_POST['portal_home_page'] ) ) {
            update_post_meta( $post_id, '_portal_home_page', intval( $_POST['portal_home_page'] ) );
        }
    }
}
