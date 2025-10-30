<?php
/**
 * Client Portal Manager - Access Control
 * Menu-based access control system
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Access_Control {

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'enforce_access_rules' ], 1 );
        
        if ( CPM_DEBUG ) {
            add_action( 'wp_footer', [ $this, 'debug_footer' ], 9999 );
        }
    }

    /**
     * Main access control
     */
    public function enforce_access_rules() {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        $user_id = get_current_user_id();
        
        // Admins bypass all restrictions
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

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
     * Check home/front page access
     */
    private function check_home_access() {
        $user_id = get_current_user_id();
        $user_portals = $this->get_user_portals( $user_id );
        
        // Check if home is in user's portal menu
        if ( ! empty( $user_portals ) ) {
            $has_home_in_menu = false;
            
            foreach ( $user_portals as $portal_id ) {
                $menu_id = get_post_meta( $portal_id, '_portal_menu_id', true );
                
                if ( $menu_id ) {
                    $menu_items = wp_get_nav_menu_items( $menu_id );
                    
                    if ( $menu_items ) {
                        foreach ( $menu_items as $item ) {
                            // Check if menu item links to home
                            if ( $item->object === 'custom' && 
                                 ( $item->url === home_url( '/' ) || $item->url === home_url() ) ) {
                                $has_home_in_menu = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            if ( $has_home_in_menu ) {
                // Portal user has home link in menu - allow
                error_log( "Portal user {$user_id} accessing home (in menu)" );
                return;
            }
            
            // Portal user doesn't have home in menu - block
            error_log( "Portal user {$user_id} blocked from home (not in menu)" );
            $this->show_404();
        }
    }

    /**
     * Check portal access
     */
    private function check_portal_access() {
        global $post;
        
        $portal_id = $post->ID;
        $user_id = get_current_user_id();
        $allowed_users = $this->get_portal_users( $portal_id );

        if ( ! $user_id ) {
            error_log( "Guest attempted portal ID {$portal_id}" );
            wp_safe_redirect( wp_login_url( get_permalink( $portal_id ) ) );
            exit;
        }

        if ( ! empty( $allowed_users ) && ! in_array( $user_id, $allowed_users, true ) ) {
            error_log( "User {$user_id} denied access to portal {$portal_id}" );
            $this->show_404();
        }

        error_log( "User {$user_id} accessing portal {$portal_id}" );
    }

    /**
     * Check page access via menu assignment
     */
    private function check_page_access_via_menu() {
        global $post;
        
        $page_id = $post->ID;
        $user_id = get_current_user_id();
        
        // Allow system pages
        if ( get_post_meta( $page_id, '_cpm_system_page', true ) === 'yes' ) {
            return;
        }

        $user_portals = $this->get_user_portals( $user_id );
        $is_portal_user = ! empty( $user_portals );

        // Check if page is in any portal menu
        $page_in_portal_menu = $this->is_page_in_portal_menu( $page_id );

        if ( $page_in_portal_menu ) {
            // Page is in a portal menu
            if ( ! $user_id ) {
                error_log( "Guest attempted portal page {$page_id}" );
                wp_safe_redirect( wp_login_url( get_permalink( $page_id ) ) );
                exit;
            }

            // Check if user can access this page via their portal menus
            if ( ! $this->user_can_access_page_via_menu( $user_id, $page_id ) ) {
                error_log( "User {$user_id} denied access to portal page {$page_id}" );
                $this->show_404();
            }

            error_log( "User {$user_id} accessing portal page {$page_id}" );
        } else {
            // Page is NOT in any portal menu - it's public
            
            if ( $is_portal_user ) {
                // Portal users can ONLY access portal content
                error_log( "Portal user {$user_id} blocked from public page {$page_id}" );
                $this->show_404();
            }
            
            error_log( "Non-portal user accessing public page {$page_id}" );
        }
    }

    /**
     * Check if page exists in any portal menu
     */
    private function is_page_in_portal_menu( $page_id ) {
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
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if user can access page via their assigned portal menus
     */
    private function user_can_access_page_via_menu( $user_id, $page_id ) {
        $user_portals = $this->get_user_portals( $user_id );

        foreach ( $user_portals as $portal_id ) {
            $menu_id = get_post_meta( $portal_id, '_portal_menu_id', true );
            
            if ( $menu_id ) {
                $menu_items = wp_get_nav_menu_items( $menu_id );
                
                if ( $menu_items ) {
                    foreach ( $menu_items as $item ) {
                        if ( $item->object === 'page' && intval( $item->object_id ) === intval( $page_id ) ) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check blog access
     */
    private function check_blog_access() {
        $user_id = get_current_user_id();
        $user_portals = $this->get_user_portals( $user_id );

        if ( ! empty( $user_portals ) ) {
            error_log( "Portal user {$user_id} blocked from blog content" );
            $this->show_404();
        }
    }

    /**
     * Show 404 page
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
     * Get portal users
     */
    private function get_portal_users( $portal_id ) {
        $users = get_post_meta( $portal_id, '_portal_users', true );
        if ( ! is_array( $users ) ) {
            $users = maybe_unserialize( $users );
        }
        return array_map( 'intval', (array) $users );
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
            'meta_query'  => [[
                'key'     => '_portal_users',
                'value'   => 'i:' . (int) $user_id . ';',
                'compare' => 'LIKE'
            ]],
        ]);
        
        return array_map( 'intval', $portals );
    }

    /**
     * Debug footer
     */
    public function debug_footer() {
        if ( is_admin() ) return;

        $user_id = get_current_user_id();
        $post_id = get_queried_object_id();
        $post_type = get_post_type( $post_id );
        
        $user_portals = $this->get_user_portals( $user_id );
        $in_menu = is_page() ? ( $this->is_page_in_portal_menu( $post_id ) ? 'YES' : 'NO' ) : 'N/A';
        $can_access = is_page() && $user_id ? ( $this->user_can_access_page_via_menu( $user_id, $post_id ) ? 'YES' : 'NO' ) : 'N/A';

        $msg = sprintf(
            'CPM | Type:%s ID:%d | User:%d Portals:[%s] | InMenu:%s CanAccess:%s',
            $post_type,
            $post_id,
            $user_id,
            implode( ',', $user_portals ),
            $in_menu,
            $can_access
        );

        echo '<div style="position:fixed;left:0;right:0;bottom:0;background:#000;color:#0f0;padding:8px;font:11px monospace;z-index:99999;">'
            . esc_html( $msg )
            . '</div>';

        error_log( $msg );
    }
}