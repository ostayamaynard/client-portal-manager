<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Sitemaps {

    public function __construct() {
        // Remove the entire portal post type from sitemaps
        add_filter( 'wp_sitemaps_post_types', [ $this, 'remove_portal_from_sitemaps' ] );

        // Exclude pages that are assigned to any portal
        add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'filter_pages_in_sitemaps' ], 10, 2 );
    }

    public function remove_portal_from_sitemaps( $post_types ) {
        if ( isset( $post_types['portal'] ) ) {
            unset( $post_types['portal'] );
        }
        return $post_types;
    }

    public function filter_pages_in_sitemaps( $args, $post_type ) {
        if ( $post_type !== 'page' ) {
            return $args;
        }

        // Only include pages that have NO _page_portals meta (i.e., public pages)
        $args['meta_query'] = [
            'relation' => 'OR',
            [
                'key'     => '_page_portals',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_page_portals',
                'value'   => '',
                'compare' => '=',
            ],
        ];

        return $args;
    }
}
