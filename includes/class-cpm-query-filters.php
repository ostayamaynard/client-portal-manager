<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Query_Filters {

    public function __construct() {
        add_action( 'pre_get_posts', [ $this, 'filter_portal_content_from_queries' ] );
        add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'filter_sitemap_entries' ], 10, 2 );
    }

    /**
     * Hide portal-restricted pages from searches and archives.
     */
   public function filter_portal_content_from_queries( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    // Hide "portal" post type from all front-end searches and archives
    if ( $query->is_search() || $query->is_archive() || $query->is_feed() ) {
        // Explicitly exclude the "portal" CPT from search
        $excluded_types = [ 'portal' ];

        // Force post_type to exclude "portal" CPT entirely
        $query->set( 'post_type', [ 'post', 'page' ] );
        $query->set( 'post__not_in', [] ); // reset in case another plugin modified

        $user_id = get_current_user_id();
        $unauthorized_pages = [];

        // Hide restricted pages
        $all_pages = get_posts([
            'post_type' => 'page',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        foreach ( $all_pages as $page_id ) {
            $page_portals = get_post_meta( $page_id, '_page_portals', true );
            if ( ! empty( $page_portals ) ) {
                $has_access = false;
                foreach ( $page_portals as $portal_id ) {
                    $portal_users = get_post_meta( $portal_id, '_portal_users', true );
                    if ( is_array( $portal_users ) && in_array( $user_id, $portal_users, true ) ) {
                        $has_access = true;
                        break;
                    }
                }
                if ( ! $has_access ) {
                    $unauthorized_pages[] = $page_id;
                }
            }
        }

        if ( ! empty( $unauthorized_pages ) ) {
            $query->set( 'post__not_in', $unauthorized_pages );
        }
    }
}



    /**
     * Remove restricted pages from sitemaps.
     */
    public function filter_sitemap_entries( $args, $post_type ) {
        if ( 'page' !== $post_type ) {
            return $args;
        }

        $user_id = get_current_user_id();
        $unauthorized_pages = [];

        $pages = get_posts([
            'post_type' => 'page',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        foreach ( $pages as $page_id ) {
            $page_portals = get_post_meta( $page_id, '_page_portals', true );
            if ( ! empty( $page_portals ) ) {
                $has_access = false;

                foreach ( $page_portals as $portal_id ) {
                    $portal_users = get_post_meta( $portal_id, '_portal_users', true );
                    if ( is_array( $portal_users ) && in_array( $user_id, $portal_users ) ) {
                        $has_access = true;
                        break;
                    }
                }

                if ( ! $has_access ) {
                    $unauthorized_pages[] = $page_id;
                }
            }
        }

        if ( ! empty( $unauthorized_pages ) ) {
            $args['post__not_in'] = $unauthorized_pages;
        }

        return $args;
    }
}
