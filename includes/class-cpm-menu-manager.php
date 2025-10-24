<?php
/**
 * Client Portal Manager - Menu Manager
 * Makes Astra's header menu dynamic based on portal assignment
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Menu_Manager {

    public function __construct() {
        // Admin: Menu assignment meta box
        add_action( 'add_meta_boxes', [ $this, 'add_menu_meta_box' ] );
        add_action( 'save_post_portal', [ $this, 'save_menu_assignment' ] );
        
        // Frontend: Override Astra's menu with portal menu
        add_filter( 'wp_nav_menu_args', [ $this, 'force_portal_menu' ], 999 );
        add_action( 'wp_head', [ $this, 'hide_empty_menu_css' ] );
    }

    /**
     * Add menu meta box to portal
     */
    public function add_menu_meta_box() {
        add_meta_box(
            'cpm_portal_menu',
            __( 'Portal Menu', 'client-portal-manager' ),
            [ $this, 'render_menu_meta_box' ],
            'portal',
            'side',
            'default'
        );
    }

    /**
     * Render menu meta box
     */
    public function render_menu_meta_box( $post ) {
        wp_nonce_field( 'cpm_save_portal_menu', 'cpm_portal_menu_nonce' );
        
        $selected_menu = get_post_meta( $post->ID, '_portal_menu_id', true );
        $menus = wp_get_nav_menus();

        ?>
        <p>
            <label for="cpm_portal_menu_id">
                <strong><?php _e( 'Select Menu:', 'client-portal-manager' ); ?></strong>
            </label>
        </p>
        <select name="cpm_portal_menu_id" id="cpm_portal_menu_id" style="width:100%;">
            <option value=""><?php _e( '— No Menu —', 'client-portal-manager' ); ?></option>
            <?php if ( ! empty( $menus ) ) : ?>
                <?php foreach ( $menus as $menu ) : ?>
                    <option value="<?php echo esc_attr( $menu->term_id ); ?>" <?php selected( $selected_menu, $menu->term_id ); ?>>
                        <?php echo esc_html( $menu->name ); ?>
                    </option>
                <?php endforeach; ?>
            <?php else : ?>
                <option disabled><?php _e( '(No menus available)', 'client-portal-manager' ); ?></option>
            <?php endif; ?>
        </select>
        <p class="description">
            <?php _e( 'This menu will appear in Astra\'s header. If empty, NO menu will be shown.', 'client-portal-manager' ); ?>
            <br>
            <a href="<?php echo admin_url( 'nav-menus.php' ); ?>" target="_blank">
                <?php _e( 'Manage Menus', 'client-portal-manager' ); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Save menu assignment
     */
    public function save_menu_assignment( $post_id ) {
        if ( ! isset( $_POST['cpm_portal_menu_nonce'] ) ||
             ! wp_verify_nonce( $_POST['cpm_portal_menu_nonce'], 'cpm_save_portal_menu' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['cpm_portal_menu_id'] ) && ! empty( $_POST['cpm_portal_menu_id'] ) ) {
            update_post_meta( $post_id, '_portal_menu_id', intval( $_POST['cpm_portal_menu_id'] ) );
            error_log( "💾 Saved menu for portal {$post_id}: " . intval( $_POST['cpm_portal_menu_id'] ) );
        } else {
            delete_post_meta( $post_id, '_portal_menu_id' );
            error_log( "💾 Removed menu from portal {$post_id}" );
        }
    }

    /**
     * Force portal menu in Astra's header
     */
    public function force_portal_menu( $args ) {
        if ( is_admin() ) {
            return $args;
        }

        $user_id = get_current_user_id();
        
        // Non-portal users see default menu
        $user_portals = $this->get_user_portals( $user_id );
        if ( empty( $user_portals ) ) {
            return $args;
        }

        // Portal user - get current portal
        $portal_id = $this->get_current_portal_id();
        
        if ( ! $portal_id ) {
            // Portal user but not in portal context - hide all menus
            return $this->hide_menu( $args );
        }

        // Get portal's assigned menu
        $menu_id = get_post_meta( $portal_id, '_portal_menu_id', true );

        if ( $menu_id ) {
            // Portal has a menu - show it
            $args['menu'] = intval( $menu_id );
            error_log( "✅ Showing menu {$menu_id} for portal {$portal_id} (user {$user_id})" );
        } else {
            // Portal has NO menu - hide completely
            return $this->hide_menu( $args );
        }

        return $args;
    }

    /**
     * Hide menu completely
     */
    private function hide_menu( $args ) {
        $args['menu'] = 0;
        $args['theme_location'] = '';
        $args['fallback_cb'] = '__return_empty_string';
        $args['items_wrap'] = '<ul style="display:none;">%3$s</ul>';
        
        error_log( "🚫 Hiding menu - portal has no menu assigned" );
        
        return $args;
    }

    /**
     * Add CSS to hide menu container when empty
     */
    public function hide_empty_menu_css() {
        if ( is_admin() ) {
            return;
        }

        $user_id = get_current_user_id();
        $user_portals = $this->get_user_portals( $user_id );
        
        if ( empty( $user_portals ) ) {
            return;
        }

        $portal_id = $this->get_current_portal_id();
        if ( ! $portal_id ) {
            return;
        }

        $menu_id = get_post_meta( $portal_id, '_portal_menu_id', true );
        
        // If portal has no menu, hide the entire navigation area
        if ( ! $menu_id ) {
            ?>
            <style type="text/css">
                /* Hide Astra's header navigation completely */
                .main-header-bar-navigation,
                .ast-header-break-point .main-header-bar-navigation,
                .main-header-menu,
                .ast-main-header-bar-alignment,
                nav.site-navigation,
                .ast-mobile-menu-buttons {
                    display: none !important;
                }
                
                /* Adjust header layout when menu is hidden */
                .main-header-bar {
                    justify-content: center !important;
                }
            </style>
            <?php
        }
    }

    /**
     * Get current portal ID
     */
    private function get_current_portal_id() {
        // Direct portal view
        if ( is_singular( 'portal' ) ) {
            return get_queried_object_id();
        }

        // Page view - find portal via menu
        if ( is_page() ) {
            $page_id = get_queried_object_id();
            $user_id = get_current_user_id();
            $user_portals = $this->get_user_portals( $user_id );

            foreach ( $user_portals as $portal_id ) {
                $menu_id = get_post_meta( $portal_id, '_portal_menu_id', true );
                
                if ( $menu_id ) {
                    $menu_items = wp_get_nav_menu_items( $menu_id );
                    
                    if ( $menu_items ) {
                        foreach ( $menu_items as $item ) {
                            if ( $item->object === 'page' && intval( $item->object_id ) === intval( $page_id ) ) {
                                return $portal_id;
                            }
                        }
                    }
                }
            }
        }

        // Check transient for active portal
        $user_id = get_current_user_id();
        if ( $user_id ) {
            $active_portal = get_transient( 'cpm_active_portal_' . $user_id );
            if ( $active_portal ) {
                return $active_portal;
            }
            
            // Fallback to first portal
            $user_portals = $this->get_user_portals( $user_id );
            if ( ! empty( $user_portals ) ) {
                return $user_portals[0];
            }
        }

        return 0;
    }

    /**
     * Get user portals
     */
    private function get_user_portals( $user_id ) {
        if ( ! $user_id ) return [];
        
        $portals = get_posts([
            'post_type'   => 'portal',
            'numberposts' => -1,
            'fields'      => 'ids',
            'post_status' => 'publish',
            'meta_query'  => [[
                'key'     => '_portal_users',
                'value'   => 'i:' . (int) $user_id . ';',
                'compare' => 'LIKE'
            ]],
        ]);
        
        return array_map( 'intval', $portals );
    }
}