<?php
/**
 * CTA Block Template.
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
$id = 'cta-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'cta';
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
    $preview_image_url = get_template_directory_uri() . '/blocks/cta/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="CTA preview" style="width:100%;height:auto;" />';
    return;
}

$allowed_service_colours = ['rose-dark', 'moss-dark', 'sky-dark'];
$context_post_id         = !empty($post_id) ? (int) $post_id : (int) get_the_ID();
if (get_post_type($context_post_id) === 'service') {
    $service_colour = get_field('service_colour', $context_post_id);
    if (!in_array($service_colour, $allowed_service_colours, true)) {
        $service_colour = 'rose-dark';
    }
    $className .= ' cta--service-' . sanitize_html_class($service_colour);
}

$label             = get_field('label');
$content           = get_field('content');
$buttons           = get_field('buttons');
$background_colour = get_field('background_colour') ?: 'glass';
$background_image  = get_field('background_image');

$allowed_colours = ['glass', 'midnight', 'sand', 'rose-dark', 'sky-dark', 'moss-dark'];
if (!in_array($background_colour, $allowed_colours, true)) {
    $background_colour = 'glass';
}

$className .= ' cta--bg-' . sanitize_html_class($background_colour);

$has_bg_image = is_array($background_image) && !empty($background_image['url']);
if ($has_bg_image) {
    $className .= ' cta--has-bg-image';
}

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

if (!$label && !$content && !$has_buttons) {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr(trim($className)) . '"><p>' . esc_html__('Add content and buttons to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}

$is_light_bg = in_array($background_colour, ['sand', 'rose-dark', 'moss-dark', 'sky-dark'], true);
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <?php if ($has_bg_image) : ?>
        <div class="cta__media" aria-hidden="true">
            <img
                class="cta__media-image"
                src="<?php echo esc_url($background_image['url']); ?>"
                alt=""
                <?php if (!empty($background_image['width'])) : ?>width="<?php echo esc_attr((string) $background_image['width']); ?>"<?php endif; ?>
                <?php if (!empty($background_image['height'])) : ?>height="<?php echo esc_attr((string) $background_image['height']); ?>"<?php endif; ?>
                loading="lazy"
                decoding="async"
            />
            <span class="cta__media-overlay"></span>
        </div>
    <?php endif; ?>

    <div class="cta__inner" data-gsap-animate="stagger">
        <?php if ($label) : ?>
            <p class="label"><?php echo esc_html($label); ?></p>
        <?php endif; ?>

        <?php if ($content) : ?>
            <div class="cta__copy">
                <?php echo wp_kses_post($content); ?>
            </div>
        <?php endif; ?>

        <?php if ($has_buttons) : ?>
            <div class="cta__buttons">
                <?php
                $button_index = 0;
                foreach ($buttons as $row) :
                    $button = isset($row['button']) ? $row['button'] : null;
                    if (!is_array($button) || empty($button['url']) || empty($button['title'])) {
                        continue;
                    }

                    $variant = $button_index === 0 ? 'primary' : 'secondary';
                    $button_index++;

                    $args = [
                        'variant' => $variant,
                        'class' => 'cta__button',
                    ];

                    if ($variant === 'primary' && $is_light_bg) {
                        $args['style'] = 'midnight';
                    }

                    soj_the_button($button, $args);
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
