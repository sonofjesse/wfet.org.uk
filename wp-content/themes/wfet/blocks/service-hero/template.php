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
$mobile_image    = get_field('mobile_image');
$secondary_image = get_field('secondary_image');
$video           = get_field('video');

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
 * Resolve an attachment ID from an ACF image field value.
 *
 * @param array|int|null $image ACF image field value.
 * @return int
 */
$resolve_image_id = static function ($image) {
    if (is_array($image) && !empty($image['ID'])) {
        return (int) $image['ID'];
    }

    if (is_numeric($image)) {
        return (int) $image;
    }

    return 0;
};

/**
 * Resolve alt text for an attachment.
 *
 * @param array|int|null $image ACF image field value.
 * @param int            $image_id Attachment ID.
 * @return string
 */
$resolve_image_alt = static function ($image, $image_id) {
    if (is_array($image) && !empty($image['alt'])) {
        return (string) $image['alt'];
    }

    if ($image_id > 0) {
        return (string) (get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '');
    }

    return '';
};

/**
 * Build a poster URL for video backgrounds.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $width Target width.
 * @param int $height Target height.
 * @return string
 */
$get_poster_url = static function ($attachment_id, $width, $height) {
    $attachment_id = (int) $attachment_id;

    if ($attachment_id <= 0) {
        return '';
    }

    if (class_exists('SOJ_Dynamic_Images')) {
        $result = SOJ_Dynamic_Images::get_image_src($attachment_id, $width, $height, true);

        if (!empty($result['src'])) {
            return (string) $result['src'];
        }
    }

    $fallback = wp_get_attachment_image_url($attachment_id, 'full');

    return is_string($fallback) ? $fallback : '';
};

/**
 * Render responsive image markup for a service hero image field.
 *
 * @param array|int|null $image       ACF image field value.
 * @param string         $img_class   CSS class for the img element.
 * @param array          $sizes       soj_picture size map.
 * @param string         $sizes_attr  sizes attribute value.
 * @param bool           $is_preview  Whether the block is in editor preview.
 * @param bool           $is_lcp      Whether this image is the page LCP candidate.
 * @param bool|null      $should_preload Whether to add a head preload hint.
 * @param string|null    $fetchpriority  Optional fetchpriority override.
 * @param bool           $force_eager Whether to force eager loading without LCP hints.
 * @return string
 */
$render_image = static function ($image, $img_class, $sizes, $sizes_attr, $is_preview, $is_lcp = false, $should_preload = null, $fetchpriority = null, $force_eager = false) use ($resolve_image_id, $resolve_image_alt) {
    $image_id  = $resolve_image_id($image);
    $image_alt = $resolve_image_alt($image, $image_id);

    if ($image_id <= 0) {
        return '';
    }

    $is_block_preview = !empty($is_preview);
    $loading          = ($is_block_preview || $is_lcp || $force_eager) ? 'eager' : 'lazy';

    if ($should_preload === null) {
        $should_preload = !$is_block_preview && $is_lcp;
    }

    if ($fetchpriority === null) {
        $fetchpriority = $is_block_preview ? '' : ($is_lcp ? 'high' : 'low');
    }

    return soj_picture($image_id, $sizes, [
        'img_class'             => $img_class,
        'alt'                   => $image_alt,
        'use_width_descriptors' => true,
        'sizes'                 => $sizes_attr,
        'loading'               => $loading,
        'decoding'              => 'async',
        'fetchpriority'         => $fetchpriority,
        'preload'               => (bool) $should_preload,
        'defer_browser_load'    => !$is_block_preview && !$is_lcp && !$force_eager,
    ]);
};

$primary_image_id = $resolve_image_id($primary_image);
$mobile_image_id  = $resolve_image_id($mobile_image);
$video_url        = is_array($video) && !empty($video['url']) ? (string) $video['url'] : '';
$has_video        = $video_url !== '';
$has_primary      = $primary_image_id > 0;
$has_mobile_image = $mobile_image_id > 0;
$has_background   = $has_video || $has_primary;

