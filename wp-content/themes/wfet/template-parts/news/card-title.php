<?php
/**
 * News card title.
 *
 * @package SOJ_Core_Modern
 *
 * @var array $args {
 *     @type int  $post_id    Post ID to render.
 *     @type bool $is_preview Whether the card is rendered in the block editor.
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

if ($post_id <= 0) {
    return;
}

$is_preview = !empty($args['is_preview']);
$permalink  = get_permalink($post_id);
$title      = get_the_title($post_id);

if ($title === '') {
    return;
}
?>

<h3 class="news-card__title">
    <?php if (!$is_preview && $permalink) : ?>
        <a class="news-card__title-link" href="<?php echo esc_url($permalink); ?>">
            <?php echo esc_html($title); ?>
        </a>
    <?php else : ?>
        <?php echo esc_html($title); ?>
    <?php endif; ?>
</h3>
