<?php

/**
 * Category archive.
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

get_header();

$term = get_queried_object();
$term_name = ($term instanceof WP_Term) ? $term->name : '';
$term_description = ($term instanceof WP_Term && $term->taxonomy === 'category')
    ? trim((string) $term->description)
    : '';

$class_name = 'category-archive';
$allowed_colours = ['rose-dark', 'moss-dark', 'sky-dark'];

if ($term instanceof WP_Term && function_exists('soj_get_news_category_colour')) {
    $background_colour = soj_get_news_category_colour($term);

    if (in_array($background_colour, $allowed_colours, true)) {
        $class_name .= ' category-archive--bg-' . sanitize_html_class($background_colour);
    }
}

global $wp_query;

$paged = max(1, (int) get_query_var('paged'));
$total_pages = (int) $wp_query->max_num_pages;
$pagination_html = '';

if ($total_pages >= 2) {
    $links = paginate_links(
        [
            'total'     => $total_pages,
            'current'   => $paged,
            'prev_next' => false,
            'mid_size'  => 2,
            'type'      => 'list',
        ]
    );

    $pagination_html = is_string($links) ? $links : '';
}
?>

<main id="primary" class="site-main">
    <section class="<?php echo esc_attr($class_name); ?>">
        <div class="container">
            <?php if ($term_name || $term_description) : ?>
                <header class="category-archive__header">
                    <?php if ($term_name) : ?>
                        <h1 class="category-archive__title"><?php echo esc_html(sprintf(__('Our work in action for %s', 'soj-core'), $term_name)); ?></h1>
                    <?php endif; ?>

                    <?php if ($term_description !== '') : ?>
                        <div class="category-archive__content">
                            <?php echo wp_kses_post(wpautop($term_description)); ?>
                        </div>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <?php
            $filter_categories = function_exists('soj_get_news_filter_categories')
                ? soj_get_news_filter_categories()
                : [];
            $current_category_id = ($term instanceof WP_Term) ? (int) $term->term_id : 0;

            if ($filter_categories !== []) :
                ?>
                <div class="category-archive__filters">
                    <?php
                    get_template_part(
                        'template-parts/news-filters',
                        null,
                        [
                            'categories'          => $filter_categories,
                            'current_category_id' => $current_category_id,
                        ]
                    );
                    ?>
                </div>
            <?php endif; ?>

            <?php if (have_posts()) : ?>
                <div class="category-archive__grid">
                    <?php
                    while (have_posts()) :
                        the_post();
                        get_template_part(
                            'template-parts/news/card',
                            null,
                            [
                                'post_id'       => get_the_ID(),
                                'show_category' => false,
                            ]
                        );
                    endwhile;
                    ?>
                </div>

                <?php if ($pagination_html) : ?>
                    <nav class="category-archive__pagination" aria-label="<?php esc_attr_e('Category pages', 'soj-core'); ?>">
                        <p class="category-archive__pagination-label"><?php esc_html_e('Pages', 'soj-core'); ?></p>
                        <div class="category-archive__pagination-links">
                            <?php echo $pagination_html; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <p class="category-archive__empty"><?php esc_html_e('No posts available yet.', 'soj-core'); ?></p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
get_footer();
