<?php
/**
 * News category badge colour mapping.
 *
 * @package SOJ_Core_Modern
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Map a category to its news card badge colour slug.
 *
 * Checks the category slug and its ancestors so child categories inherit
 * parent colours (e.g. posts under Homes use rose-dark).
 *
 * @param WP_Term|null $category Category term.
 * @return string One of: rose-dark, moss-dark, sky-dark.
 */
function soj_get_news_category_colour($category)
{
    $allowed_colours = ['rose-dark', 'moss-dark', 'sky-dark'];
    $colour_map      = [
        'homes'                         => 'rose-dark',
        'nature-based-neighbourhoods'   => 'moss-dark',
        'inclusive-education'           => 'sky-dark',
    ];

    if (!$category instanceof WP_Term || $category->taxonomy !== 'category') {
        return 'moss-dark';
    }

    $slugs_to_check = [(string) $category->slug];
    $ancestor_ids   = get_ancestors((int) $category->term_id, 'category', 'taxonomy');

    foreach ($ancestor_ids as $ancestor_id) {
        $ancestor = get_term((int) $ancestor_id, 'category');

        if ($ancestor instanceof WP_Term) {
            $slugs_to_check[] = (string) $ancestor->slug;
        }
    }

    foreach ($slugs_to_check as $slug) {
        if (isset($colour_map[$slug])) {
            $colour = $colour_map[$slug];

            return in_array($colour, $allowed_colours, true) ? $colour : 'moss-dark';
        }
    }

    return 'moss-dark';
}

/**
 * Area slug to badge colour map (matches news category slugs).
 *
 * @return array<string, string>
 */
function soj_get_area_colour_map(): array
{
    return [
        'homes'                         => 'rose-dark',
        'nature-based-neighbourhoods'   => 'moss-dark',
        'inclusive-education'           => 'sky-dark',
    ];
}

/**
 * Area slug to display label map (fallback when the category term is missing).
 *
 * @return array<string, string>
 */
function soj_get_area_label_map(): array
{
    return [
        'homes'                         => 'Homes',
        'nature-based-neighbourhoods'   => 'Nature-based Neighbourhoods',
        'inclusive-education'           => 'Inclusive Education',
    ];
}

/**
 * Map an area slug to its badge colour slug.
 *
 * @param string $area_slug Area slug from ACF select.
 * @return string One of: rose-dark, moss-dark, sky-dark.
 */
function soj_get_area_colour($area_slug)
{
    $allowed_colours = ['rose-dark', 'moss-dark', 'sky-dark'];
    $area_slug       = sanitize_title((string) $area_slug);

    if ($area_slug === '') {
        return 'moss-dark';
    }

    $category = get_category_by_slug($area_slug);

    if ($category instanceof WP_Term) {
        return soj_get_news_category_colour($category);
    }

    $colour = soj_get_area_colour_map()[$area_slug] ?? 'moss-dark';

    return in_array($colour, $allowed_colours, true) ? $colour : 'moss-dark';
}

/**
 * Get the display label for an area slug.
 *
 * @param string $area_slug Area slug from ACF select.
 * @return string
 */
function soj_get_area_label($area_slug)
{
    $area_slug = sanitize_title((string) $area_slug);

    if ($area_slug === '') {
        return '';
    }

    $category = get_category_by_slug($area_slug);

    if ($category instanceof WP_Term) {
        return $category->name;
    }

    return soj_get_area_label_map()[$area_slug] ?? '';
}

/**
 * Category slugs shown in the All News filter bar (display order).
 *
 * @return string[]
 */
function soj_get_news_filter_category_slugs(): array
{
    return [
        'homes',
        'inclusive-education',
        'nature-based-neighbourhoods',
    ];
}

/**
 * Top-level news categories for the link-based filter navigation.
 *
 * @return WP_Term[]
 */
function soj_get_news_filter_categories(): array
{
    $terms = [];

    foreach (soj_get_news_filter_category_slugs() as $slug) {
        $term = get_category_by_slug($slug);

        if ($term instanceof WP_Term) {
            $terms[] = $term;
        }
    }

    return $terms;
}
