<?php

/**
 * Author profile section for single insight pages.
 *
 * Shown when the insight has a linked author (insight_author ACF field).
 *
 * @package SOJ_Core_Modern
 */

if (!defined('ABSPATH')) {
    exit;
}

$author = get_field('insight_author');
if (empty($author)) {
    return;
}

$user_id = 0;
if (is_array($author)) {
    $user_id = (int) ($author['ID'] ?? 0);
} elseif (is_object($author)) {
    $user_id = (int) ($author->ID ?? 0);
} else {
    $user_id = (int) $author;
}

if ($user_id <= 0) {
    return;
}

$user = get_userdata($user_id);
if (!$user instanceof WP_User) {
    return;
}

$member_name = $user->display_name;
if ($member_name === '') {
    return;
}

$member_position = (string) get_field('position', 'user_' . $user_id);
$profile_image   = get_field('profile_image', 'user_' . $user_id);
$image_id        = 0;

if (is_array($profile_image) && !empty($profile_image['ID'])) {
    $image_id = (int) $profile_image['ID'];
}

$insights_url = trailingslashit(home_url('/insights'));

$photo_markup = '';
if ($image_id > 0) {
    $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $member_name;
    $mime_type = get_post_mime_type($image_id);

    if ($mime_type === 'image/svg+xml') {
        $image_url = wp_get_attachment_url($image_id);
        if ($image_url) {
            $photo_markup = sprintf(
                '<img class="insight-team-profile__photo-img" src="%s" alt="%s" width="280" height="280" loading="lazy" decoding="async" />',
                esc_url($image_url),
                esc_attr($image_alt)
            );
        }
    } else {
        $photo_markup = soj_picture($image_id, [
            0   => [280, 280, true],
            768 => [280, 280, true],
        ], [
            'img_class'             => 'insight-team-profile__photo-img',
            'alt'                   => $image_alt,
            'use_width_descriptors' => true,
            'sizes'                 => '(min-width: 768px) 280px, 220px',
            'loading'               => 'lazy',
            'decoding'              => 'async',
            'fetchpriority'         => 'low',
            'retina'                => true,
            'img_attributes'        => [
                'width'  => 280,
                'height' => 280,
            ],
        ]);
    }
}
?>

<section class="insight-team-profile">
    <div class="insight-team-profile__inner">
        <div class="insight-team-profile__layout">
            <?php if ($photo_markup !== '') : ?>
                <div class="insight-team-profile__media">
                    <div class="insight-team-profile__photo">
                        <?php echo $photo_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="insight-team-profile__content">
                <p class="insight-team-profile__title">
                    <?php
                    printf(
                        /* translators: %s: author name */
                        esc_html__('Article written by %s', 'soj-core'),
                        esc_html($member_name)
                    );
                    ?>
                </p>

                <?php if ($member_position !== '') : ?>
                    <p class="insight-team-profile__position"><?php echo esc_html($member_position); ?></p>
                <?php endif; ?>

                <div class="insight-team-profile__actions">
                    <?php
                    soj_the_button(
                        [
                            'url'   => $insights_url,
                            'title' => __('Back to Insights', 'soj-core'),
                        ],
                        [
                            'class' => 'insight-team-profile__button',
                        ]
                    );
                    ?>
                </div>
            </div>
        </div>
    </div>
</section>
