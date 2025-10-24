<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'CPM_DEBUG' ) ) {
	define( 'CPM_DEBUG', true );
}

class CPM_Page_Access {

	public function __construct() {
		// Meta boxes for page access
		add_action( 'add_meta_boxes', [ $this, 'add_portal_meta_box' ] );
		add_action( 'save_post_page', [ $this, 'save_page_portals' ] );

		// Access restrictions
		add_action( 'template_redirect', [ $this, 'restrict_page_access' ], 1 );
		add_action( 'template_redirect', [ $this, 'restrict_portal_access' ], 1 );
		add_action( 'template_redirect', [ $this, 'restrict_any_singular_with_portals' ], 1 );

		add_action( 'after_setup_theme', [ $this, 'astra_menu_override' ], 20 );

		if ( CPM_DEBUG ) add_action( 'wp_footer', [ $this, 'debug_footer' ], 9999 );
	}

	/* --------------------------------------------------------------------------
	 * META BOX
	 * -------------------------------------------------------------------------- */
	public function add_portal_meta_box() {
		add_meta_box(
			'cpm_page_portals',
			__( 'Portal Access', 'client-portal-manager' ),
			[ $this, 'render_portal_meta_box' ],
			'page',
			'side'
		);
	}

	public function render_portal_meta_box( $post ) {
		wp_nonce_field( 'cpm_save_page_portals', 'cpm_page_portals_nonce' );
		$assigned = get_post_meta( $post->ID, '_page_portals', true ) ?: [];

		$portals = get_posts([
			'post_type'   => 'portal',
			'numberposts' => -1,
			'post_status' => 'publish',
		]);

		echo '<p><strong>Assign to Portal(s):</strong></p>';
		echo '<p class="description">Select which portals can access this page. If none selected, this page is public.</p>';
		echo '<div style="max-height:150px;overflow-y:auto;border:1px solid #ddd;padding:8px;background:#fafafa;">';

		if ( empty( $portals ) ) {
			echo '<p style="color:#999;margin:0;">No portals available. <a href="' . admin_url('post-new.php?post_type=portal') . '">Create one</a></p>';
		} else {
			foreach ( $portals as $portal ) {
				$checked = in_array( $portal->ID, (array) $assigned, true ) ? 'checked' : '';
				echo '<label style="display:block;margin-bottom:6px;">';
				echo '<input type="checkbox" name="cpm_page_portals[]" value="' . esc_attr( $portal->ID ) . '" ' . $checked . '> ';
				echo esc_html( $portal->post_title );
				echo '</label>';
			}
		}
		echo '</div>';
	}

