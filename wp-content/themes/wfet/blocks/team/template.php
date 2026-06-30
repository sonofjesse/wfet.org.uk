<?php
/**
 * Team Block Template.
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
$id = 'team-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'team';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if (!empty($block['align'])) {
    $className .= ' align' . $block['align'];
}

$margin_top    = get_field('margin_top') ?: 'mt-0';
$margin_bottom = get_field('margin_bottom') ?: 'mb-0';

// Convert margin top and bottom to padding top and bottom where it has mt-x change the class to pt-x
// and where it has mb-x change the class to pb-x
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
    $preview_image_url = get_template_directory_uri() . '/blocks/team/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Team preview" style="width:100%;height:auto;" />';
    return;
}

$team_members = get_field('team');
$background_colour = get_field('background_colour') ?: 'midnight';

$allowed_bg_colours = ['midnight', 'sand'];
if (!in_array($background_colour, $allowed_bg_colours, true)) {
    $background_colour = 'midnight';
}

$className .= ' team--bg-' . sanitize_html_class($background_colour);

$has_members = false;
if (!empty($team_members) && is_array($team_members)) {
    foreach ($team_members as $member) {
        if (
            !empty($member['name'])
            || !empty($member['position'])
            || !empty($member['content'])
            || !empty($member['linkedin'])
            || (is_array($member['image'] ?? null) && !empty($member['image']['url']))
        ) {
            $has_members = true;
            break;
        }
    }
}

if (!$has_members) {
    if (!empty($is_preview)) {
        echo '<div class="' . esc_attr($className) . '"><p>' . esc_html__('Add team members to preview this block.', 'soj-core') . '</p></div>';
    }
    return;
}

$member_count = 0;
foreach ($team_members as $member) {
    if (
        !empty($member['name'])
        || !empty($member['position'])
        || !empty($member['content'])
        || !empty($member['linkedin'])
        || (is_array($member['image'] ?? null) && !empty($member['image']['url']))
    ) {
        $member_count++;
    }
}

if ($member_count > 6) {
    $className .= ' team--cols-4';
} else {
    $className .= ' team--cols-3';
}

$className .= ' team--members-' . (int) $member_count;

$is_block_preview = !empty($is_preview);

/**
 * Render a cropped team member photo via soj_picture.
 *
 * @param array|null $image      ACF image field value.
 * @param string     $alt_text   Preferred alt text (member name).
 * @param bool       $is_preview Whether the block is in editor preview.
 * @return string
 */
$render_team_photo = static function ($image, $alt_text, $is_preview) {
    $image_id  = 0;
    $image_alt = $alt_text;

    if (is_array($image) && !empty($image['ID'])) {
        $image_id = (int) $image['ID'];
        if ($image_alt === '' && !empty($image['alt'])) {
            $image_alt = (string) $image['alt'];
        }
    }

    if ($image_id <= 0) {
        if (!is_array($image) || empty($image['url'])) {
            return '';
        }

        return sprintf(
            '<img class="team__photo-img" src="%s" alt="%s" width="404" height="404" loading="%s" decoding="async" />',
            esc_url((string) $image['url']),
            esc_attr($image_alt),
            $is_preview ? 'eager' : 'lazy'
        );
    }

    if ($image_alt === '') {
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '';
    }

    $mime_type = get_post_mime_type($image_id);
    if ($mime_type === 'image/svg+xml') {
        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            return '';
        }

        return sprintf(
            '<img class="team__photo-img" src="%s" alt="%s" width="404" height="404" loading="%s" decoding="async" />',
            esc_url($image_url),
            esc_attr($image_alt),
            $is_preview ? 'eager' : 'lazy'
        );
    }

    return soj_picture($image_id, [
        0 => [404, 404, true],
    ], [
        'img_class'             => 'team__photo-img',
        'alt'                   => $image_alt,
        'use_width_descriptors' => true,
        'sizes'                 => '(min-width: 992px) 404px, (min-width: 768px) 45vw, 90vw',
        'loading'               => $is_preview ? 'eager' : 'lazy',
        'decoding'              => 'async',
        'fetchpriority'         => $is_preview ? '' : 'low',
        'retina'                => true,
        'defer_browser_load'    => !$is_preview,
        'img_attributes'        => [
            'width'  => 404,
            'height' => 404,
        ],
    ]);
};

if ($is_block_preview) {
    $className .= ' team--editor-preview';
}

