<?php
/**
 * Content Columns Block Template.
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
$id = 'content-columns-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'content-columns';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if (!empty($block['align'])) {
    $className .= ' align' . $block['align'];
}

$margin_top    = get_field('margin_top') ?: 'mt-0';
$margin_bottom = get_field('margin_bottom') ?: 'mb-0';

// convert margin top and bottom to padding top and bottom where it has mt-x change the class to pt-x
// and where it has mb-x change the class to pb-x
if (strpos($margin_top, 'mt-') !== false) {
    $margin_top = str_replace('mt-', 'pt-', $margin_top);
}
if (strpos($margin_bottom, 'mb-') !== false) {
    $margin_bottom = str_replace('mb-', 'pb-', $margin_bottom);
}

$use_as_hero = (bool) get_field('use_as_hero');

if ($use_as_hero) {
    $className .= ' content-columns--hero alignfull';
} elseif ($margin_top) {
    $className .= ' ' . $margin_top;
}

if ($margin_bottom) {
    $className .= ' ' . $margin_bottom;
}

if (!empty($is_preview) && isset($block['data']['preview_image_help'])) {
    $preview_image_url = get_template_directory_uri() . '/blocks/content-columns/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Content Columns preview" style="width:100%;height:auto;" />';
    return;
}

$allowed_colours = ['rose-dark', 'moss-dark', 'sky-dark'];
$context_post_id = !empty($post_id) ? (int) $post_id : (int) get_the_ID();
if (get_post_type($context_post_id) === 'service') {
    $service_colour = get_field('service_colour', $context_post_id);
    if (!in_array($service_colour, $allowed_colours, true)) {
        $service_colour = 'rose-dark';
    }
    $className .= ' content-columns--service-' . sanitize_html_class($service_colour);
}

$left_column         = get_field('left_column');
$right_column        = get_field('right_column');
$service_graphic     = get_field('service_graphic');
$background_colour   = get_field('background_colour') ?: 'midnight';
$is_block_preview    = !empty($is_preview);

$allowed_bg_colours = ['midnight', 'sand', 'white', 'ocean', 'glass'];
if (!in_array($background_colour, $allowed_bg_colours, true)) {
    $background_colour = 'midnight';
}

$className .= ' content-columns--bg-' . sanitize_html_class($background_colour);

$content_columns_pdf_icon = get_template_directory_uri() . '/blocks/content-columns/images/pdf.svg';

/**
 * Whether an icons repeater has any content to render.
 *
 * @param array|null $icons
 * @return bool
 */
$icons_have_content = static function ($icons) {
    if (empty($icons) || !is_array($icons)) {
        return false;
    }

    foreach ($icons as $row) {
        if (!is_array($row)) {
            continue;
        }

        $icon = $row['icon'] ?? null;
        if (!empty($row['icon_title']) || !empty($row['icon_content'])) {
            return true;
        }
        if (is_array($icon) && !empty($icon['ID'])) {
            return true;
        }
        if (is_numeric($icon) && (int) $icon > 0) {
            return true;
        }
    }

    return false;
};

/**
 * Whether a resources repeater has any content to render.
 *
 * @param array|null $resources
 * @return bool
 */
$resources_have_content = static function ($resources) {
    if (empty($resources) || !is_array($resources)) {
        return false;
    }

    foreach ($resources as $row) {
        if (!is_array($row)) {
            continue;
        }

        $resource = $row['resource'] ?? null;
        if (is_array($resource) && !empty($resource['url'])) {
            return true;
        }
        if (is_numeric($resource) && (int) $resource > 0) {
            return true;
        }
    }

    return false;
};

/**
 * Format an ACF file field value as a human-readable size in KB.
 *
 * @param array $file
 * @return string
 */
$format_resource_filesize = static function ($file) {
    if (!is_array($file)) {
        return '';
    }

    $bytes = (int) ($file['filesize'] ?? 0);

    if ($bytes <= 0 && !empty($file['ID'])) {
        $path = get_attached_file((int) $file['ID']);
        if (is_string($path) && $path !== '' && file_exists($path)) {
            $bytes = (int) filesize($path);
        }
    }

    if ($bytes <= 0) {
        return '';
    }

    $kilobytes = (int) round($bytes / 1024);

    if ($kilobytes < 1) {
        $kilobytes = 1;
    }

    return sprintf('%dKB', $kilobytes);
};

