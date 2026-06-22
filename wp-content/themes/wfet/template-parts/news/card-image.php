<?php
/**
 * News card featured image.
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

$is_preview   = !empty($args['is_preview']);
$permalink    = get_permalink($post_id);
$thumbnail_id = (int) get_post_thumbnail_id($post_id);
$image_alt    = $thumbnail_id ? (string) get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true) : '';

if ($image_alt === '') {
    $image_alt = get_the_title($post_id);
}

$image_markup = $thumbnail_id
    ? soj_picture(
        $thumbnail_id,
        [
            0 => [480, 363, true],
        ],
        [
            'img_class'             => 'news-card__image-img',
            'alt'                   => $image_alt,
            'use_width_descriptors' => true,
            'sizes'                 => '(min-width: 1280px) 480px, (min-width: 768px) 33vw, 100vw',
            'loading'               => $is_preview ? 'eager' : 'lazy',
            'decoding'              => 'async',
            'fetchpriority'         => $is_preview ? '' : 'low',
            'defer_browser_load'    => !$is_preview && empty($args['eager_images']),
        ]
    )
    : '';
?>

<div class="news-card__image">
    <?php if (!$is_preview && $permalink) : ?>
        <a class="news-card__image-link" href="<?php echo esc_url($permalink); ?>" tabindex="-1" aria-hidden="true">
    <?php endif; ?>

        <?php if ($image_markup) : ?>
            <?php echo $image_markup; ?>
        <?php else : ?>
            <span class="news-card__image-placeholder" aria-hidden="true"></span>
        <?php endif; ?>

    <?php if (!$is_preview && $permalink) : ?>
        </a>
    <?php endif; ?>
</div>
