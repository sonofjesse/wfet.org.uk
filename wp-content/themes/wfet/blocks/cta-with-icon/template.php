<?php
/**
 * CTA with Icon Block Template.
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
$id = 'cta-with-icon-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'cta-with-icon';
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
    $preview_image_url = get_template_directory_uri() . '/blocks/cta-with-icon/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="CTA with Icon preview" style="width:100%;height:auto;" />';
    return;
}

$label             = get_field('label');
$content           = get_field('content');
$buttons           = get_field('buttons');
$background_colour = get_field('background_colour') ?: 'midnight';
$background_image  = get_field('_background_image');

$allowed_colours = ['glass', 'midnight', 'sand', 'rose-dark', 'sky-dark', 'moss-dark'];
if (!in_array($background_colour, $allowed_colours, true)) {
    $background_colour = 'midnight';
}

$className .= ' cta-with-icon--bg-' . sanitize_html_class($background_colour);

$has_icon = is_array($background_image) && !empty($background_image['url']);
if ($has_icon) {
    $className .= ' cta-with-icon--has-icon';
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
$is_block_preview = !empty($is_preview);
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <?php if ($has_icon) : ?>
        <div class="cta-with-icon__icon" aria-hidden="true">
            <?php
            echo wp_get_attachment_image(
                $background_image['ID'],
                'full',
                false,
                [
                    'class' => 'cta-with-icon__icon-img',
                    'loading' => $is_block_preview ? 'eager' : 'lazy',
                    'decoding' => 'async',
                ]
            );
            ?>
        </div>
    <?php endif; ?>

    <div class="cta-with-icon__inner">
        <div class="cta-with-icon__copy" data-gsap-animate="fade-in">
            <?php if ($label) : ?>
                <p class="label"><?php echo esc_html($label); ?></p>
            <?php endif; ?>

            <?php if ($content) : ?>
                <div class="cta-with-icon__content">
                    <?php echo wp_kses_post($content); ?>
                </div>
            <?php endif; ?>

            <?php if ($has_buttons) : ?>
                <div class="cta-with-icon__buttons">
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
                            'class' => 'cta-with-icon__button',
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
    </div>
</section>