/**
 * Whether a column group has any content to render.
 *
 * @param array|null $column
 * @return bool
 */
$column_has_content = static function ($column) use ($icons_have_content, $resources_have_content) {
    if (!is_array($column)) {
        return false;
    }

    if (!empty($column['label']) || !empty($column['title']) || !empty($column['content'])) {
        return true;
    }

    $image = $column['image'] ?? null;
    if (is_array($image) && !empty($image['ID'])) {
        return true;
    }
    if (is_numeric($image) && (int) $image > 0) {
        return true;
    }

    if (!empty($column['buttons']) && is_array($column['buttons'])) {
        foreach ($column['buttons'] as $row) {
            $button = $row['button'] ?? null;
            if (is_array($button) && !empty($button['url']) && !empty($button['title'])) {
                return true;
            }
        }
    }

    if (($column['supporting_content'] ?? '') === 'icons' && $icons_have_content($column['icons'] ?? null)) {
        return true;
    }

    if (($column['supporting_content'] ?? '') === 'resources' && $resources_have_content($column['resources'] ?? null)) {
        return true;
    }

    if (($column['supporting_content'] ?? '') !== 'quote') {
        return false;
    }

    $quote = $column['quote'] ?? null;
    if (!is_array($quote)) {
        return false;
    }

    return !empty($quote['quote_title'])
        || !empty($quote['quote_name'])
        || !empty($quote['quote_position'])
        || (is_array($quote['quote_image'] ?? null) && !empty($quote['quote_image']['ID']))
        || (is_numeric($quote['quote_image'] ?? null) && (int) $quote['quote_image'] > 0);
};

