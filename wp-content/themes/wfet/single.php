<?php
/**
 * The template for displaying all single posts
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
    endwhile; // End of the loop.
    ?>

</main><!-- #primary -->

<?php get_footer();