$desktop_poster_id = $primary_image_id;
$mobile_poster_id  = $has_mobile_image ? $mobile_image_id : $primary_image_id;

if ($has_background) {
    $className .= ' service-hero--has-media';
}

if ($has_video) {
    $className .= ' service-hero--has-video';
}

if ($has_mobile_image) {
    $className .= ' service-hero--has-mobile-image';
}

$background_desktop_markup = '';
$background_mobile_markup  = '';
$desktop_poster_url        = '';
$mobile_poster_url         = '';

if ($has_primary) {
    $background_desktop_markup = $render_image(
        $primary_image,
        'service-hero__background-img',
        [0 => [1728, 846, true]],
        '100vw',
        !empty($is_preview),
        true
    );

    $background_mobile_source = $has_mobile_image ? $mobile_image : $primary_image;

    $background_mobile_markup = $render_image(
        $background_mobile_source,
        'service-hero__background-img',
        [0 => [768, 1024, true]],
        '100vw',
        !empty($is_preview),
        false,
        false,
        '',
        true
    );

    $desktop_poster_url = $get_poster_url($desktop_poster_id, 1728, 846);
    $mobile_poster_url  = $get_poster_url($mobile_poster_id, 768, 1024);

    if ($has_video && $desktop_poster_url !== '' && empty($is_preview)) {
        add_action(
            'wp_head',
            static function () use ($desktop_poster_url) {
                printf(
                    '<link rel="preload" as="image" href="%s" fetchpriority="high" />',
                    esc_url($desktop_poster_url)
                );
            },
            5
        );
    }
}

$primary_image_markup = (!$has_background && $has_primary)
    ? $render_image(
        $primary_image,
        'service-hero__primary-img',
        [0 => [675, 786, true]],
        '(min-width: 992px) 675px, (min-width: 768px) 50vw, 100vw',
        !empty($is_preview),
        true
    )
    : '';

$secondary_image_markup = $render_image(
    $secondary_image,
    'service-hero__secondary-img',
    [0 => [440, 288, true]],
    '(min-width: 992px) 440px, (min-width: 768px) 40vw, 100vw',
    !empty($is_preview),
    false
);

if (!$post_title && !$title && !$content && !$has_background && $primary_image_markup === '' && $secondary_image_markup === '') {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr($className) . '"><p>' . esc_html__('Add content to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <?php if ($has_background) : ?>
        <div class="service-hero__media" aria-hidden="true">
            <?php if ($has_video) : ?>
                <?php if ($desktop_poster_url !== '') : ?>
                    <video
                        class="service-hero__video service-hero__video--desktop"
                        src="<?php echo esc_url($video_url); ?>"
                        poster="<?php echo esc_url($desktop_poster_url); ?>"
                        muted
                        loop
                        playsinline
                        autoplay
                        <?php echo empty($is_preview) ? 'fetchpriority="high"' : ''; ?>
                    ></video>
                <?php endif; ?>

                <?php if ($mobile_poster_url !== '') : ?>
                    <video
                        class="service-hero__video service-hero__video--mobile"
                        src="<?php echo esc_url($video_url); ?>"
                        poster="<?php echo esc_url($mobile_poster_url); ?>"
                        muted
                        loop
                        playsinline
                        autoplay
                    ></video>
                <?php elseif ($desktop_poster_url === '') : ?>
                    <video
                        class="service-hero__video"
                        src="<?php echo esc_url($video_url); ?>"
                        muted
                        loop
                        playsinline
                        autoplay
                    ></video>
                <?php endif; ?>
            <?php else : ?>
                <?php if ($background_desktop_markup !== '') : ?>
                    <div class="service-hero__background service-hero__background--desktop">
                        <?php echo $background_desktop_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>

                <?php if ($background_mobile_markup !== '') : ?>
                    <div class="service-hero__background service-hero__background--mobile">
                        <?php echo $background_mobile_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

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
                <?php echo $secondary_image_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($primary_image_markup) : ?>
        <div class="service-hero__primary-image" data-gsap-animate="slide-right">
            <?php echo $primary_image_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    <?php endif; ?>
</section>
