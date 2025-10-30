<?php
/**
 * Client Portal Manager - Portal CPT
 * Registers the portal custom post type and handles user assignments
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Portal_CPT {

    public function __construct() {
        add_action( 'init', [ $this, 'register_portal_cpt' ] );
        add_filter( 'template_include', [ $this, 'load_portal_template' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_user_meta_box' ] );
        add_action( 'save_post_portal', [ $this, 'save_portal_users' ] );
    }

    /**
     * Register portal custom post type
     */
    public function register_portal_cpt() {
        $labels = [
            'name'               => __( 'Portals', 'client-portal-manager' ),
            'singular_name'      => __( 'Portal', 'client-portal-manager' ),
            'add_new'            => __( 'Add New', 'client-portal-manager' ),
            'add_new_item'       => __( 'Add New Portal', 'client-portal-manager' ),
            'edit_item'          => __( 'Edit Portal', 'client-portal-manager' ),
            'new_item'           => __( 'New Portal', 'client-portal-manager' ),
            'view_item'          => __( 'View Portal', 'client-portal-manager' ),
            'search_items'       => __( 'Search Portals', 'client-portal-manager' ),
            'not_found'          => __( 'No portals found', 'client-portal-manager' ),
            'not_found_in_trash' => __( 'No portals found in trash', 'client-portal-manager' ),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,  // Must be true for URLs to work
            'publicly_queryable'  => true,  // Must be true for single views
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => true,
            'exclude_from_search' => true,
            'has_archive'         => false,
            'hierarchical'        => false,
            'rewrite' => [ 'slug' => 'portal', 'with_front' => false ],
            'supports'            => [ 'title', 'editor', 'excerpt', 'thumbnail' ],
            'capability_type'     => 'post',
            'menu_icon'           => 'dashicons-lock',
            'menu_position'       => 20,
        ];

        register_post_type( 'portal', $args );
    }

    /**
     * Load custom template for portal
     */
    public function load_portal_template( $template ) {
        if ( is_singular( 'portal' ) ) {
            $custom_template = CPM_PATH . 'templates/single-portal.php';
            
            if ( file_exists( $custom_template ) ) {
                return $custom_template;
            }
        }
        
        return $template;
    }

    /**
     * Add user assignment meta box
     */
    public function add_user_meta_box() {
        add_meta_box(
            'cpm_portal_users',
            __( 'Portal Access', 'client-portal-manager' ),
            [ $this, 'render_user_meta_box' ],
            'portal',
            'side',
            'high'
        );
    }

    /**
     * Render user assignment meta box
     */
    public function render_user_meta_box( $post ) {
        wp_nonce_field( 'cpm_save_portal_users', 'cpm_portal_users_nonce' );
        
        $assigned_users = get_post_meta( $post->ID, '_portal_users', true );
        if ( ! is_array( $assigned_users ) ) {
            $assigned_users = maybe_unserialize( $assigned_users );
        }
        $assigned_users = array_map( 'intval', (array) $assigned_users );

        $users = get_users([
            'orderby' => 'display_name',
            'order'   => 'ASC'
        ]);

        ?>
        <p>
            <strong><?php _e( 'Select users who can access this portal:', 'client-portal-manager' ); ?></strong>
        </p>
        
        <div style="max-height:300px;overflow-y:auto;border:1px solid #ddd;padding:10px;background:#fafafa;">
            <?php if ( empty( $users ) ) : ?>
                <p style="color:#999;margin:0;">
                    <?php _e( 'No users found.', 'client-portal-manager' ); ?>
                </p>
            <?php else : ?>
                <?php foreach ( $users as $user ) : ?>
                    <label style="display:block;margin-bottom:6px;cursor:pointer;">
                        <input 
                            type="checkbox" 
                            name="cpm_portal_users[]" 
                            value="<?php echo esc_attr( $user->ID ); ?>"
                            <?php checked( in_array( $user->ID, $assigned_users, true ) ); ?>
                        > 
                        <strong><?php echo esc_html( $user->display_name ); ?></strong>
                        <span style="color:#666;font-size:12px;">
                            (<?php echo esc_html( $user->user_login ); ?>)
                        </span>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <p class="description" style="margin-top:10px;">
            <?php _e( 'If no users are selected, no one can access this portal.', 'client-portal-manager' ); ?>
        </p>
        <?php
    }

    /**
     * Save portal user assignments
     */
    public function save_portal_users( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['cpm_portal_users_nonce'] ) ||
             ! wp_verify_nonce( $_POST['cpm_portal_users_nonce'], 'cpm_save_portal_users' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save user assignments
        if ( isset( $_POST['cpm_portal_users'] ) && is_array( $_POST['cpm_portal_users'] ) ) {
            $user_ids = array_map( 'intval', $_POST['cpm_portal_users'] );
            update_post_meta( $post_id, '_portal_users', $user_ids );
            
            error_log( sprintf(
                'Saved portal %d user assignments: [%s]',
                $post_id,
                implode( ',', $user_ids )
            ));
        } else {
            delete_post_meta( $post_id, '_portal_users' );
            error_log( " Cleared user assignments for portal {$post_id}" );
        }
    }
}