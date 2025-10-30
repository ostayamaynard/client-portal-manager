<?php
/**
 * Client Portal Manager - Login Redirect
 * Handles user redirection after login
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Login_Redirect {

    public function __construct() {
        add_filter( 'login_redirect', [ $this, 'redirect_user_to_portal' ], 10, 3 );
        add_action( 'init', [ $this, 'handle_portal_selection' ] );
        add_shortcode( 'portal_selector', [ $this, 'render_portal_selector' ] );
    }

    /**
     * Redirect user after login
     */
    public function redirect_user_to_portal( $redirect_to, $request, $user ) {
        if ( ! $user || is_wp_error( $user ) ) {
            return $redirect_to;
        }

        $user_id = $user->ID;

        // Admins go to dashboard
        if ( current_user_can( 'manage_options', $user_id ) ) {
            error_log( "Admin {$user_id} → dashboard" );
            return admin_url();
        }

        // Get user's assigned portals
        $portals = get_posts([
            'post_type'   => 'portal',
            'numberposts' => -1,
            'fields'      => 'ids',
            'post_status' => 'publish',
            'meta_query'  => [[
                'key'     => '_portal_users',
                'value'   => 'i:' . (int) $user_id . ';',
                'compare' => 'LIKE'
            ]],
        ]);

        // No portals - redirect to home
        if ( empty( $portals ) ) {
            error_log( "User {$user_id} has no portals → home" );
            return home_url( '/' );
        }

        // Single portal - redirect directly to the PORTAL POST
        if ( count( $portals ) === 1 ) {
            $portal_url = get_permalink( $portals[0] );
            error_log( "User {$user_id} → single portal {$portals[0]} at {$portal_url}" );
            
            // Store active portal
            set_transient( 'cpm_active_portal_' . $user_id, $portals[0], HOUR_IN_SECONDS );
            
            return $portal_url;
        }

        // Multiple portals - show selection page
        error_log( "User {$user_id} → portal selection (" . count( $portals ) . " portals)" );
        
        // Store in transient
        set_transient( 'cpm_user_portals_' . $user_id, $portals, HOUR_IN_SECONDS );
        
        $selection_page = $this->get_or_create_selection_page();
        return get_permalink( $selection_page );
    }

    /**
     * Handle portal selection form submission
     */
    public function handle_portal_selection() {
        if ( ! isset( $_POST['cpm_select_portal'], $_POST['portal_id'], $_POST['cpm_nonce'] ) ) {
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['cpm_nonce'], 'cpm_portal_selection' ) ) {
            wp_die( __( 'Security check failed.', 'client-portal-manager' ) );
        }

        $portal_id = intval( $_POST['portal_id'] );
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        // Get user's allowed portals from transient
        $user_portals = get_transient( 'cpm_user_portals_' . $user_id );
        
        if ( ! $user_portals ) {
            // Transient expired, requery
            $user_portals = get_posts([
                'post_type'   => 'portal',
                'numberposts' => -1,
                'fields'      => 'ids',
                'post_status' => 'publish',
                'meta_query'  => [[
                    'key'     => '_portal_users',
                    'value'   => 'i:' . (int) $user_id . ';',
                    'compare' => 'LIKE'
                ]],
            ]);
        }

        // Verify user has access to selected portal
        if ( ! in_array( $portal_id, array_map( 'intval', $user_portals ), true ) ) {
            wp_die( __( 'Unauthorized portal selection.', 'client-portal-manager' ) );
        }

        // Store active portal
        set_transient( 'cpm_active_portal_' . $user_id, $portal_id, HOUR_IN_SECONDS );

        // Clean up
        delete_transient( 'cpm_user_portals_' . $user_id );

        // Redirect to selected portal POST (not page)
        error_log( "User {$user_id} selected portal {$portal_id}" );
        wp_safe_redirect( get_permalink( $portal_id ) );
        exit;
    }

    /**
     * Get or create portal selection page
     */
    private function get_or_create_selection_page() {
        $page = get_page_by_path( 'portal-selection' );
        
        if ( ! $page ) {
            $page_id = wp_insert_post([
                'post_title'   => __( 'Select Your Portal', 'client-portal-manager' ),
                'post_name'    => 'portal-selection',
                'post_content' => '[portal_selector]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'meta_input'   => [
                    '_cpm_system_page' => 'yes'
                ]
            ]);
            
            if ( $page_id ) {
                error_log( "✅ Created portal selection page: {$page_id}" );
                return $page_id;
            }
            
            return 0;
        }
        
        // Mark as system page if not already
        if ( get_post_meta( $page->ID, '_cpm_system_page', true ) !== 'yes' ) {
            update_post_meta( $page->ID, '_cpm_system_page', 'yes' );
        }
        
        return $page->ID;
    }

    /**
     * Render portal selector shortcode
     */
    public function render_portal_selector( $atts ) {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            return '<p>' . __( 'Please log in to select a portal.', 'client-portal-manager' ) . '</p>';
        }

        // Get portals from transient or query
        $user_portals = get_transient( 'cpm_user_portals_' . $user_id );
        
        if ( ! $user_portals ) {
            $user_portals = get_posts([
                'post_type'   => 'portal',
                'numberposts' => -1,
                'fields'      => 'ids',
                'post_status' => 'publish',
                'meta_query'  => [[
                    'key'     => '_portal_users',
                    'value'   => 'i:' . (int) $user_id . ';',
                    'compare' => 'LIKE'
                ]],
            ]);
        }

        if ( empty( $user_portals ) ) {
            return '<p>' . __( 'No portals available.', 'client-portal-manager' ) . '</p>';
        }

        ob_start();
        ?>
        <div class="cpm-portal-selector" style="max-width:600px;margin:3rem auto;padding:2.5rem;background:#fff;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
            <h2 style="text-align:center;margin-bottom:1rem;font-size:1.8rem;color:#1d2733;">
                <?php _e( 'Select Your Portal', 'client-portal-manager' ); ?>
            </h2>
            <p style="text-align:center;color:#555;margin-bottom:2.5rem;font-size:1.05rem;">
                <?php _e( 'You have access to multiple portals. Please choose one to continue:', 'client-portal-manager' ); ?>
            </p>
            
            <div style="display:grid;gap:1rem;">
                <?php foreach ( $user_portals as $portal_id ) : 
                    $portal = get_post( $portal_id );
                    if ( ! $portal ) continue;
                ?>
                    <form method="post" style="margin:0;">
                        <?php wp_nonce_field( 'cpm_portal_selection', 'cpm_nonce' ); ?>
                        <input type="hidden" name="portal_id" value="<?php echo esc_attr( $portal_id ); ?>">
                        
                        <button type="submit" name="cpm_select_portal" value="1" style="
                            width:100%;
                            padding:1.5rem;
                            background:#0274be;
                            color:#fff;
                            border:none;
                            border-radius:8px;
                            font-size:1.1rem;
                            font-weight:600;
                            cursor:pointer;
                            transition:all 0.2s ease;
                            text-align:left;
                            box-shadow:0 2px 4px rgba(0,0,0,0.1);
                        " onmouseover="this.style.background='#005c99';this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" 
                           onmouseout="this.style.background='#0274be';this.style.transform='translateY(0)';this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <div style="font-size:1.2rem;margin-bottom:0.3rem;">
                                        <?php echo esc_html( $portal->post_title ); ?>
                                    </div>
                                    <?php if ( $portal->post_excerpt ) : ?>
                                        <div style="font-size:0.9rem;font-weight:400;opacity:0.9;">
                                            <?php echo esc_html( $portal->post_excerpt ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span style="font-size:1.5rem;opacity:0.8;">→</span>
                            </div>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}