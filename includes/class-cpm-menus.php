<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class CPM_Menus
 * Handles assigning and displaying custom WordPress menus
 * inside each Portal post.
 */
class CPM_Menus {

    public function __construct() {
        // Add meta box for menu assignment
        add_action( 'add_meta_boxes', [ $this, 'add_menu_meta_box' ] );

        // Save menu selection when portal is saved
        add_action( 'save_post_portal', [ $this, 'save_menu_selection' ] );

        // Add hook for rendering the menu in the template
        add_action( 'cpm_render_menu', [ $this, 'render_menu' ] );

        error_log('✅ CPM_Menus initialized successfully');
    }

    /**
     * Add the "Portal Menu" meta box to the portal editor screen
     */
    public function add_menu_meta_box() {
        add_meta_box(
            'portal_menu_box',
            'Portal Menu',
            [ $this, 'render_menu_box' ],
            'portal',
            'side',
            'default'
        );
    }

    /**
     * Render the menu selection dropdown
     */
    public function render_menu_box( $post ) {
        $selected_menu = get_post_meta( $post->ID, '_portal_menu_id', true );
        $menus = wp_get_nav_menus();

        echo '<label for="portal_menu_id"><strong>Select Menu:</strong></label><br>';
        echo '<select name="portal_menu_id" id="portal_menu_id" style="width:100%;margin-top:6px;">';
        echo '<option value="">— No Menu —</option>';

        if ( ! empty( $menus ) ) {
            foreach ( $menus as $menu ) {
                $selected = selected( $selected_menu, $menu->term_id, false );
                echo "<option value='{$menu->term_id}' {$selected}>{$menu->name}</option>";
            }
        } else {
            echo '<option disabled>(No menus available — create one in Appearance → Menus)</option>';
        }

        echo '</select>';
        echo '<p style="color:#666;font-size:12px;">This menu will be shown on this portal’s page.</p>';
    }

    /**
     * Save the selected menu when the portal is updated
     */
    public function save_menu_selection( $post_id ) {
        if ( isset( $_POST['portal_menu_id'] ) ) {
            update_post_meta( $post_id, '_portal_menu_id', intval( $_POST['portal_menu_id'] ) );
            error_log("💾 Saved portal menu for post ID {$post_id}");
        } else {
            delete_post_meta( $post_id, '_portal_menu_id' );
        }
    }

    /**
     * Render the assigned menu in the portal template
     */
    public function render_menu( $post_id = null ) {
        if ( ! $post_id ) {
            global $post;
            $post_id = $post->ID ?? 0;
        }

        $menu_id = get_post_meta( $post_id, '_portal_menu_id', true );

        if ( $menu_id ) {
            echo '<nav class="portal-menu" style="text-align:center;margin-bottom:1.5rem;">';
            wp_nav_menu([
                'menu' => intval( $menu_id ),
                'container' => false,
                'menu_class' => 'portal-nav',
            ]);
            echo '</nav>';
        } else {
            echo '<div style="text-align:center;color:#888;margin-bottom:1rem;">(No menu assigned)</div>';
        }
    }
}
