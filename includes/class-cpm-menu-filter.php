<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Client Portal Manager – Menu Filter
 * - Forces the portal-specific menu where applicable
 * - Filters out menu items the current user cannot access
 */
class CPM_Menu_Filter {

    public function __construct() {
        // Force the correct portal menu to render
        add_filter( 'wp_nav_menu_args', [ $this, 'force_portal_menu' ], 999 );

        // Remove menu items a user cannot access
        add_filter( 'wp_nav_menu_objects', [ $this, 'filter_menu_items' ], 999, 2 );
    }

    /* ----------------------------------------------------------------------
     * Filters
     * --------------------------------------------------------------------*/

    /**
     * If we’re inside a portal context, use that portal’s assigned menu.
     */
    public function force_portal_menu( $args ) {
        if ( is_admin() ) return $args;

        $active_portal_id = $this->get_active_portal_id();
        if ( ! $active_portal_id ) return $args;

        $menu_id = (int) get_post_meta( $active_portal_id, '_portal_menu_id', true );
        if ( $menu_id ) {
            $args['menu'] = $menu_id;
            // Ensure a theme location is set to prevent theme edge cases
            if ( empty( $args['theme_location'] ) ) {
                $args['theme_location'] = 'primary';
            }
        }
        return $args;
    }

    /**
     * Remove links to content the user cannot access.
     */
    public function filter_menu_items( $items, $args ) {
        if ( is_admin() ) return $items;

        $user_id      = get_current_user_id();
        $user_portals = $this->get_user_portals( $user_id );

        // Guests can keep seeing public links
        foreach ( $items as $key => $item ) {
            $object_id   = isset( $item->object_id ) ? (int) $item->object_id : 0;
            $object_type = isset( $item->object ) ? $item->object : '';

            // Only filter posts/pages/CPTs that participate in portal restrictions.
            if ( $object_id && $this->uses_portal_meta( $object_type ) ) {
                $required = $this->get_post_portals( $object_id );

                // Public content (no portals assigned) is visible to everyone
                if ( empty( $required ) ) {
                    continue;
                }

                // Admins/editors can see everything
                if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_others_pages' ) ) {
                    continue;
                }

                // Logged-out users cannot access restricted links
                if ( ! $user_id ) {
                    unset( $items[$key] );
                    continue;
                }

                // If user has no overlap with required portals, remove item
                if ( empty( array_intersect( $required, $user_portals ) ) ) {
                    unset( $items[$key] );
                }
            }
        }

        return $items;
    }

    /* ----------------------------------------------------------------------
     * Helpers
     * --------------------------------------------------------------------*/

    /**
     * Determine the active portal:
     *  - If CPM_Portal_Switcher exists, use its cookie-based active portal
     *  - If viewing a portal, use that portal
     *  - If viewing a page assigned to one of the user’s portals, use that portal
     *  - Fallback to first user portal (if any)
     */
    private function get_active_portal_id() {
        // Preferred: from the switcher (cookie)
        if ( class_exists( 'CPM_Portal_Switcher' ) && method_exists( 'CPM_Portal_Switcher', 'get_active_portal_id' ) ) {
            $pid = (int) CPM_Portal_Switcher::get_active_portal_id();
            if ( $pid ) return $pid;
        }

        // Inside a portal
        if ( is_singular( 'portal' ) ) {
            return (int) get_queried_object_id();
        }

        // If on a singular post that is assigned to a portal the user belongs to
        if ( is_singular() ) {
            $post_id      = (int) get_queried_object_id();
            $required     = $this->get_post_portals( $post_id );
            $user_id      = get_current_user_id();
            $user_portals = $this->get_user_portals( $user_id );

            if ( $required && $user_portals ) {
                $overlap = array_intersect( $required, $user_portals );
                if ( ! empty( $overlap ) ) {
                    return (int) array_shift( $overlap );
                }
            }
        }

        // Fallback: if user has portals, return the first
        $user_id      = get_current_user_id();
        $user_portals = $this->get_user_portals( $user_id );
        if ( ! empty( $user_portals ) ) {
            return (int) $user_portals[0];
        }

        return 0;
        }

    /**
     * Return true for post types where we apply portal rules.
     * Pages and any CPTs that store `_page_portals` are included.
     */
    private function uses_portal_meta( $post_type ) {
        // page always participates; other CPTs do if they have _page_portals meta
        if ( $post_type === 'page' || $post_type === 'portal' ) return true;
        // Assume other post types MAY participate (safe to check metadata later)
        return true;
    }

    private function get_post_portals( $post_id ) {
        $ids = get_post_meta( $post_id, '_page_portals', true );
        $ids = is_array( $ids ) ? $ids : [];
        return array_filter( array_map( 'intval', $ids ) );
    }

    private function get_user_portals( $user_id ) {
        if ( ! $user_id ) return [];
        $portals = get_posts([
            'post_type'   => 'portal',
            'numberposts' => -1,
            'fields'      => 'ids',
            'meta_query'  => [
                [
                    'key'     => '_portal_users',
                    'value'   => 'i:' . (int) $user_id . ';',
                    'compare' => 'LIKE',
                ],
            ],
        ]);
        return array_map( 'intval', $portals );
    }
}
