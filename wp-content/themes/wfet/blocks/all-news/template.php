<?php
/**
 * All News Block Template.
 *
 * @param array $block The block settings and attributes.
 * @param string $content The block inner HTML (empty).
 * @param bool $is_preview True during AJAX preview.
 * @param int $post_id The post ID this block is saved to.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $block ACF block settings and attributes (injected by ACF at render). */
$id = 'all-news-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'all-news';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if (!empty($block['align'])) {
    $className .= ' align' . $block['align'];
}

$margin_top    = get_field('margin_top') ?: 'mt-0';
$margin_bottom = get_field('margin_bottom') ?: 'mb-0';

if ($margin_top) {
    $className .= ' ' . $margin_top;
}
if ($margin_bottom) {
    $className .= ' ' . $margin_bottom;
}

if (!empty($is_preview) && isset($block['data']['preview_image_help'])) {
    $preview_image_url = get_template_directory_uri() . '/blocks/all-news/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="All News preview" style="width:100%;height:auto;" />';
    return;
}

$is_block_preview = !empty($is_preview);
$title            = get_field('title');
$content          = get_field('content');

$paged = function_exists('soj_all_news_current_page')
    ? soj_all_news_current_page()
    : max(1, (int) get_query_var('paged'), (int) get_query_var('page'));

$query_args = function_exists('soj_all_news_query_args')
    ? soj_all_news_query_args($paged)
    : [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => max(1, (int) get_option('posts_per_page', 10)),
        'paged'               => $paged,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
    ];

$news_query = new WP_Query($query_args);

$context_post_id = !empty($post_id) ? (int) $post_id : (int) get_the_ID();
$show_category   = get_post_type($context_post_id) !== 'service';

if ($is_block_preview) {
    $className .= ' all-news--editor-preview';
}

$filter_categories = function_exists('soj_get_news_filter_categories')
    ? soj_get_news_filter_categories()
    : [];
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($className); ?>">
    <div class="container">
        <?php if ($filter_categories !== []) : ?>
            <div class="all-news__filters" data-gsap-animate="slide-up">
                <?php
                get_template_part(
                    'template-parts/news-filters',
                    null,
                    [
                        'categories' => $filter_categories,
                        'ajax'       => !$is_block_preview,
                    ]
                );
                ?>
            </div>
        <?php endif; ?>

        <div
            class="all-news__results"
            data-all-news-results
            data-context-post-id="<?php echo esc_attr((string) $context_post_id); ?>"
            data-show-category="<?php echo $show_category ? '1' : '0'; ?>"
        >
            <?php
            if (function_exists('soj_all_news_render_results_html')) {
                echo soj_all_news_render_results_html(
                    $news_query,
                    $paged,
                    $context_post_id,
                    $show_category,
                    $is_block_preview
                );
            }
            ?>
        </div>
    </div>
</section>
