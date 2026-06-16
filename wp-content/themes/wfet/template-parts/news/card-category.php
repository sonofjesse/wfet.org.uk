<?php
/**
 * News card category tag.
 *
 * @package SOJ_Core_Modern
 *
 * @var array $args {
 *     @type bool $show_category Whether to output the category tag. Default true.
 *     @type bool $eager_images  Whether to render images immediately.
 *     @type string $taxonomy  Taxonomy slug for category tags. Default category.
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

if ($post_id <= 0) {
    return;
}

$taxonomy = isset($args['taxonomy']) ? (string) $args['taxonomy'] : 'category';
$categories = get_the_terms($post_id, $taxonomy);

if (is_wp_error($categories) || empty($categories)) {
    return;
}

$is_preview = !empty($args['is_preview']);
?>

<div class="news-card__categories">
    <?php foreach ($categories as $category) :
        if (!$category instanceof WP_Term) {
            continue;
        }

        $colour        = function_exists('soj_get_news_category_colour') && $taxonomy === 'category'
            ? soj_get_news_category_colour($category)
            : (function_exists('soj_get_insights_category_colour') && $taxonomy === 'insights-category'
                ? soj_get_insights_category_colour($category)
                : 'moss-dark');
        $category_link = get_term_link($category, $taxonomy);
        $category_class = 'news-card__category news-card__category--' . sanitize_html_class($colour);

        if (!$is_preview && $category_link && !is_wp_error($category_link)) :
            ?>
            <a
                class="<?php echo esc_attr($category_class); ?>"
                href="<?php echo esc_url($category_link); ?>"
            >
                <?php echo esc_html($category->name); ?>
            </a>
        <?php else : ?>
            <span class="<?php echo esc_attr($category_class); ?>">
                <?php echo esc_html($category->name); ?>
            </span>
        <?php endif;
    endforeach; ?>
</div>
