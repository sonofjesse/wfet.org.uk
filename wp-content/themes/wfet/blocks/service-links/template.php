<?php
/**
 * Service Links Block Template.
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
$id = 'service-links-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'service-links';
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
    $preview_image_url = get_template_directory_uri() . '/blocks/service-links/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Service Links preview" style="width:100%;height:auto;" />';
    return;
}

$is_block_preview = !empty($is_preview);

if ($is_block_preview) {
    $className .= ' service-links--editor-preview';
}

$services = get_field('service_links');
if (empty($services) || !is_array($services)) {
    return;
}

$allowed_colours = array('rose-dark', 'moss-dark', 'sky-dark');

$current_service_id = 0;
if (is_singular('service')) {
    $current_service_id = (int) get_queried_object_id();
}
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($className); ?>">
    <div class="service-links__grid">
        <?php foreach ($services as $service) :
            $service_id = 0;
            if (is_object($service) && isset($service->ID)) {
                $service_id = (int) $service->ID;
            } elseif (is_numeric($service)) {
                $service_id = (int) $service;
            }

            if ($service_id <= 0) {
                continue;
            }

            $title     = get_the_title($service_id);
            $excerpt   = get_the_excerpt($service_id);
            $permalink = get_permalink($service_id);

            if (!$title || !$permalink) {
                continue;
            }

            $colour = get_field('service_colour', $service_id);
            if (!in_array($colour, $allowed_colours, true)) {
                $colour = 'rose-dark';
            }

            $icon = get_field('service_icon', $service_id);
            $is_current_service = $current_service_id > 0 && $service_id === $current_service_id;
            $column_class = 'service-links__column service-links__column--' . sanitize_html_class($colour);
            if ($is_current_service) {
                $column_class .= ' service-links__column--current';
            }

            $thumbnail_id = (int) get_post_thumbnail_id($service_id);
            $image_markup = $thumbnail_id
                ? soj_picture($thumbnail_id, [
                    0 => [576, 451, true],
                ], [
                    'img_class'             => 'service-links__image-img',
                    'alt'                   => '',
                    'use_width_descriptors' => true,
                    'sizes'                 => '(min-width: 1440px) 576px, (min-width: 768px) 33vw, 100vw',
                    'loading'               => $is_block_preview ? 'eager' : 'lazy',
                    'decoding'              => 'async',
                    'fetchpriority'         => $is_block_preview ? '' : 'low',
                    'defer_browser_load'    => !$is_block_preview,
                ])
                : '';
            ?>
            <article class="<?php echo esc_attr($column_class); ?>"<?php echo $is_current_service ? ' aria-current="page"' : ''; ?>>
                <?php if ($is_block_preview || $is_current_service) : ?>
                    <div class="service-links__column-inner" >
                <?php else : ?>
                    <a class="service-links__column-link" href="<?php echo esc_url($permalink); ?>">
                <?php endif; ?>
                    <div class="service-links__caption" data-gsap-animate="stagger">
                        <h2 class="service-links__title"><?php echo esc_html($title); ?></h2>

                        <?php if ($excerpt) : ?>
                            <p class="service-links__description"><?php echo esc_html($excerpt); ?></p>
                        <?php endif; ?>

                        <span class="service-links__link">
                            <span class="service-links__link-text"><?php esc_html_e('More information', 'soj-core'); ?></span>
                            <span class="service-links__link-arrow" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 8H14.5M14.5 8L8.5 2M14.5 8L8.5 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </span>

                        <?php
                        if (is_array($icon) && !empty($icon['url'])) :
                            ?>
                            <div class="service-links__icon" aria-hidden="true">
                                <img
                                    class="service-links__icon-img"
                                    src="<?php echo esc_url($icon['url']); ?>"
                                    alt=""
                                    <?php if (!empty($icon['width'])) : ?>width="<?php echo esc_attr((string) $icon['width']); ?>"<?php endif; ?>
                                    <?php if (!empty($icon['height'])) : ?>height="<?php echo esc_attr((string) $icon['height']); ?>"<?php endif; ?>
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <?php
                        endif;
                        ?>
                    </div>

                    <?php if ($image_markup) : ?>
                        <div class="service-links__image">
                            <?php echo $image_markup; ?>
                        </div>
                    <?php endif; ?>

                    <div class="service-links__border" aria-hidden="true"></div>
                <?php if ($is_block_preview || $is_current_service) : ?>
                    </div>
                <?php else : ?>
                    </a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
