<?php

/**
 * Recent insights grid for single insight pages.
 *
 * @package SOJ_Core_Modern
 *
 * @var array $args {
 *     @type int $exclude_post_id Post ID to exclude from results.
 *     @type int $posts_per_page  Number of insights to show. Default 3.
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$exclude_post_id = isset($args['exclude_post_id']) ? (int) $args['exclude_post_id'] : 0;
$posts_per_page  = isset($args['posts_per_page']) ? max(1, (int) $args['posts_per_page']) : 3;

$query_args = [
    'post_type'           => 'insight',
    'post_status'         => 'publish',
    'posts_per_page'      => $posts_per_page,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'ignore_sticky_posts' => true,
];

if ($exclude_post_id > 0) {
    $query_args['post__not_in'] = [$exclude_post_id];
}

$insights_query = new WP_Query($query_args);
?>

<section class="news news--type-all single-insight__recent">
    <div class="container">
        <header class="news__header" data-gsap-animate="slide-up">
            <h2 class="news__title"><?php esc_html_e('Recent Insights', 'soj-core'); ?></h2>
        </header>

        <?php if ($insights_query->have_posts()) : ?>
            <div class="news__grid" data-gsap-animate="stagger">
                <?php
                while ($insights_query->have_posts()) :
                    $insights_query->the_post();
                    get_template_part(
                        'template-parts/news/card',
                        null,
                        [
                            'post_id'       => get_the_ID(),
                            'show_category' => true,
                            'taxonomy'      => 'insights-category',
                        ]
                    );
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php else : ?>
            <p class="news__empty"><?php esc_html_e('No insights available yet.', 'soj-core'); ?></p>
        <?php endif; ?>
    </div>
</section>
