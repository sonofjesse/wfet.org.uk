<?php
/**
 * Intro Block Template.
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
$id = 'intro-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'intro';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if (!empty($block['align'])) {
    $className .= ' align' . $block['align'];
}

$title       = get_field('title');
$content     = get_field('content');
$sub_content = get_field('sub_content');

if (!empty($is_preview) && isset($block['data']['preview_image_help'])) {
    $preview_image_url = get_template_directory_uri() . '/blocks/intro/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Intro preview" style="width:100%;height:auto;" />';
    return;
}

$margin_top    = get_field('margin_top') ?: 'mt-0';
$margin_bottom = get_field('margin_bottom') ?: 'mb-0';

if (!$content && !$title && !$sub_content) {
    return;
}
?>

<section id="<?php echo esc_attr($id); ?>"
    class="<?php echo esc_attr($className); ?>
        <?php if ($margin_top) echo esc_attr($margin_top); ?>
        <?php if ($margin_bottom) echo esc_attr($margin_bottom); ?>">

    <div class="intro__inner">
        <?php if ($content): ?>
            <div class="intro__text"
                data-highlight-text
                data-highlight-scroll-start="top 90%"
                data-highlight-scroll-end="center 40%"
                data-highlight-fade="0.2"
                data-highlight-stagger="0.1">
                <?php echo wp_kses_post($content); ?>
            </div>
        <?php elseif ($title): ?>
            <p class="intro__text"
                data-highlight-text
                data-highlight-scroll-start="top 90%"
                data-highlight-scroll-end="center 40%"
                data-highlight-fade="0.2"
                data-highlight-stagger="0.1"><?php echo esc_html($title); ?></p>
        <?php endif; ?>

        <?php if ($sub_content): ?>
            <div class="intro__sub-text" data-gsap-animate="slide-up">
                <?php echo wp_kses_post($sub_content); ?>
            </div>
        <?php endif; ?>
    </div>

</section>
