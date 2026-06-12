<?php
/**
 * Standard Content Block Template.
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
$id = 'standard-content-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'standard-content';
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
    $preview_image_url = get_template_directory_uri() . '/blocks/standard-content/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Standard Content preview" style="width:100%;height:auto;" />';
    return;
}

$label             = get_field('label');
$label_as_h1       = (bool) get_field('label_as_h1');
$content           = get_field('content');
$image             = get_field('image');
$mobile_image      = get_field('mobile_image');
$buttons           = get_field('buttons');
$background_colour = get_field('background_colour') ?: 'midnight';
$content_width     = get_field('content_width') ?: '50';

/**
 * Render responsive image markup for a standard content image field.
 *
 * @param array|int|null $image      ACF image field value.
 * @param string         $img_class  CSS class for the img element.
 * @param array          $sizes      soj_picture size map.
 * @param string         $sizes_attr sizes attribute value.
 * @return string
 */
$render_image = static function ($image, $img_class, $sizes, $sizes_attr) {
    $image_id  = 0;
    $image_alt = '';

    if (is_array($image) && !empty($image['ID'])) {
        $image_id  = (int) $image['ID'];
        $image_alt = !empty($image['alt']) ? (string) $image['alt'] : '';
    } elseif (is_numeric($image)) {
        $image_id = (int) $image;
    }

    if ($image_id <= 0) {
        return '';
    }

    if ($image_alt === '') {
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '';
    }

    $mime_type = get_post_mime_type($image_id);
    $is_svg    = $mime_type === 'image/svg+xml';

    if ($is_svg) {
        $image_url = wp_get_attachment_url($image_id);
        if ($image_url) {
            return sprintf(
                '<img class="%s" src="%s" alt="%s" loading="lazy" decoding="async" />',
                esc_attr($img_class),
                esc_url($image_url),
                esc_attr($image_alt)
            );
        }

        return '';
    }

    return soj_picture($image_id, $sizes, [
        'img_class'             => $img_class,
        'alt'                   => $image_alt,
        'use_width_descriptors' => true,
        'sizes'                 => $sizes_attr,
        'loading'               => 'lazy',
        'decoding'              => 'async',
        'fetchpriority'         => 'low',
    ]);
};

$allowed_colours = ['sand', 'midnight'];
if (!in_array($background_colour, $allowed_colours, true)) {
    $background_colour = 'midnight';
}

$allowed_widths = ['50', '70', '100'];
if (!in_array((string) $content_width, $allowed_widths, true)) {
    $content_width = '50';
}

$className .= ' standard-content--bg-' . sanitize_html_class($background_colour);
$className .= ' standard-content--width-' . sanitize_html_class((string) $content_width);

$desktop_image_markup = $render_image(
    $image,
    'standard-content__image-img',
    [0 => [1200, 0, false]],
    '(min-width: 1200px) 1200px, 100vw'
);

$mobile_image_markup = $render_image(
    $mobile_image,
    'standard-content__image-img',
    [0 => [768, 0, false]],
    '100vw'
);

if (!$label && !$content && !$desktop_image_markup && !$mobile_image_markup && empty($buttons)) {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr($className) . '"><p>' . esc_html__('Add content to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <div class="standard-content__inner">
        <div class="standard-content__caption">
            <?php if ($label) : ?>
                <?php if ($label_as_h1) : ?>
                    <h1 class="label"><?php echo esc_html($label); ?></h1>
                <?php else : ?>
                    <p class="label"><?php echo esc_html($label); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($content) : ?>
                <div class="standard-content__content">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>


            <?php if (!empty($buttons) && is_array($buttons)) : ?>
                <div class="standard-content__buttons">
                    <?php foreach ($buttons as $row) :
                        $button = isset($row['button']) ? $row['button'] : null;
                        if (!is_array($button) || empty($button['url']) || empty($button['title'])) {
                            continue;
                        }
                        ?>
                        <?php soj_the_button($button, ['class' => 'standard-content__button']); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($desktop_image_markup || $mobile_image_markup) : ?>
        <div class="standard-content__image-container">
            <div class="standard-content__image">
                <?php if ($desktop_image_markup) : ?>
                    <div class="standard-content__image-desktop">
                        <?php echo $desktop_image_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>

                <?php if ($mobile_image_markup) : ?>
                    <div class="standard-content__image-mobile">
                        <?php echo $mobile_image_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>  
    </div>

    

</section>
