<?php

/**
 * All News block results (grid, empty state, pagination).
 *
 * @package SOJ_Core_Modern
 *
 * @var array $args {
 *     @type WP_Query $query            News query.
 *     @type int       $paged            Current page.
 *     @type int       $context_post_id  Host page ID for pagination URLs.
 *     @type bool      $show_category    Whether cards show category tags.
 *     @type bool      $is_preview       Block editor preview.
 *     @type bool      $eager_images     Render images immediately (for AJAX responses).
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$query           = isset($args['query']) && $args['query'] instanceof WP_Query ? $args['query'] : null;
$paged           = isset($args['paged']) ? max(1, (int) $args['paged']) : 1;
$context_post_id = isset($args['context_post_id']) ? (int) $args['context_post_id'] : 0;
$show_category   = !empty($args['show_category']);
$is_preview      = !empty($args['is_preview']);
$eager_images    = !empty($args['eager_images']);

if (!$query instanceof WP_Query) {
    return;
}

$pagination_html = '';

if (!$is_preview) {
    $pagination_html = function_exists('soj_all_news_pagination_html')
        ? soj_all_news_pagination_html($query, $paged, $context_post_id)
        : '';
}
?>

<?php if ($query->have_posts()) : ?>
    <div class="all-news__grid" data-gsap-animate="stagger">
        <?php
        while ($query->have_posts()) :
            $query->the_post();
            get_template_part(
                'template-parts/news/card',
                null,
                [
                    'post_id'       => get_the_ID(),
                    'is_preview'    => $is_preview,
                    'show_category' => $show_category,
                    'eager_images'  => $eager_images,
                ]
            );
        endwhile;
        wp_reset_postdata();
        ?>
    </div>

    <?php if ($pagination_html) : ?>
        <nav class="all-news__pagination" aria-label="<?php esc_attr_e('News pages', 'soj-core'); ?>">
            <p class="all-news__pagination-label"><?php esc_html_e('Pages', 'soj-core'); ?></p>
            <div class="all-news__pagination-links">
                <?php echo $pagination_html; ?>
            </div>
        </nav>
    <?php endif; ?>
<?php else : ?>
    <p class="all-news__empty"><?php esc_html_e('No posts available yet.', 'soj-core'); ?></p>
<?php endif; ?>
