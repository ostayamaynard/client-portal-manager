<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class CPM_Portal_CPT
 * Handles registration of the 'portal' post type, template loading,
 * user access meta box, and save logic.
 */
class CPM_Portal_CPT {

    public function __construct() {
        // ✅ Register CPT safely once WordPress is fully loaded
        add_action( 'init', [ $this, 'register_portal_cpt' ] );

        // ✅ Template override for single portal view
        add_filter( 'single_template', [ $this, 'load_portal_template' ] );

        // ✅ Add and save user access meta box
        add_action( 'add_meta_boxes', [ $this, 'add_user_meta_box' ] );
        add_action( 'save_post_portal', [ $this, 'save_portal_users' ] );

        error_log('✅ CPM_Portal_CPT initialized successfully');
    }

    /** ------------------------------------------------------------
     * 🔹 Register the Custom Post Type
     * ------------------------------------------------------------ */
    public function register_portal_cpt() {
        $args = [
            'labels' => [
                'name'          => 'Portals',
                'singular_name' => 'Portal',
                'add_new_item'  => 'Add New Portal',
                'edit_item'     => 'Edit Portal',
                'view_item'     => 'View Portal',
                'search_items'  => 'Search Portals',
            ],
            'public'             => true, // hides from search and archives
            'publicly_queryable' => true,  // allows frontend URLs like /portal/test-portal/
            'has_archive'   => false,
            'rewrite'       => [ 'slug' => 'portal', 'with_front' => false ],
            'supports'      => [ 'title', 'editor' ],
            'show_in_menu'  => true,
            'menu_icon'     => 'dashicons-lock',
            'capability_type' => 'post',
        ];

        register_post_type( 'portal', $args );
        error_log('✅ Portal CPT registered safely');
    }

    /** ------------------------------------------------------------
     * 🔹 Load Custom Template
     * ------------------------------------------------------------ */
    public function load_portal_template( $template ) {
        global $post;

        if ( isset( $post->post_type ) && $post->post_type === 'portal' ) {
            $custom = CPM_PATH . 'templates/portal-page.php';
            if ( file_exists( $custom ) ) {
                error_log('✅ portal-page.php template loaded');
                return $custom;
            } else {
                error_log('❌ portal-page.php missing: ' . $custom);
            }
        }

        return $template;
    }

    /** ------------------------------------------------------------
     * 🔹 Add Meta Box to Assign Users
     * ------------------------------------------------------------ */
    public function add_user_meta_box() {
        add_meta_box(
            'portal_user_access',
            'Portal Access',
            [ $this, 'render_user_meta_box' ],
            'portal',
            'side',
            'default'
        );
    }

    /** ------------------------------------------------------------
     * 🔹 Render User Access Meta Box
     * ------------------------------------------------------------ */
    public function render_user_meta_box( $post ) {
        $assigned = get_post_meta( $post->ID, '_portal_users', true ) ?: [];
        $users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

        echo '<p>Select which users can access this portal:</p>';

        foreach ( $users as $user ) {
            $checked = in_array( $user->ID, $assigned ) ? 'checked' : '';
            echo '<label style="display:block;margin-bottom:4px;">';
            echo '<input type="checkbox" name="portal_users[]" value="' . esc_attr( $user->ID ) . '" ' . $checked . '> ';
            echo esc_html( $user->display_name );
            echo '</label>';
        }

        echo '<p style="font-size:11px;color:#666;">If no users are selected, the portal is public to all logged-in users.</p>';
    }

    /** ------------------------------------------------------------
     * 🔹 Save Selected Users
     * ------------------------------------------------------------ */
    public function save_portal_users( $post_id ) {
        // Avoid autosave and quick edits
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        if ( isset( $_POST['portal_users'] ) ) {
            $user_ids = array_map( 'intval', $_POST['portal_users'] );
            update_post_meta( $post_id, '_portal_users', $user_ids );
        } else {
            delete_post_meta( $post_id, '_portal_users' );
        }
    }
}