$modal_id = $id . '-modal';
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(trim($className)); ?>">
    <div class="team__inner">
        <div class="team__grid">
            <?php foreach ($team_members as $index => $member) :
                $member_image    = $member['image'] ?? null;
                $member_name     = $member['name'] ?? '';
                $member_position = $member['position'] ?? '';
                $member_area     = $member['area'] ?? '';
                $member_content  = $member['content'] ?? '';
                $member_linkedin = $member['linkedin'] ?? '';
                $member_area_label = $member_area && function_exists('soj_get_area_label')
                    ? soj_get_area_label($member_area)
                    : '';

                if (
                    !$member_name
                    && !$member_position
                    && !$member_content
                    && !$member_linkedin
                    && !(is_array($member_image) && !empty($member_image['url']))
                ) {
                    continue;
                }

                $member_key   = $id . '-member-' . (int) $index;
                $has_modal    = $member_content && !$is_block_preview;
                $photo_markup = $render_team_photo($member_image, $member_name, $is_block_preview);
                ?>
                <article class="team__member<?php echo $has_modal ? ' team__member--has-modal' : ''; ?>">
                    <?php if ($photo_markup !== '') : ?>
                        <?php if ($has_modal) : ?>
                            <button
                                type="button"
                                class="team__photo-trigger"
                                data-team-modal-trigger
                                aria-controls="<?php echo esc_attr($modal_id); ?>"
                                aria-haspopup="dialog"
                                data-member-name="<?php echo esc_attr($member_name); ?>"
                                data-member-content-id="<?php echo esc_attr($member_key); ?>"
                                aria-label="<?php echo esc_attr(sprintf(/* translators: %s: team member name */ __('Learn more about %s', 'soj-core'), $member_name ?: __('team member', 'soj-core'))); ?>"
                            >
                                <span class="team__photo">
                                    <?php echo $photo_markup; ?>
                                </span>
                            </button>
                        <?php else : ?>
                            <div class="team__photo">
                                <?php echo $photo_markup; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($member_area_label || $member_name || $member_position || $member_linkedin) : ?>
                        <div class="team__details">
                            <?php if ($member_area_label) :
                                $member_area_colour = function_exists('soj_get_area_colour')
                                    ? soj_get_area_colour($member_area)
                                    : 'moss-dark';
                                $member_area_class  = 'news-card__category news-card__category--' . sanitize_html_class($member_area_colour);
                                $member_area_term   = get_category_by_slug((string) $member_area);
                                $member_area_link   = ($member_area_term instanceof WP_Term)
                                    ? get_term_link($member_area_term, 'category')
                                    : '';
                                ?>
                                <div class="news-card__categories team__area">
                                    <?php if (!$is_block_preview && $member_area_link && !is_wp_error($member_area_link)) : ?>
                                        <a
                                            class="<?php echo esc_attr($member_area_class); ?>"
                                            href="<?php echo esc_url($member_area_link); ?>"
                                        >
                                            <?php echo esc_html($member_area_label); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="<?php echo esc_attr($member_area_class); ?>">
                                            <?php echo esc_html($member_area_label); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($member_name || $member_linkedin) : ?>
                                <div class="team__name-row">
                                    <?php if ($member_name) : ?>
                                        <?php if ($has_modal) : ?>
                                            <button
                                                type="button"
                                                class="team__name team__name--trigger"
                                                data-team-modal-trigger
                                                aria-controls="<?php echo esc_attr($modal_id); ?>"
                                                aria-haspopup="dialog"
                                                data-member-name="<?php echo esc_attr($member_name); ?>"
                                                data-member-content-id="<?php echo esc_attr($member_key); ?>"
                                            >
                                                <?php echo esc_html($member_name); ?>
                                            </button>
                                        <?php else : ?>
                                            <h3 class="team__name"><?php echo esc_html($member_name); ?></h3>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($member_linkedin) : ?>
                                        <a
                                            class="team__linkedin"
                                            href="<?php echo esc_url($member_linkedin); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="<?php echo esc_attr(sprintf(/* translators: %s: team member name */ __('LinkedIn profile for %s', 'soj-core'), $member_name ?: __('team member', 'soj-core'))); ?>"
                                        >
                                            <svg class="team__linkedin-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($member_position) : ?>
                                <p class="team__position"><?php echo esc_html($member_position); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($member_content) : ?>
                        <?php if ($is_block_preview) : ?>
                            <span class="team__learn-more team__learn-more--static">
                                <span class="team__learn-more-text"><?php esc_html_e('Learn more', 'soj-core'); ?></span>
                                <span class="team__learn-more-arrow" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1 8H14.5M14.5 8L8.5 2M14.5 8L8.5 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </span>
                        <?php else : ?>
                            <button
                                type="button"
                                class="team__learn-more"
                                data-team-modal-trigger
                                aria-controls="<?php echo esc_attr($modal_id); ?>"
                                aria-haspopup="dialog"
                                data-member-name="<?php echo esc_attr($member_name); ?>"
                                data-member-content-id="<?php echo esc_attr($member_key); ?>"
                            >
                                <span class="team__learn-more-text"><?php esc_html_e('Learn more', 'soj-core'); ?></span>
                                <span class="team__learn-more-arrow" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1 8H14.5M14.5 8L8.5 2M14.5 8L8.5 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </button>

                            <template id="<?php echo esc_attr($member_key); ?>">
                                <?php echo wp_kses_post($member_content); ?>
                            </template>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$is_block_preview) : ?>
    <div
        id="<?php echo esc_attr($modal_id); ?>"
        class="team__modal"
        hidden
        aria-hidden="true"
    >
        <div class="team__modal-overlay" data-team-modal-close tabindex="-1"></div>
        <div
            class="team__modal-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="<?php echo esc_attr($modal_id); ?>-title"
        >
            <button
                type="button"
                class="team__modal-close"
                data-team-modal-close
                aria-label="<?php esc_attr_e('Close dialog', 'soj-core'); ?>"
            >
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                    <path d="M4 4L16 16M16 4L4 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>
            <h2 id="<?php echo esc_attr($modal_id); ?>-title" class="team__modal-title"></h2>
            <div class="team__modal-body"></div>
        </div>
    </div>
    <?php endif; ?>
</section>
