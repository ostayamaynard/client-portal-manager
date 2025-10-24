<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Access_Logger {

    public function __construct() {
        add_action( 'init', [ $this, 'register_log_cpt' ] );
        add_action( 'template_redirect', [ $this, 'log_access_event' ], 20 );
    }

    /**
     * ✅ Register custom post type for logs
     */
    public function register_log_cpt() {
        register_post_type( 'cpm_access_log', [
            'labels' => [
                'name' => 'Access Logs',
                'singular_name' => 'Access Log',
            ],
            'public' => false,
            'show_ui' => true,
            'menu_position' => 80,
            'menu_icon' => 'dashicons-clipboard',
            'supports' => ['title', 'custom-fields'],
        ]);
    }

    /**
     * ✅ Log access attempts
     */
    public function log_access_event() {
        if ( ! is_singular( 'portal' ) ) return;

        global $post;
        $user = wp_get_current_user();
        $allowed_users = get_post_meta( $post->ID, '_portal_users', true );
        $settings = get_option( 'cpm_settings', [] );
        $redirect_page = $settings['redirect_page'] ?? '';
        $redirect_url = $redirect_page ? get_permalink( $redirect_page ) : wp_login_url();

        $status = 'granted';
        $note = 'Access granted.';

        if ( ! $user->ID || ( ! empty( $allowed_users ) && ! in_array( $user->ID, (array) $allowed_users ) ) ) {
            $status = 'denied';
            $note = 'Access denied (user not authorized).';

            // ✅ Log denied attempt before redirect
            $this->create_log_entry( $user, $post, $status, $note );

            wp_safe_redirect( $redirect_url );
            exit;
        }

        // ✅ Log granted access
        $this->create_log_entry( $user, $post, $status, $note );
    }

    /**
     * ✅ Create log post entry
     */
    private function create_log_entry( $user, $portal, $status, $note ) {
        $title = sprintf(
            '[%s] %s — %s',
            strtoupper($status),
            $user->user_login ?: 'Guest',
            get_the_title( $portal->ID )
        );

        $log_id = wp_insert_post([
            'post_type'   => 'cpm_access_log',
            'post_title'  => $title,
            'post_status' => 'publish',
        ]);

        if ( $log_id ) {
            update_post_meta( $log_id, '_user_id', $user->ID );
            update_post_meta( $log_id, '_user_name', $user->user_login ?: 'Guest' );
            update_post_meta( $log_id, '_portal_id', $portal->ID );
            update_post_meta( $log_id, '_portal_title', get_the_title( $portal->ID ) );
            update_post_meta( $log_id, '_status', $status );
            update_post_meta( $log_id, '_note', $note );
            update_post_meta( $log_id, '_timestamp', current_time('mysql') );
        }
    }
}