if (!$column_has_content($left_column) && !$column_has_content($right_column)) {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr($className) . '"><p>' . esc_html__('Add content to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}

/**
 * Render responsive image markup for a column image field.
 *
 * @param array|int|null $image
 * @param string         $img_class
 * @param bool           $is_preview
 * @return string
 */
$render_column_image = static function ($image, $img_class, $is_preview, &$display_width = null) {
    $image_id  = 0;
    $image_alt = '';

    if (is_array($image) && !empty($image['ID'])) {
        $image_id  = (int) $image['ID'];
        $image_alt = !empty($image['alt']) ? (string) $image['alt'] : '';
    } elseif (is_numeric($image)) {
        $image_id = (int) $image;
    }

    if ($image_id <= 0) {
        return '';
    }

    if ($image_alt === '') {
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '';
    }

    $loading = $is_preview ? 'eager' : 'lazy';

    $img_attributes = [];
    $metadata       = wp_get_attachment_metadata($image_id);
    $orig_width     = (int) ($metadata['width'] ?? 0);
    $orig_height    = (int) ($metadata['height'] ?? 0);
    $max_display_w  = 632;

    if ($orig_width > 0 && $orig_height > 0) {
        if ($orig_width > $max_display_w) {
            $img_attributes['width']  = $max_display_w;
            $img_attributes['height'] = (int) round($orig_height * ($max_display_w / $orig_width));
        } else {
            $img_attributes['width']  = $orig_width;
            $img_attributes['height'] = $orig_height;
        }

        if ($display_width !== null) {
            $display_width = (int) $img_attributes['width'];
        }
    }

    $mime_type = get_post_mime_type($image_id);
    if ($mime_type === 'image/svg+xml') {
        $image_url = wp_get_attachment_url($image_id);
        if ($image_url) {
            return sprintf(
                '<img class="%s" src="%s" alt="%s" loading="%s" decoding="async" />',
                esc_attr($img_class),
                esc_url($image_url),
                esc_attr($image_alt),
                esc_attr($loading)
            );
        }

        return '';
    }

    return soj_picture($image_id, [
        0 => [632, 0, false],
    ], [
        'img_class'             => $img_class,
        'alt'                   => $image_alt,
        'use_width_descriptors' => true,
        'sizes'                 => '(min-width: 992px) 632px, 100vw',
        'loading'               => $loading,
        'decoding'              => 'async',
        'fetchpriority'         => $is_preview ? '' : 'low',
        'img_attributes'        => $img_attributes,
    ]);
};

$service_graphic_url = '';
if (is_array($service_graphic) && !empty($service_graphic['url'])) {
    $service_graphic_url = (string) $service_graphic['url'];
} elseif (is_numeric($service_graphic) && (int) $service_graphic > 0) {
    $service_graphic_url = (string) wp_get_attachment_url((int) $service_graphic);
}

if ($service_graphic_url !== '') {
    $className .= ' content-columns--has-service-graphic';
}

/**
 * Render avatar image for quote attribution.
 *
 * @param array|int|null $image
 * @param bool           $is_preview
 * @return string
 */
$render_quote_avatar = static function ($image, $is_preview) use ($render_column_image) {
    return $render_column_image($image, 'content-columns__quote-avatar-img', $is_preview);
};

/**
 * Render a single content column.
 *
 * @param array|null $column
 * @param string     $modifier BEM modifier (left|right).
 * @param bool       $is_preview
 */
$render_column = static function ($column, $modifier, $is_preview) use ($column_has_content, $render_column_image, $render_quote_avatar, $icons_have_content, $resources_have_content, $format_resource_filesize, $content_columns_pdf_icon) {
    if (!$column_has_content($column)) {
        return;
    }

    $label              = $column['label'] ?? '';
    $title              = $column['title'] ?? '';
    $content            = $column['content'] ?? '';
    $buttons            = $column['buttons'] ?? [];
    $supporting_content = $column['supporting_content'] ?? '';
    $quote              = is_array($column['quote'] ?? null) ? $column['quote'] : [];

    $quote_title    = $quote['quote_title'] ?? '';
    $quote_name     = $quote['quote_name'] ?? '';
    $quote_position = $quote['quote_position'] ?? '';
    $quote_image    = $quote['quote_image'] ?? null;

    $has_quote = $supporting_content === 'quote'
        && ($quote_title || $quote_name || $quote_position || $render_quote_avatar($quote_image, $is_preview) !== '');
    $has_icons     = $supporting_content === 'icons' && $icons_have_content($column['icons'] ?? null);
    $has_resources = $supporting_content === 'resources' && $resources_have_content($column['resources'] ?? null);
    $icons         = $column['icons'] ?? [];
    $resources     = $column['resources'] ?? [];

    $image_display_width = 0;
    $image_markup        = $render_column_image($column['image'] ?? null, 'content-columns__media-img', $is_preview, $image_display_width);
    ?>
    <div data-gsap-animate="stagger" class="content-columns__column content-columns__column--<?php echo esc_attr($modifier); ?>">
        <?php if ($label) : ?>
            <p class="label"><?php echo esc_html($label); ?></p>
        <?php endif; ?>

        <?php if ($title) : ?>
            <h3 class="content-columns__title"><?php echo esc_html($title); ?></h3>
        <?php endif; ?>

        <?php if ($image_markup) :
            $image_caption_markup = soj_render_attachment_figcaption($column['image'] ?? null);
            ?>
            <div class="content-columns__media"<?php
            if ($image_display_width > 0) {
                echo ' style="width:' . (int) $image_display_width . 'px;max-width:100%;"';
            }
            ?>>
                <figure class="content-columns__figure">
                    <?php echo $image_markup; ?>
                    <?php echo $image_caption_markup; ?>
                </figure>
            </div>
        <?php endif; ?>

        <?php if ($content) : ?>
            <div class="content-columns__content">
                <?php echo wp_kses_post($content); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($buttons) && is_array($buttons)) : ?>
            <div class="content-columns__buttons">
                <?php foreach ($buttons as $row) :
                    $button = isset($row['button']) ? $row['button'] : null;
                    if (!is_array($button) || empty($button['url']) || empty($button['title'])) {
                        continue;
                    }
                    ?>
                    <?php soj_the_button($button, ['class' => 'content-columns__button']); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($has_quote) : ?>
            <figure class="content-columns__quote">
                <div class="content-columns__quote-bar" aria-hidden="true"></div>

                <?php if ($quote_title) : ?>
                    <h4 class="content-columns__quote-text"><?php echo esc_html($quote_title); ?></h4>
                <?php endif; ?>

                <?php if ($quote_name || $quote_position || $render_quote_avatar($quote_image, $is_preview) !== '') : ?>
                    <figcaption class="content-columns__quote-attribution">
                        <?php
                        $avatar_markup = $render_quote_avatar($quote_image, $is_preview);
                        if ($avatar_markup) :
                            ?>
                            <div class="content-columns__quote-avatar">
                                <?php echo $avatar_markup; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($quote_name || $quote_position) : ?>
                            <div class="content-columns__quote-meta">
                                <?php if ($quote_name) : ?>
                                    <p class="content-columns__quote-name"><?php echo esc_html($quote_name); ?></p>
                                <?php endif; ?>
                                <?php if ($quote_position) : ?>
                                    <p class="content-columns__quote-position"><?php echo esc_html($quote_position); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </figcaption>
                <?php endif; ?>
            </figure>
        <?php endif; ?>

        <?php if ($has_resources) : ?>
            <div class="content-columns__resources" role="list">
                <?php foreach ($resources as $row) :
                    if (!is_array($row)) {
                        continue;
                    }

                    $resource = $row['resource'] ?? null;
                    if (is_numeric($resource) && (int) $resource > 0) {
                        $attachment_id = (int) $resource;
                        $resource      = [
                            'ID'       => $attachment_id,
                            'id'       => $attachment_id,
                            'url'      => (string) wp_get_attachment_url($attachment_id),
                            'title'    => (string) get_the_title($attachment_id),
                            'filename' => (string) wp_basename((string) get_attached_file($attachment_id)),
                            'filesize' => 0,
                        ];
                    }

                    if (!is_array($resource) || empty($resource['url'])) {
                        continue;
                    }

                    $resource_title = trim((string) ($resource['title'] ?? ''));
                    if ($resource_title === '') {
                        $resource_title = trim((string) pathinfo((string) ($resource['filename'] ?? ''), PATHINFO_FILENAME));
                    }

                    $resource_size = $format_resource_filesize($resource);
                    ?>
                    <a
                        class="content-columns__resource-item"
                        role="listitem"
                        href="<?php echo esc_url($resource['url']); ?>"
                        target="_blank"
                    >
                        <span class="content-columns__resource-icon" aria-hidden="true">
                            <img
                                class="content-columns__resource-icon-img"
                                src="<?php echo esc_url($content_columns_pdf_icon); ?>"
                                alt=""
                                width="48"
                                height="50"
                                loading="lazy"
                                decoding="async"
                            />
                        </span>

                        <?php if ($resource_title || $resource_size) : ?>
                            <span class="content-columns__resource-copy">
                                <?php if ($resource_title) : ?>
                                    <span class="content-columns__resource-title"><?php echo esc_html($resource_title); ?></span>
                                <?php endif; ?>

                                <?php if ($resource_size) : ?>
                                    <span class="content-columns__resource-size"><?php echo esc_html($resource_size); ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($has_icons) : ?>
            <div class="content-columns__icons" role="list">
                <?php foreach ($icons as $row) :
                    if (!is_array($row)) {
                        continue;
                    }

                    $icon_title   = $row['icon_title'] ?? '';
                    $icon_content = $row['icon_content'] ?? '';
                    $icon         = $row['icon'] ?? null;

                    if (!$icon_title && !$icon_content && !$render_column_image($icon, 'content-columns__icon-img', $is_preview)) {
                        continue;
                    }
                    ?>
                    <article class="content-columns__icon-item" role="listitem">
                        <?php
                        $icon_markup = $render_column_image($icon, 'content-columns__icon-img', $is_preview);
                        if ($icon_markup) :
                            ?>
                            <div class="content-columns__icon-graphic" aria-hidden="true">
                                <?php echo $icon_markup; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($icon_title || $icon_content) : ?>
                            <div class="content-columns__icon-copy">
                                <?php if ($icon_title) : ?>
                                    <h4 class="content-columns__icon-title"><?php echo esc_html($icon_title); ?></h4>
                                <?php endif; ?>

                                <?php if ($icon_content) : ?>
                                    <p class="content-columns__icon-content"><?php echo esc_html($icon_content); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
};
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <?php if ($service_graphic_url !== '') : ?>
        <div class="content-columns__image" aria-hidden="true">
            <img
                class="content-columns__image-img"
                src="<?php echo esc_url($service_graphic_url); ?>"
                alt=""
                loading="<?php echo $is_block_preview ? 'eager' : 'lazy'; ?>"
                decoding="async"
            />
        </div>
    <?php endif; ?>

    <div class="content-columns__inner">
        <?php $render_column($left_column, 'left', $is_block_preview); ?>
        <?php $render_column($right_column, 'right', $is_block_preview); ?>
    </div>
</section>
