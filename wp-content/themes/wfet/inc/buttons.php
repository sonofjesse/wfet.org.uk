<?php

/**
 * Animated button markup (button-016 pattern).
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build rel attribute for external links.
 *
 * @param array<string, mixed> $button ACF link field.
 * @return string Empty or rel="noopener noreferrer".
 */
function soj_button_rel_attribute(array $button): string
{
    $rel = [];

    if (!empty($button['target']) && $button['target'] === '_blank') {
        $rel[] = 'noopener';
        $rel[] = 'noreferrer';
    }

    if (!empty($button['rel'])) {
        $parts = preg_split('/\s+/', trim((string) $button['rel']));
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if ($part !== '') {
                    $rel[] = $part;
                }
            }
        }
    }

    $rel = array_values(array_unique($rel));

    return $rel !== [] ? ' rel="' . esc_attr(implode(' ', $rel)) . '"' : '';
}

/**
 * Render a theme button link.
 *
 * Primary buttons use the slide + background animation (.btn / .btn-primary).
 * Secondary buttons are plain outlined links (.btn-secondary).
 *
 * @param array<string, mixed> $button ACF link field (url, title, optional target).
 * @param array<string, mixed> $args {
 *     @type string $variant  primary|secondary. Default primary.
 *     @type string $style     primary only: midnight for sand-on-midnight (e.g. service CTA).
 *     @type string $class    Extra class names.
 * }
 * @return string HTML or empty string.
 */
function soj_render_button(array $button, array $args = []): string
{
    if (empty($button['url']) || empty($button['title'])) {
        return '';
    }

    $variant = isset($args['variant']) ? (string) $args['variant'] : 'primary';
    $extra_class = isset($args['class']) ? trim((string) $args['class']) : '';

    $classes = ['btn'];
    if ($variant === 'secondary') {
        $classes[] = 'btn-secondary';
    } else {
        $classes[] = 'btn-primary';
        $style = isset($args['style']) ? (string) $args['style'] : '';
        if ($style === 'midnight') {
            $classes[] = 'btn-primary--midnight';
        }
    }
    if ($extra_class !== '') {
        $classes[] = $extra_class;
    }

    $href = esc_url((string) $button['url']);
    $title = esc_html((string) $button['title']);
    $class_attr = esc_attr(implode(' ', $classes));
    $target_attr = !empty($button['target'])
        ? ' target="' . esc_attr((string) $button['target']) . '"'
        : '';
    $rel_attr = soj_button_rel_attribute($button);

    if ($variant === 'secondary') {
        return sprintf(
            '<a href="%1$s" class="%2$s"%3$s%4$s>%5$s</a>',
            $href,
            $class_attr,
            $target_attr,
            $rel_attr,
            $title
        );
    }

    return sprintf(
        '<a href="%1$s" class="%2$s" data-button=""%3$s%4$s><span class="btn__inner"><span class="btn__text">%5$s</span></span><span class="btn__bg"></span></a>',
        $href,
        $class_attr,
        $target_attr,
        $rel_attr,
        $title
    );
}

/**
 * Echo soj_render_button().
 *
 * @param array<string, mixed> $button ACF link field.
 * @param array<string, mixed> $args     Optional arguments.
 */
function soj_the_button(array $button, array $args = []): void
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in soj_render_button().
    echo soj_render_button($button, $args);
}

/**
 * Whether a core/button block uses the outline style.
 *
 * @param array<string, mixed> $block         Block data.
 * @param string               $block_content Rendered HTML.
 * @return bool
 */
function soj_core_button_is_outline(array $block, string $block_content): bool
{
    $class_name = isset($block['attrs']['className']) ? (string) $block['attrs']['className'] : '';

    return str_contains($class_name, 'is-style-outline')
        || str_contains($block_content, 'is-style-outline');
}

/**
 * Parse core/button fields from block attrs or saved HTML.
 *
 * @param array<string, mixed> $block         Block data.
 * @param string               $block_content Rendered HTML.
 * @return array{url: string, title: string, target: string, rel: string}
 */
function soj_parse_core_button_fields(array $block, string $block_content): array
{
    $attrs = $block['attrs'] ?? [];

    $url = isset($attrs['url']) ? (string) $attrs['url'] : '';
    $title = isset($attrs['text']) ? (string) $attrs['text'] : '';
    $target = !empty($attrs['linkTarget']) && $attrs['linkTarget'] === '_blank' ? '_blank' : '';
    $rel = isset($attrs['rel']) ? (string) $attrs['rel'] : '';

    if ($url === '' && preg_match('/\bhref=(["\'])([^"\']+)\1/i', $block_content, $matches)) {
        $url = html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');
    }

    if ($title === '' && preg_match('/wp-block-button__link[^>]*>(.*?)<\/a>/is', $block_content, $matches)) {
        $title = trim(wp_strip_all_tags($matches[1]));
    }

    return [
        'url' => $url,
        'title' => $title,
        'target' => $target,
        'rel' => $rel,
    ];
}

/**
 * Replace core/button link markup with the theme animated button.
 *
 * @param string               $block_content Block HTML.
 * @param array<string, mixed> $block         Block data.
 * @return string
 */
function soj_render_core_button_block(string $block_content, array $block, $instance = null): string
{
    unset($instance);

    if (($block['blockName'] ?? '') !== 'core/button' || $block_content === '') {
        return $block_content;
    }

    $fields = soj_parse_core_button_fields($block, $block_content);
    if ($fields['url'] === '' || $fields['title'] === '') {
        return $block_content;
    }

    $is_outline = soj_core_button_is_outline($block, $block_content);

    $animated_link = soj_render_button(
        [
            'url' => $fields['url'],
            'title' => $fields['title'],
            'target' => $fields['target'],
            'rel' => $fields['rel'],
        ],
        [
            'variant' => $is_outline ? 'secondary' : 'primary',
            'class' => 'wp-block-button__link wp-element-button',
        ]
    );

    if ($animated_link === '') {
        return $block_content;
    }

    $replaced = preg_replace(
        '/<a\s[^>]*\bwp-block-button__link\b[^>]*>.*?<\/a>/is',
        $animated_link,
        $block_content,
        1
    );

    return is_string($replaced) ? $replaced : $block_content;
}

add_filter('render_block', 'soj_render_core_button_block', 10, 2);
