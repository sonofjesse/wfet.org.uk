<?php
/**
 * Service Hero Block Template.
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
$id = 'service-hero-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'service-hero';
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
    $preview_image_url = get_template_directory_uri() . '/blocks/service-hero/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Service Hero preview" style="width:100%;height:auto;" />';
    return;
}

$title           = get_field('title');
$content         = get_field('content');
$primary_image   = get_field('primary_image');
$secondary_image = get_field('secondary_image');

$post_title = get_the_title($post_id);

$background_colour = get_field('background_colour', $post_id);
if (!$background_colour) {
    $background_colour = get_field('service_colour', $post_id);
}

$allowed_colours = ['rose-dark', 'moss-dark', 'sky-dark'];
if (!in_array($background_colour, $allowed_colours, true)) {
    $background_colour = 'rose-dark';
}

$className .= ' service-hero--bg-' . sanitize_html_class($background_colour);

$service_icon = get_field('service_icon', $post_id);

/**
 * Render responsive image markup for a service hero image field.
 *
 * @param array|int|null $image       ACF image field value.
 * @param string         $img_class   CSS class for the img element.
 * @param array          $sizes       soj_picture size map.
 * @param string         $sizes_attr  sizes attribute value.
 * @param bool           $is_preview  Whether the block is in editor preview.
 * @param bool           $is_lcp      Whether this image is the page LCP candidate.
 * @return string
 */
$render_image = static function ($image, $img_class, $sizes, $sizes_attr, $is_preview, $is_lcp = false) {
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

    $is_block_preview = !empty($is_preview);
    $loading          = ($is_block_preview || $is_lcp) ? 'eager' : 'lazy';

    return soj_picture($image_id, $sizes, [
        'img_class'             => $img_class,
        'alt'                   => $image_alt,
        'use_width_descriptors' => true,
        'sizes'                 => $sizes_attr,
        'loading'               => $loading,
        'decoding'              => 'async',
        'fetchpriority'         => $is_block_preview ? '' : ($is_lcp ? 'high' : 'low'),
        'preload'               => !$is_block_preview && $is_lcp,
        'defer_browser_load'    => !$is_block_preview && !$is_lcp,
    ]);
};

$primary_image_markup = $render_image(
    $primary_image,
    'service-hero__primary-img',
    [0 => [675, 786, true]],
    '(min-width: 992px) 675px, (min-width: 768px) 50vw, 100vw',
    !empty($is_preview),
    true
);

$secondary_image_markup = $render_image(
    $secondary_image,
    'service-hero__secondary-img',
    [0 => [440, 288, true]],
    '(min-width: 992px) 440px, (min-width: 768px) 40vw, 100vw',
    !empty($is_preview),
    false
);

if (!$post_title && !$title && !$content && $primary_image_markup === '' && $secondary_image_markup === '') {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr($className) . '"><p>' . esc_html__('Add content to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <div class="service-hero__inner" data-gsap-animate="stagger">
        <?php if (is_array($service_icon) && !empty($service_icon['url'])) : ?>
            <div class="service-hero__icon" aria-hidden="true">
                <img
                    class="service-hero__icon-img"
                    src="<?php echo esc_url($service_icon['url']); ?>"
                    alt=""
                    <?php if (!empty($service_icon['width'])) : ?>width="<?php echo esc_attr((string) $service_icon['width']); ?>"<?php endif; ?>
                    <?php if (!empty($service_icon['height'])) : ?>height="<?php echo esc_attr((string) $service_icon['height']); ?>"<?php endif; ?>
                    loading="lazy"
                    decoding="async"
                />
            </div>
        <?php endif; ?>

        <div class="service-hero__caption">
            <?php if ($post_title) : ?>
                <p class="service-hero__label"><?php echo esc_html($post_title); ?></p>
            <?php endif; ?>

            <?php if ($title) : ?>
                <h1 class="service-hero__title"><?php echo esc_html($title); ?></h1>
            <?php endif; ?>

            <?php if ($content) : ?>
                <p class="service-hero__description"><?php echo esc_html($content); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($secondary_image_markup) : ?>
            <div class="service-hero__secondary-image">
                <?php echo $secondary_image_markup; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($primary_image_markup) : ?>
        <div class="service-hero__primary-image" data-gsap-animate="slide-right">
            <?php echo $primary_image_markup; ?>
        </div>
    <?php endif; ?>
</section>
