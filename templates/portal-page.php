<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Portal Page (Standalone Astra-Like Header)
 * Description: Uses custom header styled like Astra, dynamic menu logic integrated with plugin settings.
 */

global $post;
$current_user = wp_get_current_user();

// 🔹 Get the portal-specific menu (if any)
$portal_menu_id = get_post_meta( $post->ID, '_portal_menu_id', true );
$has_portal_menu = ! empty( $portal_menu_id ) && wp_get_nav_menu_items( $portal_menu_id );

// 🔹 Load plugin settings (for fallback menu)
$settings = get_option( 'cpm_settings', [] );
$default_menu_id = $settings['default_menu'] ?? '';
$has_default_menu = ! empty( $default_menu_id ) && wp_get_nav_menu_items( $default_menu_id );

// 🔹 Determine which menu to show (portal → default → none)
$menu_to_display = $has_portal_menu ? $portal_menu_id : ( $has_default_menu ? $default_menu_id : false );
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( get_the_title() ); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        body {
            background: #f6f9fc;
            font-family: "Inter", system-ui, sans-serif;
            color: #222;
            margin: 0;
        }

        /* ✅ Header styled like Astra */
        .portal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            border-bottom: 1px solid #e5e5e5;
            padding: 15px 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .portal-header .site-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1d2733;
            text-decoration: none;
        }

        .portal-menu ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .portal-menu li {
            display: inline-block;
            margin: 0 15px;
        }

        .portal-menu a {
            color: #0274be; /* Astra blue */
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .portal-menu a:hover {
            color: #005c99;
        }

        /* ✅ Portal body layout */
        .portal-body {
            max-width: 900px;
            margin: 3rem auto;
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
            text-align: center;
        }

        .portal-body h2 {
            font-size: 1.9rem;
            font-weight: 600;
            color: #222;
            margin-bottom: 0.5rem;
        }

        .portal-body p {
            color: #555;
            margin-bottom: 1.8rem;
        }

        .portal-content {
            text-align: left;
            color: #444;
            line-height: 1.7;
        }

        @media (max-width: 768px) {
            .portal-header {
                flex-direction: column;
                padding: 1rem;
                text-align: center;
            }
            .portal-menu li {
                margin: 0 8px;
            }
        }

        .debug {
            position: fixed;
            top: 0;
            right: 0;
            background: #111;
            color: #0f0;
            font-size: 0.8rem;
            padding: 3px 8px;
            font-family: monospace;
            border-bottom-left-radius: 6px;
            z-index: 9999;
        }

        .back-dashboard-btn {
            background: #0274be;
            color: #fff;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .back-dashboard-btn:hover {
            background: #005c99;
        }

    </style>
</head>
<body <?php body_class(); ?>>

<div class="debug">✅ portal-page.php ACTIVE</div>

<!-- ✅ Astra-Like Custom Header -->
<header class="portal-header">
    <a href="<?php echo esc_url( home_url('/') ); ?>" class="site-title">
        <?php bloginfo('name'); ?>
    </a>

    <nav class="portal-menu">
        <?php
        if ( $menu_to_display ) {
            wp_nav_menu([
                'menu' => $menu_to_display,
                'container' => false,
                'items_wrap' => '<ul>%3$s</ul>',
                'fallback_cb' => false
            ]);
        }
        ?>
    </nav>

    <?php if ( current_user_can('manage_options') ) : ?>
        <a href="<?php echo esc_url( admin_url() ); ?>" class="back-dashboard-btn">⬅ Dashboard</a>
    <?php endif; ?>
</header>

<!-- ✅ Portal Body -->

<main class="portal-body">
    <h2><?php echo esc_html( get_the_title() ); ?></h2>
    <p>Welcome, <strong><?php echo esc_html( $current_user->display_name ); ?></strong></p>

    <div class="portal-content">
        <?php
        if ( have_posts() ) {
            while ( have_posts() ) {
                the_post();
                echo apply_filters( 'the_content', get_the_content() );
            }
        } else {
            echo '<p>No portal content found.</p>';
        }
        ?>
    </div>
</main>

<?php wp_footer(); ?>
</body>
</html>
