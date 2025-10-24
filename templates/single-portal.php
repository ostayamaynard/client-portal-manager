<?php
/**
 * Template for displaying single portal
 * Uses Astra's header with dynamic menu
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

global $post;
$current_user = wp_get_current_user();
?>

<style>
.cpm-portal-content-wrapper {
    max-width: 1200px;
    margin: 3rem auto;
    padding: 0 2rem;
}

.cpm-portal-header {
    text-align: center;
    margin-bottom: 3rem;
    padding: 2.5rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.cpm-portal-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1d2733;
    margin: 0 0 0.5rem 0;
}

.cpm-portal-greeting {
    font-size: 1.1rem;
    color: #555;
    margin: 0;
}

.cpm-portal-greeting strong {
    color: #0274be;
}

.cpm-portal-content {
    background: #fff;
    padding: 2.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    line-height: 1.7;
}

.cpm-portal-thumbnail {
    margin-bottom: 2rem;
    text-align: center;
}

.cpm-portal-thumbnail img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
}

/* Hide any shortcode-generated buttons */
.cpm-portal-content .wp-block-buttons,
.cpm-portal-content .wp-block-button {
    display: none !important;
}

@media (max-width: 768px) {
    .cpm-portal-content-wrapper {
        padding: 0 1rem;
        margin: 2rem auto;
    }
    
    .cpm-portal-title {
        font-size: 2rem;
    }
    
    .cpm-portal-header,
    .cpm-portal-content {
        padding: 1.5rem;
    }
}
</style>

<div class="cpm-portal-content-wrapper">
    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
        
        <header class="cpm-portal-header">
            <h1 class="cpm-portal-title"><?php the_title(); ?></h1>
            <p class="cpm-portal-greeting">
                <?php printf( 
                    __( 'Welcome, %s', 'client-portal-manager' ),
                    '<strong>' . esc_html( $current_user->display_name ) . '</strong>'
                ); ?>
            </p>
        </header>

        <article class="cpm-portal-content">
            <?php
            // Display featured image
            if ( has_post_thumbnail() ) : ?>
                <div class="cpm-portal-thumbnail">
                    <?php the_post_thumbnail( 'large' ); ?>
                </div>
            <?php endif;

            // Get the content and remove button blocks
            $content = get_the_content();
            $content = apply_filters( 'the_content', $content );
            
            // Remove WordPress button blocks
            $content = preg_replace( '/<div class="wp-block-buttons">.*?<\/div>/s', '', $content );
            
            echo $content;
            ?>
        </article>

    <?php endwhile; else : ?>
        
        <div class="cpm-portal-content">
            <p><?php _e( 'Portal content not found.', 'client-portal-manager' ); ?></p>
        </div>

    <?php endif; ?>
</div>

<?php
get_footer();