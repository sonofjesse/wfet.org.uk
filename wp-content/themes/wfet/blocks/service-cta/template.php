<?php
/**
 * Service CTA Block Template.
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
$id = 'service-cta-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'service-cta';
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
    $preview_image_url = get_template_directory_uri() . '/blocks/service-cta/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Service CTA preview" style="width:100%;height:auto;" />';
    return;
}

$related_service = get_field('related_service');
$content         = get_field('content');
$buttons         = get_field('buttons');

$service_id = 0;
if ($related_service instanceof WP_Post) {
    $service_id = (int) $related_service->ID;
} elseif (is_numeric($related_service)) {
    $service_id = (int) $related_service;
}

$service_colour = 'rose-dark';
$service_image  = null;

if ($service_id > 0) {
    $colour = get_field('service_colour', $service_id);
    $allowed_colours = ['rose-dark', 'moss-dark', 'sky-dark'];
    if (in_array($colour, $allowed_colours, true)) {
        $service_colour = $colour;
    }

    $service_image = get_field('service_image', $service_id);
    if (!is_array($service_image) || empty($service_image['url'])) {
        $service_image = get_field('service_icon', $service_id);
    }
}

$className .= ' service-cta--bg-' . sanitize_html_class($service_colour);

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

if (!$content && !$has_buttons) {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr($className) . '"><p>' . esc_html__('Add content and buttons to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <?php if (is_array($service_image) && !empty($service_image['url'])) : ?>
        <div class="service-cta__graphic" aria-hidden="true">
            <img
                class="service-cta__graphic-img"
                src="<?php echo esc_url($service_image['url']); ?>"
                alt=""
                <?php if (!empty($service_image['width'])) : ?>width="<?php echo esc_attr((string) $service_image['width']); ?>"<?php endif; ?>
                <?php if (!empty($service_image['height'])) : ?>height="<?php echo esc_attr((string) $service_image['height']); ?>"<?php endif; ?>
                loading="lazy"
                decoding="async"
            />
        </div>
    <?php endif; ?>

    <div class="service-cta__inner">
        <?php if ($content) : ?>
            <div class="service-cta__copy">
                <?php echo wp_kses_post($content); ?>
            </div>
        <?php endif; ?>

        <?php if ($has_buttons) : ?>
            <div class="service-cta__buttons">
                <?php
                $button_index = 0;
                foreach ($buttons as $row) :
                    $button = isset($row['button']) ? $row['button'] : null;
                    if (!is_array($button) || empty($button['url']) || empty($button['title'])) {
                        continue;
                    }

                    $variant = $button_index === 0 ? 'primary' : 'secondary';
                    $button_index++;
                    soj_the_button($button, [
                        'variant' => $variant,
                        'class' => 'service-cta__button',
                        'style' => $variant === 'primary' ? 'midnight' : '',
                    ]);
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
