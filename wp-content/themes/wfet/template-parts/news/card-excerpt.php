<?php
/**
 * News card excerpt.
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

$excerpt = get_the_excerpt($post_id);

if ($excerpt === '') {
    return;
}
?>

<p class="news-card__excerpt"><?php echo esc_html($excerpt); ?></p>
