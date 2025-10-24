<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Login_Redirect {

    public function __construct() {
        add_filter( 'login_redirect', [ $this, 'redirect_user_to_portal' ], 10, 3 );
        add_action( 'init', [ $this, 'handle_portal_selection' ] );
        add_shortcode( 'portal_selector', [ $this, 'render_portal_selector' ] );
    }

    /**
     * Redirect user after login based on assigned portals.
     */
    public function redirect_user_to_portal( $redirect_to, $request, $user ) {
        error_log('🟡 CPM_Login_Redirect triggered');

        if ( ! $user || is_wp_error( $user ) ) {
            error_log('🔴 No user or error detected');
            return $redirect_to;
        }

        $user_id = $user->ID;
        error_log('👤 Redirecting user ID: ' . $user_id);

        $portals = get_posts([
            'post_type'   => 'portal',
            'numberposts' => -1,
            'meta_query'  => [
                'relation' => 'OR',
                [
                    'key'     => '_portal_users',
                    'value'   => ':"' . $user_id . '";',
                    'compare' => 'LIKE'
                ],
                [
                    'key'     => '_portal_users',
                    'value'   => 'i:' . $user_id . ';',
                    'compare' => 'LIKE'
                ]
            ]
        ]);

        error_log('🧩 Found ' . count($portals) . ' portals for user');

        // No portals -> go to dashboard
        if ( empty( $portals ) ) {
            error_log('⚠️ No portals found. Returning to dashboard.');
            return admin_url();
        }

        // ✅ FIXED: If user has only one portal, go directly there
        if ( count( $portals ) === 1 ) {
            $portal_url = get_permalink( $portals[0]->ID );
            error_log("✅ Single portal found. Redirecting to {$portal_url}");
            return $portal_url;
        }

        // ✅ Multiple portals → show selection page
        error_log('🔀 Multiple portals. Redirecting to selection page.');

        if ( ! session_id() ) session_start();
        $_SESSION['cpm_user_portals'] = wp_list_pluck( $portals, 'ID' );

        $selection_page = $this->get_or_create_selection_page();
        return get_permalink( $selection_page );
    }

    /**
     * Get or create the portal selection page.
     */
    private function get_or_create_selection_page() {
        $page = get_page_by_path( 'portal-selection' );

        if ( ! $page ) {
            $page_id = wp_insert_post([
                'post_title'   => 'Select Your Portal',
                'post_name'    => 'portal-selection',
                'post_content' => '[portal_selector]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'meta_input'   => [
                    '_cpm_system_page' => 'yes' // Mark system page
                ]
            ]);

            if ( $page_id ) {
                error_log("✅ Created portal selection page ID: {$page_id}");
                return $page_id;
            }
        }

        return $page->ID;
    }

    /**
     * Handle portal selection form submission.
     */
    public function handle_portal_selection() {
        if ( ! isset( $_POST['cpm_select_portal'], $_POST['portal_id'] ) ) return;

        if ( ! session_id() ) session_start();

        $portal_id = intval( $_POST['portal_id'] );
        $user_portals = $_SESSION['cpm_user_portals'] ?? [];

        // Verify user access
        if ( ! in_array( $portal_id, $user_portals, true ) ) {
            wp_die( 'Unauthorized portal selection.' );
        }

        $_SESSION['cpm_active_portal'] = $portal_id;

        wp_safe_redirect( get_permalink( $portal_id ) );
        exit;
    }

    /**
     * Render portal selection UI (shortcode).
     */
    public function render_portal_selector( $atts ) {
        if ( ! session_id() ) session_start();

        $user_portals = $_SESSION['cpm_user_portals'] ?? [];
        if ( empty( $user_portals ) ) return '<p>No portals available.</p>';

        ob_start();
        ?>
        <div class="cpm-portal-selector" style="max-width: 600px; margin: 2rem auto; padding: 2rem; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h2 style="text-align: center; margin-bottom: 1.5rem;">Select Your Portal</h2>
            <p style="text-align: center; color: #666; margin-bottom: 2rem;">You have access to multiple portals. Please choose one to continue:</p>
            <div class="portal-list" style="display: grid; gap: 1rem;">
                <?php foreach ( $user_portals as $portal_id ) :
                    $portal = get_post( $portal_id );
                    if ( ! $portal ) continue;
                ?>
                    <form method="post" style="margin: 0;">
                        <button type="submit" name="cpm_select_portal" style="
                            width: 100%;
                            padding: 1.5rem;
                            background: #0274be;
                            color: #fff;
                            border: none;
                            border-radius: 6px;
                            font-size: 1.1rem;
                            font-weight: 600;
                            cursor: pointer;
                            transition: background 0.2s ease;
                            text-align: left;
                        " onmouseover="this.style.background='#005c99'" onmouseout="this.style.background='#0274be'">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span><?php echo esc_html( $portal->post_title ); ?></span>
                                <span style="opacity: 0.8;">→</span>
                            </div>
                            <?php if ( $portal->post_excerpt ) : ?>
                                <div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.9;">
                                    <?php echo esc_html( $portal->post_excerpt ); ?>
                                </div>
                            <?php endif; ?>
                        </button>
                        <input type="hidden" name="portal_id" value="<?php echo esc_attr( $portal_id ); ?>">
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
