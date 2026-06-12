<?php
/**
 * Image Carousel Block Template.
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
$id = 'image-carousel-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'image-carousel alignfull';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if (!empty($block['align'])) {
    $className .= ' align' . $block['align'];
}

$margin_top    = get_field('margin_top') ?: 'mt-0';
$margin_bottom = get_field('margin_bottom');

if ($margin_top && $margin_top !== 'mt-0') {
    $className .= ' ' . $margin_top;
}

// Universal block field defaults to mb-5; keep carousel flush unless smaller spacing is chosen.
if ($margin_bottom && !in_array($margin_bottom, ['mb-0', 'mb-5'], true)) {
    $className .= ' ' . $margin_bottom;
}

if (!empty($is_preview) && isset($block['data']['preview_image_help'])) {
    $preview_image_url = get_template_directory_uri() . '/blocks/image-carousel/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Image Carousel preview" style="width:100%;height:auto;" />';
    return;
}

$background_colour = get_field('background_colour') ?: 'sand';
$allowed_colours   = ['sand', 'midnight'];

if (!in_array($background_colour, $allowed_colours, true)) {
    $background_colour = 'sand';
}

$className .= ' image-carousel--bg-' . sanitize_html_class($background_colour);

$gallery = get_field('gallery');
if (empty($gallery) || !is_array($gallery)) {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr($className) . '"><p>' . esc_html__('Add images to the gallery field to preview this carousel.', 'soj-core') . '</p></div>';
    }
    return;
}

$is_block_preview = !empty($is_preview);
if ($is_block_preview) {
    $className .= ' image-carousel--editor-preview';
}
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <div class="image-carousel__inner">
        <div class="image-carousel__track" aria-label="<?php esc_attr_e('Image carousel', 'soj-core'); ?>">
            <?php foreach ($gallery as $image) :
                if (!is_array($image) || empty($image['ID'])) {
                    continue;
                }

                $image_id  = (int) $image['ID'];
                $image_alt = !empty($image['alt']) ? (string) $image['alt'] : '';

                if ($image_alt === '') {
                    $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '';
                }

                $image_markup = soj_picture($image_id, [
                    0 => [424, 636, true],
                ], [
                    'img_class'             => 'image-carousel__image',
                    'alt'                   => $image_alt,
                    'use_width_descriptors' => true,
                    'sizes'                 => '(min-width: 992px) 350px, (min-width: 768px) 280px, 240px',
                    'loading'               => $is_block_preview ? 'eager' : 'lazy',
                    'decoding'              => 'async',
                    'fetchpriority'         => $is_block_preview ? '' : 'low',
                ]);

                if ($image_markup === '') {
                    continue;
                }
                ?>
                <div class="image-carousel__slide">
                    <div class="image-carousel__frame">
                        <?php echo $image_markup; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
