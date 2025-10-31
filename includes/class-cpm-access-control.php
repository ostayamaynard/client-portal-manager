<?php
/**
 * Client Portal Manager - Access Control
 * Menu-based access control system
 */

class CPM_Access_Control {

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'enforce_access_rules' ], 1 );
        
        // Redirect guests to login when accessing portals
        add_action( 'template_redirect', [ $this, 'redirect_guests_to_login' ], 2 );
        
        add_action( 'wp_footer', [ $this, 'debug_footer' ] );
    }

    /**
     * Main access control enforcement
     */
    public function enforce_access_rules() {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        // Admins bypass all restrictions
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check different content types
        if ( is_singular( 'portal' ) ) {
            $this->check_portal_access();
        } elseif ( is_page() ) {
            $this->check_page_access_via_menu();
        } elseif ( is_home() || is_front_page() ) {
            $this->check_home_access();
        } elseif ( is_single() || is_archive() ) {
            $this->check_blog_access();
        }
    }

    /**
     * Check if user can access the current portal
     */
    private function check_portal_access() {
        $portal_id = get_queried_object_id();
        $user_id   = get_current_user_id();

        // Guest users get login redirect (handled separately)
        if ( ! $user_id ) {
            return;
        }

        $assigned_users = get_post_meta( $portal_id, '_portal_users', true );

        if ( ! $assigned_users || ! in_array( $user_id, $assigned_users ) ) {
            // Log denial
            $this->log_access_denial( $user_id, $portal_id, 'portal' );
            $this->show_404();
        }

        // Set this as the active portal
        set_transient( 'cpm_active_portal_' . $user_id, $portal_id, HOUR_IN_SECONDS );
    }

    /**
     * FIXED: Check page access - must be in CURRENT portal's menu only
     */
    private function check_page_access_via_menu() {
        $page_id = get_queried_object_id();
        $user_id = get_current_user_id();

        // Guest users - let WordPress handle it normally
        if ( ! $user_id ) {
            return;
        }

        // Check if this is a system page (portal selection, etc.)
        if ( get_post_meta( $page_id, '_cpm_system_page', true ) === 'yes' ) {
            return; // Allow access to system pages
        }

        // Get user's portals
        $user_portals = $this->get_user_portals( $user_id );

        // Non-portal users can access all pages
        if ( empty( $user_portals ) ) {
            return;
        }

        // CRITICAL FIX: Check if page is in the CURRENT portal's menu
        // Not just any portal the user has access to
        $in_current_portal = $this->is_page_in_current_portal_menu( $page_id, $user_id );

        if ( ! $in_current_portal ) {
            // Page not in current portal's menu - deny access
            error_log( "🚫 CPM: User {$user_id} denied access to page {$page_id} - not in current portal's menu" );
            $this->show_404();
        }
    }

    /**
     * NEW METHOD: Check if page is in the CURRENT (active) portal's menu
     */
    private function is_page_in_current_portal_menu( $page_id, $user_id ) {
        // Get the current active portal
        $current_portal_id = get_transient( 'cpm_active_portal_' . $user_id );

        // If no active portal set, check if they only have one portal
        if ( ! $current_portal_id ) {
            $user_portals = $this->get_user_portals( $user_id );
            
            if ( count( $user_portals ) === 1 ) {
                // Single portal user - set it as active
                $current_portal_id = $user_portals[0];
                set_transient( 'cpm_active_portal_' . $user_id, $current_portal_id, HOUR_IN_SECONDS );
            } else {
                // Multi-portal user without active portal set
                // They need to select a portal first
                error_log( "⚠️ CPM: User {$user_id} has no active portal set" );
                return false;
            }
        }

        // Verify user actually has access to this portal
        $user_portals = $this->get_user_portals( $user_id );
        if ( ! in_array( $current_portal_id, $user_portals ) ) {
            // Transient is stale or invalid - clear it
            delete_transient( 'cpm_active_portal_' . $user_id );
            error_log( "⚠️ CPM: User {$user_id} active portal {$current_portal_id} is invalid" );
            return false;
        }

        // Get the current portal's menu
        $menu_id = get_post_meta( $current_portal_id, '_portal_menu_id', true );

        if ( ! $menu_id ) {
            // Current portal has no menu assigned
            error_log( "⚠️ CPM: Portal {$current_portal_id} has no menu assigned" );
            return false;
        }

        // Check if page is in this specific menu
        $menu_items = wp_get_nav_menu_items( $menu_id );

        if ( ! $menu_items ) {
            return false;
        }

        foreach ( $menu_items as $item ) {
            if ( $item->object === 'page' && intval( $item->object_id ) === intval( $page_id ) ) {
                error_log( "✅ CPM: Page {$page_id} found in current portal {$current_portal_id} menu" );
                return true;
            }
        }

        // Check if page is in OTHER portal's menu (for better error logging)
        $in_other_portal = $this->is_page_in_any_user_portal_menu( $page_id, $user_id, $current_portal_id );
        if ( $in_other_portal ) {
            error_log( "🔀 CPM: Page {$page_id} exists in another portal (not current portal {$current_portal_id})" );
        }

        return false;
    }


    private function is_page_in_any_user_portal_menu( $page_id, $user_id, $exclude_portal_id ) {
        $user_portals = $this->get_user_portals( $user_id );

        foreach ( $user_portals as $portal_id ) {
            // Skip the current portal
            if ( $portal_id == $exclude_portal_id ) {
                continue;
            }

            $menu_id = get_post_meta( $portal_id, '_portal_menu_id', true );

            if ( $menu_id ) {
                $menu_items = wp_get_nav_menu_items( $menu_id );

                if ( $menu_items ) {
                    foreach ( $menu_items as $item ) {
                        if ( $item->object === 'page' && intval( $item->object_id ) === intval( $page_id ) ) {
                            return true; // Found in another portal
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * OLD METHOD - Kept for reference but no longer used
     * This was the bug - it checked ALL portals instead of current portal
     */
    private function is_page_in_portal_menu_OLD_BUGGY( $page_id ) {
        $portals = get_posts([
            'post_type'   => 'portal',
            'numberposts' => -1,
            'fields'      => 'ids',
            'post_status' => 'publish'
        ]);

        foreach ( $portals as $portal_id ) {
            $menu_id = get_post_meta( $portal_id, '_portal_menu_id', true );
            
            if ( $menu_id ) {
                $menu_items = wp_get_nav_menu_items( $menu_id );
                
                if ( $menu_items ) {
                    foreach ( $menu_items as $item ) {
                        if ( $item->object === 'page' && intval( $item->object_id ) === intval( $page_id ) ) {
                            return true; // BUG: Returns true for ANY portal, not current
                        }
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Check home page access
     */
    private function check_home_access() {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return;
        }

        // Get user's portals
        $user_portals = $this->get_user_portals( $user_id );

        // Non-portal users can access home
        if ( empty( $user_portals ) ) {
            return;
        }

        $home_page_id = get_option( 'page_on_front' );

        if ( ! $home_page_id ) {
            return; // Blog homepage, allow
        }

        // Check if home page is in current portal's menu
        if ( ! $this->is_page_in_current_portal_menu( $home_page_id, $user_id ) ) {
            $this->show_404();
        }
    }

    /**
     * Check blog/archive access
     */
    private function check_blog_access() {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return;
        }

        // Get user's portals
        $user_portals = $this->get_user_portals( $user_id );

        // Portal users shouldn't access blog content
        if ( ! empty( $user_portals ) ) {
            error_log( "🚫 CPM: Portal user {$user_id} tried to access blog content" );
            $this->show_404();
        }
    }

    /**
     * Redirect guests trying to access portals to login
     */
    public function redirect_guests_to_login() {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        if ( is_user_logged_in() ) {
            return;
        }

        // Check if viewing a portal
        if ( is_singular( 'portal' ) ) {
            wp_redirect( wp_login_url( get_permalink() ) );
            exit;
        }
    }

    /**
     * Get all portals a user has access to
     */
    private function get_user_portals( $user_id ) {
        $portals = get_posts([
            'post_type'   => 'portal',
            'numberposts' => -1,
            'fields'      => 'ids',
            'post_status' => 'publish',
            'meta_query'  => [[
                'key'     => '_portal_users',
                'value'   => 'i:' . intval( $user_id ) . ';',
                'compare' => 'LIKE'
            ]]
        ]);

        return $portals;
    }

    /**
     * Show 404 error page
     */
    private function show_404() {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        include( get_query_template( '404' ) );
        exit;
    }

    /**
     * Log access denial
     */
    private function log_access_denial( $user_id, $content_id, $type ) {
        if ( class_exists( 'CPM_Access_Logger' ) ) {
            $logger = new CPM_Access_Logger();
            $logger->log_access( $user_id, $content_id, 'denied', "Attempted {$type} access" );
        }

        error_log( sprintf(
            '🚫 CPM Access Denied: User %d tried to access %s %d',
            $user_id,
            $type,
            $content_id
        ));
    }

    /**
     * Debug footer (only when CPM_DEBUG is true)
     */
    public function debug_footer() {
        if ( ! defined( 'CPM_DEBUG' ) || ! CPM_DEBUG ) {
            return;
        }

        if ( is_admin() ) {
            return;
        }

        $user_id = get_current_user_id();
        $user_portals = $user_id ? $this->get_user_portals( $user_id ) : [];
        $active_portal = $user_id ? get_transient( 'cpm_active_portal_' . $user_id ) : 'N/A';

        $debug_info = sprintf(
            'CPM Debug | Type:%s ID:%d | User:%d | Portals:[%s] | Active:%s',
            get_post_type() ?: 'N/A',
            get_queried_object_id() ?: 0,
            $user_id,
            implode( ',', $user_portals ),
            $active_portal ?: 'None'
        );

        echo '<div style="position:fixed;bottom:0;left:0;right:0;background:#000;color:#0f0;padding:8px;font-family:monospace;font-size:11px;z-index:99999;border-top:2px solid #0f0;">';
        echo esc_html( $debug_info );
        echo '</div>';
    }
}

new CPM_Access_Control();