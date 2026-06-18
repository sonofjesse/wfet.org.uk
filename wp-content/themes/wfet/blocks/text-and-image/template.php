<?php
/**
 * Text and Image Block Template.
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
$id = 'text-and-image-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'text-and-image';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if (!empty($block['align'])) {
    $className .= ' align' . $block['align'];
}

$margin_top    = get_field('margin_top') ?: 'mt-0';
$margin_bottom = get_field('margin_bottom') ?: 'mb-0';

// Convert margin top and bottom to padding top and bottom where it has mt-x change the class to pt-x
// and where it has mb-x change the class to pb-x
if (strpos($margin_top, 'mt-') !== false) {
    $margin_top = str_replace('mt-', 'pt-', $margin_top);
}
if (strpos($margin_bottom, 'mb-') !== false) {
    $margin_bottom = str_replace('mb-', 'pb-', $margin_bottom);
}

$use_as_hero = (bool) get_field('use_as_hero');

if ($use_as_hero) {
    $className .= ' text-and-image--hero alignfull';
} elseif ($margin_top) {
    $className .= ' ' . $margin_top;
}

if ($margin_bottom) {
    $className .= ' ' . $margin_bottom;
}

$background_colour = get_field('background_colour') ?: 'midnight';
$allowed_colours   = ['midnight', 'ocean', 'glass', 'sand', 'moss-dark', 'sky-dark', 'rose-dark'];

if (!in_array($background_colour, $allowed_colours, true)) {
    $background_colour = 'midnight';
}

$className .= ' text-and-image--bg-' . sanitize_html_class($background_colour);

$image_position = get_field('image_position') ?: 'left';
$allowed_positions = ['left', 'right'];

if (!in_array($image_position, $allowed_positions, true)) {
    $image_position = 'left';
}

$className .= ' text-and-image--image-' . sanitize_html_class($image_position);

if (!empty($is_preview) && isset($block['data']['preview_image_help'])) {
    $preview_image_url = get_template_directory_uri() . '/blocks/text-and-image/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Text and Image preview" style="width:100%;height:auto;" />';
    return;
}

$label   = get_field('label');
$title   = get_field('title');
$content = get_field('content');
$buttons = get_field('buttons');
$image   = get_field('image');

$image_id  = 0;
$image_alt = '';

if (is_array($image) && !empty($image['ID'])) {
    $image_id  = (int) $image['ID'];
    $image_alt = !empty($image['alt']) ? (string) $image['alt'] : '';
} elseif (is_numeric($image)) {
    $image_id = (int) $image;
}

if (!$label && !$title && !$content && empty($buttons) && !$image_id) {
    return;
}

$image_markup = '';
if ($image_id) {
    if ($image_alt === '') {
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '';
    }

    $mime_type = get_post_mime_type($image_id);
    $is_svg    = $mime_type === 'image/svg+xml';

    if ($is_svg) {
        $image_url = wp_get_attachment_url($image_id);
        if ($image_url) {
            $image_markup = sprintf(
                '<img class="text-and-image__image-img" src="%s" alt="%s" loading="lazy" decoding="async" />',
                esc_url($image_url),
                esc_attr($image_alt)
            );
        }
    } else {
        $img_attributes = [];
        $metadata       = wp_get_attachment_metadata($image_id);
        $orig_width     = (int) ($metadata['width'] ?? 0);
        $orig_height    = (int) ($metadata['height'] ?? 0);
        $max_display_w  = 693;

        if ($orig_width > 0 && $orig_height > 0) {
            if ($orig_width > $max_display_w) {
                $img_attributes['width']  = $max_display_w;
                $img_attributes['height'] = (int) round($orig_height * ($max_display_w / $orig_width));
            } else {
                $img_attributes['width']  = $orig_width;
                $img_attributes['height'] = $orig_height;
            }
        }

        $image_markup = soj_picture($image_id, [
            0 => [693, 0, false],
        ], [
            'img_class'             => 'text-and-image__image-img',
            'alt'                   => $image_alt,
            'use_width_descriptors' => true,
            'sizes'                 => '693px',
            'loading'               => 'lazy',
            'decoding'              => 'async',
            'fetchpriority'         => 'low',
            'img_attributes'        => $img_attributes,
        ]);
    }
}
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">

    <div class="text-and-image__inner" data-gsap-animate="stagger">
        <?php if ($image_markup) : ?>
            <div class="text-and-image__media">
                <?php echo $image_markup; ?>
            </div>
        <?php endif; ?>

        <div class="text-and-image__body">
            <?php if ($label) : ?>
                <p class="label"><?php echo esc_html($label); ?></p>
            <?php endif; ?>

            <?php if ($title) : ?>
                <h2 class="text-and-image__title"><?php echo wp_kses_post($title); ?></h2>
            <?php endif; ?>

            <?php if ($content) : ?>
                <div class="text-and-image__content">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($buttons) && is_array($buttons)) : ?>
                <div class="text-and-image__buttons">
                    <?php foreach ($buttons as $row) :
                        $button = isset($row['button']) ? $row['button'] : null;
                        if (!is_array($button) || empty($button['url']) || empty($button['title'])) {
                            continue;
                        }
                        ?>
                        <?php soj_the_button($button, ['class' => 'text-and-image__button']); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</section>
