<?php
/**
 * News card category tag.
 *
 * @package SOJ_Core_Modern
 *
 * @var array $args {
 *     @type int $post_id Post ID to render.
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

if ($post_id <= 0) {
    return;
}

$categories = get_the_category($post_id);

if (empty($categories)) {
    return;
}

$is_preview = !empty($args['is_preview']);
?>

<div class="news-card__categories">
    <?php foreach ($categories as $category) :
        if (!$category instanceof WP_Term) {
            continue;
        }

        $colour        = function_exists('soj_get_news_category_colour')
            ? soj_get_news_category_colour($category)
            : 'moss-dark';
        $category_link = get_category_link($category->term_id);
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
