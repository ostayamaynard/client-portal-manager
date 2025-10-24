<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Portal_Switcher {
    const QV = 'cpm';
    const ACTION_CHOOSE = 'choose';
    const ACTION_SET = 'set';
    const COOKIE = 'cpm_active_portal';

    public function __construct() {
        add_action( 'init', [ $this, 'add_rewrite' ] );
        add_filter( 'query_vars', [ $this, 'query_vars' ] );
        add_action( 'template_redirect', [ $this, 'route' ], 0 );
        add_action( 'wp_logout', [ $this, 'clear_cookie' ] );
    }

    public function add_rewrite() {
        add_rewrite_rule( '^portal/choose/?$', 'index.php?'.self::QV.'='.self::ACTION_CHOOSE, 'top' );
        add_rewrite_rule( '^portal/set/([0-9]+)/?$', 'index.php?'.self::QV.'='.self::ACTION_SET.'&portal_id=$matches[1]', 'top' );
    }

    public function query_vars( $vars ) {
        $vars[] = self::QV;
        $vars[] = 'portal_id';
        return $vars;
    }

    public function route() {
        $act = get_query_var( self::QV );
        if ( ! $act ) return;

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( home_url( '/portal/choose/' ) ) );
            exit;
        }

        if ( $act === self::ACTION_CHOOSE ) {
            $this->render_choose();
            exit;
        }

        if ( $act === self::ACTION_SET ) {
            $this->pick_portal();
            exit;
        }
    }

    private function render_choose() {
        $user_id = get_current_user_id();
        $portals = get_posts([
            'post_type'   => 'portal',
            'numberposts' => -1,
            'meta_query'  => [
                'relation' => 'OR',
                [
                    'key'     => '_portal_users',
                    'value'   => ':"' . $user_id . '";',
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => '_portal_users',
                    'value'   => $user_id,
                    'compare' => '=',
                ],
            ],
        ]);

        status_header(200);
        nocache_headers();

        echo '<!doctype html><html><head><meta charset="utf-8"><title>Choose Portal</title>';
        wp_head();
        echo '</head><body class="cpm-choose">';
        echo '<main style="max-width:640px;margin:4rem auto;font:16px/1.5 system-ui">';
        echo '<h1 style="margin-bottom:1rem;">Choose your portal</h1>';
        if ( empty( $portals ) ) {
            echo '<p>No portals assigned.</p>';
            echo '</main>';
            wp_footer(); echo '</body></html>';
            return;
        }
        echo '<ul style="list-style:none;padding:0;margin:0;">';
        foreach ( $portals as $p ) {
            $url = home_url( '/portal/set/' . $p->ID . '/' );
            echo '<li style="margin:.5rem 0;"><a class="button" href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
        }
        echo '</ul></main>';
        wp_footer(); echo '</body></html>';
    }

    private function pick_portal() {
        $portal_id = absint( get_query_var('portal_id') );
        if ( ! $portal_id ) {
            wp_safe_redirect( home_url( '/portal/choose/' ) ); exit;
        }
        // validate membership
        $user_id = get_current_user_id();
        $users   = (array) get_post_meta( $portal_id, '_portal_users', true );
        $users   = array_map( 'intval', $users );
        if ( ! in_array( $user_id, $users, true ) && ! current_user_can('manage_options') ) {
            // not allowed → 404
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            $template = get_404_template();
            if ( $template ) include $template; else wp_die( esc_html__('Not found','client-portal-manager'), 404 );
            exit;
        }

        // session-length cookie (no explicit expiry)
        setcookie( self::COOKIE, (string) $portal_id, 0, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
        wp_safe_redirect( get_permalink( $portal_id ) );
        exit;
    }

    public function clear_cookie() {
        if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
            setcookie( self::COOKIE, '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
        }
    }

    // helper to read active portal (use in other classes if needed)
    public static function get_active_portal_id() {
        return isset( $_COOKIE[ self::COOKIE ] ) ? absint( $_COOKIE[ self::COOKIE ] ) : 0;
    }
}
