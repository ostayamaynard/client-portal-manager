<?php
/**
 * Client Portal Manager - Portal Switcher
 * Allows users with multiple portals to switch between them
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Portal_Switcher {

    public function __construct() {
        add_action( 'wp_footer', [ $this, 'render_switcher_widget' ], 999 );
        add_action( 'template_redirect', [ $this, 'handle_portal_switch' ], 5 );
    }

    /**
     * Render the floating portal switcher widget
     */
    public function render_switcher_widget() {
        // Don't show in admin
        if ( is_admin() ) {
            return;
        }

        $user_id = get_current_user_id();

        // Must be logged in
        if ( ! $user_id ) {
            return;
        }

        // Get user's portals
        $user_portals = $this->get_user_portals( $user_id );

        // Only show if user has 2+ portals
        if ( count( $user_portals ) < 2 ) {
            return;
        }

        // Get current active portal
        $active_portal_id = get_transient( 'cpm_active_portal_' . $user_id );

        ?>
        <div id="cpm-portal-switcher" style="
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #fff;
            border: 2px solid #0073aa;
            border-radius: 6px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 99998;
            min-width: 200px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        ">
            <div style="margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #333;">
                Switch Portal
            </div>
            
            <form method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="margin: 0;">
                <?php wp_nonce_field( 'cpm_switch_portal', 'cpm_switch_nonce' ); ?>
                <input type="hidden" name="cpm_switch_portal" value="1">
                
                <select 
                    name="cpm_portal_id" 
                    onchange="this.form.submit()"
                    style="
                        width: 100%;
                        padding: 8px 10px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        font-size: 14px;
                        background: #fff;
                        cursor: pointer;
                    "
                >
                    <?php foreach ( $user_portals as $portal_id ) : ?>
                        <?php $portal = get_post( $portal_id ); ?>
                        <option 
                            value="<?php echo esc_attr( $portal_id ); ?>"
                            <?php selected( $active_portal_id, $portal_id ); ?>
                        >
                            <?php echo esc_html( $portal->post_title ); ?>
                            <?php if ( $portal_id == $active_portal_id ) : ?>
                                ✓
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <noscript>
                    <button 
                        type="submit" 
                        name="cpm_switch_portal"
                        style="
                            margin-top: 8px;
                            padding: 6px 12px;
                            background: #0073aa;
                            color: #fff;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            width: 100%;
                        "
                    >
                        Switch Portal
                    </button>
                </noscript>
            </form>
        </div>

        <style>
            #cpm-portal-switcher:hover {
                box-shadow: 0 6px 16px rgba(0,0,0,0.2);
            }

            @media (max-width: 768px) {
                #cpm-portal-switcher {
                    bottom: 10px;
                    right: 10px;
                    left: 10px;
                    min-width: auto;
                }
            }

            #cpm-portal-switcher form.submitting {
                opacity: 0.6;
                pointer-events: none;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.querySelector('#cpm-portal-switcher form');
                if (form) {
                    form.addEventListener('submit', function() {
                        this.classList.add('submitting');
                    });
                }
            });
        </script>
        <?php
    }

    /**
     * Handle portal switch form submission
     */
    public function handle_portal_switch() {
        if ( ! isset( $_POST['cpm_switch_portal'] ) && ! isset( $_POST['cpm_portal_id'] ) ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['cpm_switch_nonce'] ) || 
             ! wp_verify_nonce( $_POST['cpm_switch_nonce'], 'cpm_switch_portal' ) ) {
            wp_die( 'Security check failed' );
        }

        $user_id    = get_current_user_id();
        $portal_id  = isset( $_POST['cpm_portal_id'] ) ? intval( $_POST['cpm_portal_id'] ) : 0;

        if ( ! $user_id || ! $portal_id ) {
            return;
        }

        // Verify user has access to this portal
        $user_portals = $this->get_user_portals( $user_id );

        if ( ! in_array( $portal_id, $user_portals ) ) {
            wp_die( 'You do not have access to this portal' );
        }

        // Set as active portal
        set_transient( 'cpm_active_portal_' . $user_id, $portal_id, HOUR_IN_SECONDS );

        // Log the switch
        error_log( sprintf(
            '🔄 CPM: User %d switched to portal %d',
            $user_id,
            $portal_id
        ));

        // Redirect to the portal page
        $redirect_url = get_permalink( $portal_id );
        
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Get all portals a user has access to
     */
    private function get_user_portals( $user_id ) {
        $portals = get_posts([
            'post_type'   => 'portal',
            'numberposts' => -1,
            'fields'      => 'ids',
            'post_status' => 'publish',
            'meta_query'  => [[
                'key'     => '_portal_users',
                'value'   => 'i:' . intval( $user_id ) . ';',
                'compare' => 'LIKE'
            ]]
        ]);

        return $portals;
    }
}

new CPM_Portal_Switcher();