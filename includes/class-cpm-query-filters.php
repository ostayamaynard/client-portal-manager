<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Client Portal Manager — Query Filters (FIXED)
 *
 * Goals:
 * - Never interfere with singular `portal` views.
 * - Keep portals out of search/archives.
 * - Hide restricted pages from users who don't have access,
 *   but do NOT touch singular requests (404s must be driven only by access control).
 */
class CPM_Query_Filters {

	public function __construct() {
		add_action( 'pre_get_posts', [ $this, 'adjust_queries' ], 999 );
		add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'sitemaps_filter' ], 10, 2 );
	}

	/**
	 * Main query adjustments.
	 */
	public function adjust_queries( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) return;

		// 🔒 Absolutely never touch a singular portal page
		if ( $q->is_singular( 'portal' ) ) {
			// Ensure WP is actually querying the portal CPT
			$q->set( 'post_type', 'portal' );
			return;
		}

		// 🔍 Remove portals from search, home and archives
		if ( $q->is_search() || $q->is_home() || $q->is_archive() ) {
			$post_types = $q->get( 'post_type' );

			if ( empty( $post_types ) ) {
				// default WP types when empty
				$post_types = [ 'post', 'page' ];
			} elseif ( $post_types === 'any' ) {
				// if "any", just make sure "portal" is not returned
				$post_types = [ 'post', 'page' ];
			} else {
				$post_types = (array) $post_types;
			}

			$q->set( 'post_type', array_values( array_diff( $post_types, [ 'portal' ] ) ) );
		}

		/**
		 * 🚫 Hide restricted pages from users who aren't allowed,
		 * but (IMPORTANT) do not alter singular requests here.
		 * Page access decisions for singulars are handled in CPM_Page_Access::restrict_page_access().
		 */
		if ( $q->is_search() || $q->is_home() || $q->is_archive() ) {
			$user_id = get_current_user_id();
			$user_portals = $this->get_user_portals( $user_id );

			// Build a meta query that excludes pages assigned to portals the user does NOT belong to
			$meta_query = (array) $q->get( 'meta_query' );
			$meta_query['relation'] = 'AND';

			if ( empty( $user_portals ) ) {
				// Not a portal user: exclude any page that has _page_portals set (i.e., restricted)
				$meta_query[] = [
					'key'     => '_page_portals',
					'compare' => 'NOT EXISTS',
				];
			} else {
				// Is a portal user: allow public pages to disappear from their experience by default
				// and only show pages assigned to their portals in these listing contexts.
				$meta_query[] = [
					'key'     => '_page_portals',
					'value'   => array_map( 'strval', $user_portals ),
					'compare' => 'IN',
				];
			}

			$q->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Remove restricted content from sitemaps & hide portal CPT from sitemaps.
	 */
	public function sitemaps_filter( $args, $post_type ) {
		if ( $post_type === 'portal' ) {
			// no portals in sitemaps
			$args['post__in'] = [ 0 ];
			return $args;
		}

		$user_id = get_current_user_id();
		$user_portals = $this->get_user_portals( $user_id );

		if ( empty( $user_portals ) ) {
			// visitors / non-portal users: only pages without assignments
			$args['meta_query'][] = [
				'key'     => '_page_portals',
				'compare' => 'NOT EXISTS',
			];
		} else {
			// portal users: only pages assigned to their portals
			$args['meta_query'][] = [
				'key'     => '_page_portals',
				'value'   => array_map( 'strval', $user_portals ),
				'compare' => 'IN',
			];
		}

		return $args;
	}

	private function get_user_portals( $user_id ) {
		if ( ! $user_id ) return [];
		$ids = get_posts( [
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
		] );
		return array_map( 'intval', $ids );
	}
}
