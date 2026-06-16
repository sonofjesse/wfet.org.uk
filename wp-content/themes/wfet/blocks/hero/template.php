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
        $hero_image_id = is_array($image) && !empty($image['ID']) ? (int) $image['ID'] : 0;

        if ($hero_image_id > 0 && empty($is_preview)) {
            $preload_url = wp_get_attachment_image_url($hero_image_id, 'full');

            if ($preload_url) {
                add_action(
                    'wp_head',
                    static function () use ($preload_url) {
                        printf(
                            '<link rel="preload" as="image" href="%s" fetchpriority="high" />',
                            esc_url($preload_url)
                        );
                    },
                    5
                );
            }
        }
        ?>
        <?php if ($hero_image_id > 0) : ?>
            <div class="hero__image">
                <?php
                $hero_image_attrs = [
                    'class'    => 'hero__image-img',
                    'loading'  => 'eager',
                    'decoding' => 'async',
                ];

                if (empty($is_preview)) {
                    $hero_image_attrs['fetchpriority'] = 'high';
                }

                echo wp_get_attachment_image(
                    $hero_image_id,
                    'full',
                    false,
                    $hero_image_attrs
                );
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</section>
