<?php
/**
 * Client Portal Manager - Portal Switcher
 * Allows users with multiple portals to switch between them
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Portal_Switcher {

    public function __construct() {
        // Add switcher to portal pages
        add_action( 'wp_footer', [ $this, 'render_switcher_widget' ] );
        
        // Handle switch request
        add_action( 'init', [ $this, 'handle_switch_request' ] );
        
        // Add shortcode for manual placement
        add_shortcode( 'portal_switcher', [ $this, 'render_switcher_dropdown' ] );
    }

    /**
     * Render floating switcher widget (only on portal pages)
     */
    public function render_switcher_widget() {
        // Only show on portal pages or pages assigned to portals
        if ( ! $this->should_show_switcher() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        $user_portals = $this->get_user_portals( $user_id );
        
        // Only show if user has multiple portals
        if ( count( $user_portals ) < 2 ) {
            return;
        }

        $current_portal_id = $this->get_current_portal_id();

        ?>
        <div id="cpm-portal-switcher" style="
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #fff;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9998;
            min-width: 200px;
            border: 2px solid #0274be;
        ">
            <form method="post" style="margin:0;">
                <?php wp_nonce_field( 'cpm_switch_portal', 'cpm_switch_nonce' ); ?>
                
                <label style="display:block;font-weight:600;margin-bottom:8px;color:#1d2733;font-size:13px;">
                    🔄 <?php _e( 'Switch Portal', 'client-portal-manager' ); ?>
                </label>
                
                <select 
                    name="cpm_portal_id" 
                    onchange="this.form.submit()"
                    style="
                        width:100%;
                        padding:8px 10px;
                        border:1px solid #ddd;
                        border-radius:6px;
                        font-size:14px;
                        cursor:pointer;
                        background:#f9f9f9;
                    "
                >
                    <?php foreach ( $user_portals as $portal_id ) : 
                        $portal = get_post( $portal_id );
                        if ( ! $portal ) continue;
                    ?>
                        <option 
                            value="<?php echo esc_attr( $portal_id ); ?>"
                            <?php selected( $current_portal_id, $portal_id ); ?>
                        >
                            <?php echo esc_html( $portal->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <noscript>
                    <button type="submit" style="
                        margin-top:8px;
                        width:100%;
                        padding:6px;
                        background:#0274be;
                        color:#fff;
                        border:none;
                        border-radius:4px;
                        cursor:pointer;
                    ">
                        <?php _e( 'Switch', 'client-portal-manager' ); ?>
                    </button>
                </noscript>
            </form>
        </div>
        <?php
    }

    /**
     * Render dropdown for shortcode
     */
    public function render_switcher_dropdown( $atts ) {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            return '';
        }

        $user_portals = $this->get_user_portals( $user_id );
        
        if ( count( $user_portals ) < 2 ) {
            return '';
        }

        $current_portal_id = $this->get_current_portal_id();

        ob_start();
        ?>
        <div class="cpm-portal-switcher-inline" style="margin:1rem 0;">
            <form method="post" style="display:inline-block;">
                <?php wp_nonce_field( 'cpm_switch_portal', 'cpm_switch_nonce' ); ?>
                
                <label style="margin-right:10px;font-weight:600;">
                    <?php _e( 'Switch Portal:', 'client-portal-manager' ); ?>
                </label>
                
                <select 
                    name="cpm_portal_id" 
                    onchange="this.form.submit()"
                    style="padding:6px 10px;border-radius:4px;"
                >
                    <?php foreach ( $user_portals as $portal_id ) : 
                        $portal = get_post( $portal_id );
                        if ( ! $portal ) continue;
                    ?>
                        <option 
                            value="<?php echo esc_attr( $portal_id ); ?>"
                            <?php selected( $current_portal_id, $portal_id ); ?>
                        >
                            <?php echo esc_html( $portal->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <noscript>
                    <button type="submit" class="button">
                        <?php _e( 'Go', 'client-portal-manager' ); ?>
                    </button>
                </noscript>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle portal switch request
     */
    public function handle_switch_request() {
        if ( ! isset( $_POST['cpm_portal_id'], $_POST['cpm_switch_nonce'] ) ) {
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['cpm_switch_nonce'], 'cpm_switch_portal' ) ) {
            wp_die( __( 'Security check failed.', 'client-portal-manager' ) );
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        $portal_id = intval( $_POST['cpm_portal_id'] );
        
        // Verify user has access to this portal
        $user_portals = $this->get_user_portals( $user_id );
        
        if ( ! in_array( $portal_id, $user_portals, true ) ) {
            wp_die( __( 'You do not have access to this portal.', 'client-portal-manager' ) );
        }

        // Store in transient (1 hour)
        set_transient( 'cpm_active_portal_' . $user_id, $portal_id, HOUR_IN_SECONDS );
        
        error_log( "🔄 User {$user_id} switched to portal {$portal_id}" );

        // Redirect to the new portal
        wp_safe_redirect( get_permalink( $portal_id ) );
        exit;
    }

    /**
     * Get current portal ID
     */
    private function get_current_portal_id() {
        // Check if viewing a portal directly
        if ( is_singular( 'portal' ) ) {
            return get_queried_object_id();
        }

        // Check if on a page assigned to portals
        if ( is_page() ) {
            $page_id = get_queried_object_id();
            $page_portals = $this->get_page_portals( $page_id );
            
            if ( ! empty( $page_portals ) ) {
                // Check if user has active portal stored
                $user_id = get_current_user_id();
                $active_portal = get_transient( 'cpm_active_portal_' . $user_id );
                
                // If active portal is in page's portals, use it
                if ( $active_portal && in_array( $active_portal, $page_portals, true ) ) {
                    return $active_portal;
                }
                
                // Otherwise use first assigned portal
                return $page_portals[0];
            }
        }

        // Check stored active portal
        $user_id = get_current_user_id();
        if ( $user_id ) {
            $active_portal = get_transient( 'cpm_active_portal_' . $user_id );
            if ( $active_portal ) {
                return $active_portal;
            }
            
            // Fallback to first user portal
            $user_portals = $this->get_user_portals( $user_id );
            if ( ! empty( $user_portals ) ) {
                return $user_portals[0];
            }
        }

        return 0;
    }

    /**
     * Should the switcher be shown?
     */
    private function should_show_switcher() {
        // Don't show in admin
        if ( is_admin() ) {
            return false;
        }

        // Show on portal pages
        if ( is_singular( 'portal' ) ) {
            return true;
        }

        // Show on pages assigned to portals
        if ( is_page() ) {
            $page_id = get_queried_object_id();
            $page_portals = $this->get_page_portals( $page_id );
            return ! empty( $page_portals );
        }

        return false;
    }

    /**
     * Get portals assigned to a page
     */
    private function get_page_portals( $page_id ) {
        $portals = get_post_meta( $page_id, '_page_portals', true );
        if ( ! is_array( $portals ) ) {
            $portals = maybe_unserialize( $portals );
        }
        return array_map( 'intval', (array) $portals );
    }

    /**
     * Get portals assigned to a user
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
     * Get active portal ID (public static for other classes)
     */
    public static function get_active_portal_id() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return 0;
        
        return (int) get_transient( 'cpm_active_portal_' . $user_id );
    }
}