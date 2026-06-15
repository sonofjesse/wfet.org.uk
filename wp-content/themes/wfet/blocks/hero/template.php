<?php
/**
 * Hero Block Template.
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
$id = 'hero-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'hero';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if (!empty($block['align'])) {
    $className .= ' align' . $block['align'];
}

$title   = get_field('title');
$content = get_field('content');
$link    = get_field('link');
$image   = get_field('image');

if (!empty($is_preview) && isset($block['data']['preview_image_help'])) {
    $preview_image_url = get_template_directory_uri() . '/blocks/hero/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Hero preview" style="width:100%;height:auto;" />';
    return;
}

$margin_top    = get_field('margin_top') ?: 'mt-0';
$margin_bottom = get_field('margin_bottom') ?: 'mb-0';
?>

<section id="<?php echo esc_attr($id); ?>"
    class="<?php echo esc_attr($className); ?>
        <?php if ($margin_top) echo esc_attr($margin_top); ?>
        <?php if ($margin_bottom) echo esc_attr($margin_bottom); ?>">

    <div class="hero__inner" data-gsap-animate="stagger">

        <div class="hero__body">
            <?php if ($title): ?>
                <h1 class="hero__title"><?php echo wp_kses_post($title); ?></h1>
            <?php endif; ?>

            <?php if ($content): ?>
                <div class="hero__content">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($link) && !empty($link['url']) && !empty($link['title'])) : ?>
                <?php soj_the_button($link, ['class' => 'hero__cta']); ?>
            <?php endif; ?>
        </div>

    </div>

    <?php if ($image) :
        $hero_image_id  = is_array($image) && !empty($image['ID']) ? (int) $image['ID'] : 0;
        $hero_image_alt = is_array($image) && !empty($image['alt']) ? (string) $image['alt'] : '';

        if ($hero_image_id > 0 && $hero_image_alt === '') {
            $hero_image_alt = get_post_meta($hero_image_id, '_wp_attachment_image_alt', true) ?: '';
        }

        $hero_image_markup = $hero_image_id > 0
            ? soj_picture(
                $hero_image_id,
                [
                    1200 => [1300, 1300, true],
                    768  => [800, 800, true],
                    0    => [480, 480, true],
                ],
                [
                    'img_class'             => 'hero__image-img',
                    'alt'                   => $hero_image_alt,
                    'use_width_descriptors' => true,
                    'sizes'                 => '(min-width: 1460px) 1300px, (min-width: 1200px) 65vw, 95vw',
                    'loading'               => 'eager',
                    'decoding'              => 'async',
                    'fetchpriority'         => !empty($is_preview) ? '' : 'high',
                    'preload'               => empty($is_preview),
                ]
            )
            : '';
        ?>
        <?php if ($hero_image_markup !== '') : ?>
            <div class="hero__image">
                <?php echo $hero_image_markup; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</section>
