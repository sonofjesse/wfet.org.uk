<?php

/**
 * The template for displaying all single insights
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

get_header();
?>

<main id="primary" class="site-main">

    <?php
    while (have_posts()) :
        the_post();

        if (post_password_required()) {
            get_template_part('template-parts/content', 'password');
            break;
        }

        the_content();

        get_template_part('template-parts/insights/team-profile');

        get_template_part(
            'template-parts/insights/recent',
            null,
            [
                'exclude_post_id' => get_the_ID(),
                'posts_per_page'  => 3,
            ]
        );
    endwhile; // End of the loop.
    ?>

</main><!-- #primary -->

<?php get_footer();
