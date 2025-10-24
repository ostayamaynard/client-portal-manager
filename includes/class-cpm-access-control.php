<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Access_Control {

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'restrict_portal_access' ] );
    }

    public function restrict_portal_access() {
        if ( ! is_singular( 'portal' ) ) return;

        global $post;
        $portal_id = $post->ID;

        // Fetch assigned users (array of IDs)
        $allowed_users = get_post_meta( $portal_id, '_portal_users', true );
        $current_user  = wp_get_current_user();

        // ✅ Allow admins and editors full access — they can view any portal
        if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_others_pages' ) ) {
            error_log("✅ Admin/Editor bypassed restriction for portal ID $portal_id");
            return;
        }

        // 🔍 Debug logging
        error_log("🔍 CPM Access Check → Portal ID: $portal_id | User: {$current_user->user_login} ({$current_user->ID}) | Allowed: " . print_r($allowed_users, true));

        // If no restriction set → allow all
        if ( empty( $allowed_users ) ) return;

        // Ensure allowed_users is always an array
        if ( ! is_array( $allowed_users ) ) {
            $allowed_users = (array) maybe_unserialize( $allowed_users );
        }

        // If not logged in → redirect to login page
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( get_permalink( $portal_id ) ) );
            exit;
        }

        // If logged in but not authorized
        if ( ! in_array( $current_user->ID, array_map( 'intval', $allowed_users ) ) ) {
            $settings = get_option( 'cpm_settings', [] );

            // Fetch redirect and message settings
            $redirect_page  = $settings['redirect_page'] ?? home_url('/');
            $access_message = $settings['access_denied_message'] ?? 'Access Denied. Please contact the administrator.';

            // Log denial
            error_log("🚫 Access denied for user {$current_user->user_login} ({$current_user->ID}) on portal $portal_id");

            // 🧭 Redirect if redirect page is defined
            if ( ! empty( $redirect_page ) && ! str_contains( $redirect_page, get_permalink( $portal_id ) ) ) {
                wp_safe_redirect( esc_url( $redirect_page ) );
                exit;
            }

            // 🚫 Otherwise, display message
            wp_die( esc_html( $access_message ), 'Access Denied', [
                'response'  => 403,
                'back_link' => true,
            ]);
            exit;
        }

        // ✅ TEMP: Visual debug banner (for testing only)
        if ( is_user_logged_in() && in_array( $current_user->ID, array_map( 'intval', (array) $allowed_users ) ) ) {
            echo '<div style="position:fixed;top:0;left:0;right:0;padding:8px 12px;background:#0a0;color:#fff;z-index:9999;font-size:13px;font-family:monospace;">✅ ACCESS GRANTED for ' . esc_html( $current_user->user_login ) . ' (ID: ' . esc_html( $current_user->ID ) . ')</div>';
        } else {
            echo '<div style="position:fixed;top:0;left:0;right:0;padding:8px 12px;background:#a00;color:#fff;z-index:9999;font-size:13px;font-family:monospace;">🚫 ACCESS DENIED for ' . esc_html( $current_user->user_login ) . ' (ID: ' . esc_html( $current_user->ID ) . ')</div>';
        }
    }
}
