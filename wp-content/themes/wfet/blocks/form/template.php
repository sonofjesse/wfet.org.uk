<?php
/**
 * Form Block Template.
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
$id = 'form-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'form';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if (!empty($block['align'])) {
    $className .= ' align' . $block['align'];
}

$margin_top    = get_field('margin_top') ?: 'mt-0';
$margin_bottom = get_field('margin_bottom') ?: 'mb-0';

if (strpos($margin_top, 'mt-') !== false) {
    $margin_top = str_replace('mt-', 'pt-', $margin_top);
}
if (strpos($margin_bottom, 'mb-') !== false) {
    $margin_bottom = str_replace('mb-', 'pb-', $margin_bottom);
}

if ($margin_top) {
    $className .= ' ' . $margin_top;
}
if ($margin_bottom) {
    $className .= ' ' . $margin_bottom;
}

if (!empty($is_preview) && isset($block['data']['preview_image_help'])) {
    $preview_image_url = get_template_directory_uri() . '/blocks/form/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Form preview" style="width:100%;height:auto;" />';
    return;
}

$title             = get_field('title');
$content           = get_field('content');
$buttons           = get_field('buttons');
$gravity_form_id   = get_field('gravity_form_id');
$background_colour = get_field('background_colour') ?: 'ocean';

$allowed_colours = ['ocean', 'glass', 'midnight', 'moss-dark', 'sky-dark', 'rose-dark'];
if (!in_array($background_colour, $allowed_colours, true)) {
    $background_colour = 'ocean';
}

$className .= ' form--bg-' . sanitize_html_class($background_colour);

$has_buttons = false;
if (!empty($buttons) && is_array($buttons)) {
    foreach ($buttons as $row) {
        $button = $row['button'] ?? null;
        if (is_array($button) && !empty($button['url']) && !empty($button['title'])) {
            $has_buttons = true;
            break;
        }
    }
}

$form_id = absint($gravity_form_id);
$has_form = $form_id > 0 && function_exists('gravity_form');

if (!$title && !$content && !$has_buttons && !$has_form) {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr(trim($className)) . '"><p>' . esc_html__('Add content and a Gravity Form ID to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}

$is_light_bg = in_array($background_colour, ['moss-dark', 'sky-dark', 'rose-dark'], true);

$form_images_uri = get_template_directory_uri() . '/blocks/form/images/';
$left_arrow_url  = $form_images_uri . 'left_arrow.svg';
$right_arrow_url = $form_images_uri . 'right_arrow.svg';
?>

<div class="form-anchor">
    <section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
        <div class="form__arrow form__arrow--left" aria-hidden="true" data-gsap-animate="slide-left">
            <img
                class="form__arrow-img"
                src="<?php echo esc_url($left_arrow_url); ?>"
                alt=""
                width="176"
                height="354"
                loading="lazy"
                decoding="async"
            />
        </div>

        <div class="form__arrow form__arrow--right" aria-hidden="true" data-gsap-animate="slide-right">
            <img
                class="form__arrow-img"
                src="<?php echo esc_url($right_arrow_url); ?>"
                alt=""
                width="178"
                height="354"
                loading="lazy"
                decoding="async"
            />
        </div>

        <div class="form__inner">
            <div class="form__content">
                    <?php if ($title) : ?>
                        <div class="form__title">
                            <?php echo wp_kses_post($title); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($content) : ?>
                        <div class="form__copy">
                            <?php echo wp_kses_post($content); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($has_buttons) : ?>
                        <div class="form__buttons">
                            <?php foreach ($buttons as $row) :
                                $button = isset($row['button']) ? $row['button'] : null;
                                if (!is_array($button) || empty($button['url']) || empty($button['title'])) {
                                    continue;
                                }

                                $args = [
                                    'variant' => 'primary',
                                    'class'   => 'form__button',
                                ];

                                if ($is_light_bg) {
                                    $args['style'] = 'midnight';
                                }

                                soj_the_button($button, $args);
                                ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
            </div>

            <?php if ($has_form) : ?>
                <div class="form__form">
                    <?php
                    gravity_form(
                        $form_id,
                        false,
                        false,
                        false,
                        null,
                        true,
                        0,
                        true
                    );
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
