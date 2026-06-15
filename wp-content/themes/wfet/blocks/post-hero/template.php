<?php
/**
 * Post Hero Block Template.
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
$id = 'post-hero-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'post-hero';
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

if ($margin_bottom && !in_array($margin_bottom, ['mb-0', 'mb-5'], true)) {
    $className .= ' ' . $margin_bottom;
}

if (!empty($is_preview) && isset($block['data']['preview_image_help'])) {
    $preview_image_url = get_template_directory_uri() . '/blocks/post-hero/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Post Hero preview" style="width:100%;height:auto;" />';
    return;
}

$post_title = get_the_title($post_id);
$content    = get_field('content');
$hero_image = get_field('hero_image');

$categories = get_the_category($post_id);
$category   = !empty($categories) && $categories[0] instanceof WP_Term ? $categories[0] : null;

$category_colour = 'moss-dark';
if ($category instanceof WP_Term && function_exists('soj_get_news_category_colour')) {
    $category_colour = soj_get_news_category_colour($category);
}

$hero_image_id  = 0;
$hero_image_alt = $post_title;

if (is_array($hero_image) && !empty($hero_image['ID'])) {
    $hero_image_id = (int) $hero_image['ID'];

    if (!empty($hero_image['alt'])) {
        $hero_image_alt = (string) $hero_image['alt'];
    }
} elseif (is_numeric($hero_image)) {
    $hero_image_id = (int) $hero_image;
}

if ($hero_image_id > 0 && $hero_image_alt === $post_title) {
    $meta_alt = get_post_meta($hero_image_id, '_wp_attachment_image_alt', true);

    if ($meta_alt) {
        $hero_image_alt = (string) $meta_alt;
    }
}

$hero_image_markup = '';

if ($hero_image_id > 0) {
    $hero_image_markup = soj_picture(
        $hero_image_id,
        [
            0 => [1728, 846, true],
        ],
        [
            'img_class'             => 'post-hero__image-img',
            'alt'                   => $hero_image_alt,
            'use_width_descriptors' => true,
            'sizes'                 => '100vw',
            'loading'               => 'eager',
            'decoding'              => 'async',
            'fetchpriority'         => !empty($is_preview) ? '' : 'high',
            'preload'               => empty($is_preview),
        ]
    );
}

if (!$post_title && !$content && !$category && $hero_image_markup === '') {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr($className) . '"><p>' . esc_html__('Add content to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}
?>

<div class="post-hero-anchor alignfull">
    <section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
        <div class="post-hero__media" aria-hidden="true">
        <?php if ($hero_image_markup) : ?>
            <div class="post-hero__image">
                <?php echo $hero_image_markup; ?>
            </div>
        <?php endif; ?>
        <div class="post-hero__overlay"></div>
    </div>

    <div class="post-hero__inner" data-gsap-animate="stagger">
        <div class="post-hero__caption">
            <?php if ($category instanceof WP_Term) :
                $category_class = 'post-hero__category post-hero__category--' . sanitize_html_class($category_colour);
                $category_link  = get_category_link($category->term_id);

                if (!$is_preview && $category_link && !is_wp_error($category_link)) :
                    ?>
                    <a class="<?php echo esc_attr($category_class); ?>" href="<?php echo esc_url($category_link); ?>">
                        <?php echo esc_html($category->name); ?>
                    </a>
                <?php else : ?>
                    <span class="<?php echo esc_attr($category_class); ?>">
                        <?php echo esc_html($category->name); ?>
                    </span>
                <?php endif;
            endif; ?>

            <?php if ($post_title) : ?>
                <h1 class="post-hero__title"><?php echo esc_html($post_title); ?></h1>
            <?php endif; ?>

            <?php if ($content) : ?>
                <p class="post-hero__description"><?php echo esc_html($content); ?></p>
            <?php endif; ?>
        </div>
        </div>
    </section>
</div>
