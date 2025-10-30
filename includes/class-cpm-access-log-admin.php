<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CPM_Access_Log_Admin {

    public function __construct() {
        add_filter( 'manage_cpm_access_log_posts_columns', [ $this, 'set_custom_columns' ] );
        add_action( 'manage_cpm_access_log_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
        add_filter( 'manage_edit-cpm_access_log_sortable_columns', [ $this, 'make_columns_sortable' ] );
        add_action( 'pre_get_posts', [ $this, 'sort_logs_by_meta' ] );
    }

    /** Define admin columns */
    public function set_custom_columns( $columns ) {
        return [
            'cb'            => '<input type="checkbox" />',
            'title'         => __( 'Log Entry', 'cpm' ),
            'user'          => __( 'User', 'cpm' ),
            'portal'        => __( 'Portal', 'cpm' ),
            'status'        => __( 'Status', 'cpm' ),
            'timestamp'     => __( 'Date & Time', 'cpm' ),
        ];
    }

    /** Fill column content */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'user':
                $user_name = get_post_meta( $post_id, '_user_name', true );
                $user_id   = get_post_meta( $post_id, '_user_id', true );
                echo $user_id ? '<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '">' . esc_html( $user_name ) . '</a>' : esc_html( $user_name ?: 'Guest' );
                break;

            case 'portal':
                $portal_id = get_post_meta( $post_id, '_portal_id', true );
                $portal_title = get_post_meta( $post_id, '_portal_title', true );
                echo $portal_id ? '<a href="' . esc_url( get_edit_post_link( $portal_id ) ) . '">' . esc_html( $portal_title ) . '</a>' : '—';
                break;

            case 'status':
                $status = get_post_meta( $post_id, '_status', true );
                $color = $status === 'granted' ? '#00a32a' : '#d63638';
                echo '<strong style="color:' . esc_attr( $color ) . ';">' . strtoupper( esc_html( $status ) ) . '</strong>';
                break;

            case 'timestamp':
                $time = get_post_meta( $post_id, '_timestamp', true );
                echo $time ? esc_html( date_i18n( 'M d, Y g:i A', strtotime( $time ) ) ) : '—';
                break;
        }
    }

    /** Make columns sortable */
    public function make_columns_sortable( $columns ) {
        $columns['status']    = 'status';
        $columns['timestamp'] = 'timestamp';
        return $columns;
    }

    /** Handle sorting logic */
    public function sort_logs_by_meta( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get('post_type') !== 'cpm_access_log' ) return;

        $orderby = $query->get( 'orderby' );
        if ( $orderby === 'timestamp' ) {
            $query->set( 'meta_key', '_timestamp' );
            $query->set( 'orderby', 'meta_value' );
        }
        elseif ( $orderby === 'status' ) {
            $query->set( 'meta_key', '_status' );
            $query->set( 'orderby', 'meta_value' );
        }
    }
}
