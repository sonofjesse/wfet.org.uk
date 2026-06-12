<?php
/**
 * Text and Icons Block Template.
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
$id = 'text-and-icons-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'text-and-icons';
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
    $preview_image_url = get_template_directory_uri() . '/blocks/text-and-icons/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Text and Icons preview" style="width:100%;height:auto;" />';
    return;
}

$allowed_colours = ['rose-dark', 'moss-dark', 'sky-dark'];
$context_post_id = !empty($post_id) ? (int) $post_id : (int) get_the_ID();
if (get_post_type($context_post_id) === 'service') {
    $service_colour = get_field('service_colour', $context_post_id);
    if (!in_array($service_colour, $allowed_colours, true)) {
        $service_colour = 'rose-dark';
    }
    $className .= ' text-and-icons--service-' . sanitize_html_class($service_colour);
}

$label = get_field('label');
$title = get_field('title');
$content = get_field('content');
$items   = get_field('items');

$has_items = false;
if (!empty($items) && is_array($items)) {
    foreach ($items as $row) {
        if (!empty($row['icon']) || !empty($row['title']) || !empty($row['content'])) {
            $has_items = true;
            break;
        }
    }
}

if (!$label && !$title && !$content && !$has_items) {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr($className) . '"><p>' . esc_html__('Add content to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}

$is_block_preview = !empty($is_preview);
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <div class="text-and-icons__inner" data-gsap-animate="slide-up">
        <?php if ($label || $title || $content) : ?>
            <header class="text-and-icons__header">
                <?php if ($label) : ?>
                    <p class="label"><?php echo esc_html($label); ?></p>
                <?php endif; ?>

                <?php if ($title) : ?>
                    <h2 class="text-and-icons__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>

                <?php if ($content) : ?>
                    <p class="text-and-icons__intro"><?php echo esc_html($content); ?></p>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if ($has_items) : ?>
            <div class="text-and-icons__items" data-gsap-animate="stagger">
                <?php foreach ($items as $row) :
                    $item_icon    = $row['icon'] ?? null;
                    $item_title   = $row['title'] ?? '';
                    $item_content = $row['content'] ?? '';

                    if (!$item_icon && !$item_title && !$item_content) {
                        continue;
                    }
                    ?>
                    <article class="text-and-icons__item">
                        <?php
                        $item_icon_markup = '';
                        if (is_array($item_icon) && !empty($item_icon['url'])) {
                            $item_icon_id = !empty($item_icon['ID']) ? (int) $item_icon['ID'] : 0;
                            $is_svg_icon  = $item_icon_id > 0 && get_post_mime_type($item_icon_id) === 'image/svg+xml';
                            $icon_loading = $is_block_preview ? 'eager' : 'lazy';

                            if ($is_svg_icon) {
                                $item_icon_markup = sprintf(
                                    '<img class="text-and-icons__icon-img" src="%s" alt="" loading="%s" decoding="async" />',
                                    esc_url((string) $item_icon['url']),
                                    esc_attr($icon_loading)
                                );
                            } else {
                                $item_icon_markup = soj_picture($item_icon, [
                                    0 => [80, 80, false],
                                ], [
                                    'img_class'             => 'text-and-icons__icon-img',
                                    'alt'                   => '',
                                    'use_width_descriptors' => true,
                                    'sizes'                 => '80px',
                                    'loading'               => $icon_loading,
                                    'decoding'              => 'async',
                                ]);
                            }
                        }

                        if ($item_icon_markup) :
                            ?>
                            <div class="text-and-icons__icon" aria-hidden="true">
                                <?php echo $item_icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($item_title) : ?>
                            <h3 class="text-and-icons__item-title"><?php echo esc_html($item_title); ?></h3>
                        <?php endif; ?>

                        <?php if ($item_content) : ?>
                            <div class="text-and-icons__item-content"><?php echo wp_kses_post($item_content); ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