	public function save_page_portals( $post_id ) {
		if ( ! isset( $_POST['cpm_page_portals_nonce'] ) ||
		     ! wp_verify_nonce( $_POST['cpm_page_portals_nonce'], 'cpm_save_page_portals' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$selected = isset( $_POST['cpm_page_portals'] )
			? array_map( 'intval', $_POST['cpm_page_portals'] )
			: [];

		update_post_meta( $post_id, '_page_portals', $selected );
		error_log("💾 Saved page {$post_id} portal assignments: " . implode(',', $selected));
	}

	/* --------------------------------------------------------------------------
	 * HELPERS
	 * -------------------------------------------------------------------------- */
	private function get_user_portals( $user_id ) {
		if ( ! $user_id ) return [];
		$portals = get_posts([
			'post_type'   => 'portal',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => [[
				'key'     => '_portal_users',
				'value'   => 'i:' . $user_id . ';',
				'compare' => 'LIKE'
			]],
		]);
		return $portals;
	}

	private function get_page_portals( $post_id ) {
		return (array) get_post_meta( $post_id, '_page_portals', true );
	}

	private function is_portal_user( $user_id ) {
		if ( ! $user_id ) return false;
		$portals = $this->get_user_portals( $user_id );
		return ! empty( $portals );
	}

	private function get_portal_menu_id( $portal_id ) {
		$menu_id = (int) get_post_meta( $portal_id, '_portal_menu_id', true );
		if ( ! $menu_id ) {
			$theme_locations = get_nav_menu_locations();
			if ( isset( $theme_locations['primary'] ) ) {
				$menu_id = (int) $theme_locations['primary'];
			}
		}
		return $menu_id;
	}

	private function show_404() {
		global $wp_query;
		$wp_query->set_404();
		status_header(404);
		nocache_headers();
		$template = get_404_template();
		if ( $template ) {
			include $template;
		} else {
			wp_die( esc_html__( 'Not found', 'client-portal-manager' ), 404 );
		}
		exit;
	}

	/* --------------------------------------------------------------------------
	 * ACCESS CONTROL
	 * -------------------------------------------------------------------------- */

	public function restrict_page_access() {
		if ( ! is_page() ) return;
		global $post;

		$user_id      = get_current_user_id();
		$page_portals = $this->get_page_portals( $post->ID );
		$user_portals = $this->get_user_portals( $user_id );

		// Admins/editors can access everything
		if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_others_pages' ) ) return;

		// If page is public, everyone can see it
		if ( empty( $page_portals ) ) return;

		// Guest → redirect to login
		if ( ! $user_id ) {
			wp_safe_redirect( wp_login_url( get_permalink( $post->ID ) ) );
			exit;
		}

		// Authorized?
		$has_access = ! empty( array_intersect( $page_portals, $user_portals ) );
		if ( ! $has_access ) $this->show_404();
	}

	public function restrict_portal_access() {
		if ( ! is_singular( 'portal' ) ) return;

		$portal_id = get_queried_object_id();
		$user_id   = get_current_user_id();
		$users     = (array) get_post_meta( $portal_id, '_portal_users', true );

		if ( current_user_can( 'manage_options' ) ) return;

		if ( ! $user_id ) {
			wp_safe_redirect( wp_login_url( get_permalink( $portal_id ) ) );
			exit;
		}

		if ( empty( $users ) || ! in_array( $user_id, array_map( 'intval', $users ), true ) ) {
			$this->show_404();
		}
	}

	/**
	 * ✅ NEW universal restriction for any post type that uses _page_portals meta
	 */
	public function restrict_any_singular_with_portals() {
		if ( ! is_singular() ) return;
		if ( is_page() || is_singular( 'portal' ) ) return;

		$post_id = get_queried_object_id();
		if ( ! $post_id ) return;

		$required_portals = (array) get_post_meta( $post_id, '_page_portals', true );
		$required_portals = array_filter( array_map( 'intval', $required_portals ) );
		if ( empty( $required_portals ) ) return;

		if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_others_pages' ) ) return;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_safe_redirect( wp_login_url( get_permalink( $post_id ) ) );
			exit;
		}

		$user_portals = $this->get_user_portals( $user_id );
		if ( empty( array_intersect( $required_portals, $user_portals ) ) ) {
			$this->show_404();
		}
	}

	/* --------------------------------------------------------------------------
	 * ASTRA MENU OVERRIDE
	 * -------------------------------------------------------------------------- */
	public function astra_menu_override() {
		if ( is_admin() ) return;

		add_filter( 'wp_nav_menu_args', function( $args ) {
			$user_id = get_current_user_id();
			if ( ! $user_id ) return $args;

			$user_portals = $this->get_user_portals( $user_id );
			if ( empty( $user_portals ) ) return $args;

			$current_post = get_queried_object();
			$current_portal_id = null;

			if ( is_singular( 'portal' ) ) {
				$current_portal_id = $current_post->ID;
			} elseif ( is_page() ) {
				$page_portals = $this->get_page_portals( $current_post->ID );
				foreach ( $user_portals as $pid ) {
					if ( in_array( $pid, $page_portals, true ) ) {
						$current_portal_id = $pid;
						break;
					}
				}
			}

			if ( ! $current_portal_id ) $current_portal_id = $user_portals[0];
			$menu_id = $this->get_portal_menu_id( $current_portal_id );

			if ( $menu_id ) {
				$args['menu'] = $menu_id;
				if ( empty( $args['theme_location'] ) ) $args['theme_location'] = 'primary';
			}

			return $args;
		}, 999 );
	}

	/* --------------------------------------------------------------------------
	 * DEBUG FOOTER
	 * -------------------------------------------------------------------------- */
	public function debug_footer() {
		if ( is_admin() ) return;

		$uid = get_current_user_id();
		$post_id = get_queried_object_id();
		$post_type = get_post_type( $post_id );

		$user_portals = $this->get_user_portals( $uid );
		$page_portals = $this->get_page_portals( $post_id );
		$resolved_portal = ! empty( $page_portals ) ? $page_portals[0] : ( ! empty( $user_portals ) ? $user_portals[0] : 0 );
		$menu_id = $this->get_portal_menu_id( $resolved_portal );

		$msg = sprintf(
			'CPM DEBUG | type=%s post=%d | uid=%d | portals=%s | page_portals=%s | menu=%d',
			$post_type, $post_id, $uid,
			implode( ',', $user_portals ),
			implode( ',', $page_portals ),
			$menu_id
		);

		echo '<div style="position:fixed;left:0;right:0;bottom:0;background:#000;color:#0f0;padding:8px 12px;font:11px monospace;z-index:99999;">'
			. esc_html( $msg )
			. '</div>';
	}
}
