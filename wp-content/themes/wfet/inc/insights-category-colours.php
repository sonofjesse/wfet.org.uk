<?php
/**
 * Insights category badge colour mapping.
 *
 * @package SOJ_Core_Modern
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Map an insights category to its card badge colour slug.
 *
 * Checks the category slug and its ancestors so child categories inherit
 * parent colours.
 *
 * @param WP_Term|null $category Category term.
 * @return string One of: rose-dark, moss-dark, sky-dark.
 */
function soj_get_insights_category_colour($category)
{
    $allowed_colours = ['rose-dark', 'moss-dark', 'sky-dark'];
    $colour_map      = [
        'homes'                         => 'rose-dark',
        'nature-based-neighbourhoods'   => 'moss-dark',
        'inclusive-education'           => 'sky-dark',
    ];

    if (!$category instanceof WP_Term || $category->taxonomy !== 'insights-category') {
        return 'moss-dark';
    }

    $slugs_to_check = [(string) $category->slug];
    $ancestor_ids   = get_ancestors((int) $category->term_id, 'insights-category', 'taxonomy');

    foreach ($ancestor_ids as $ancestor_id) {
        $ancestor = get_term((int) $ancestor_id, 'insights-category');

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
 * Category slugs shown in the All Insights filter bar (display order).
 *
 * @return string[]
 */
function soj_get_insights_filter_category_slugs(): array
{
    return [
        'homes',
        'inclusive-education',
        'nature-based-neighbourhoods',
    ];
}

/**
 * Top-level insights categories for the link-based filter navigation.
 *
 * @return WP_Term[]
 */
function soj_get_insights_filter_categories(): array
{
    $terms = [];

    foreach (soj_get_insights_filter_category_slugs() as $slug) {
        $term = get_term_by('slug', $slug, 'insights-category');

        if ($term instanceof WP_Term) {
            $terms[] = $term;
        }
    }

    if ($terms !== []) {
        return $terms;
    }

    $fallback_terms = get_terms(
        [
            'taxonomy'   => 'insights-category',
            'hide_empty' => false,
            'parent'     => 0,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]
    );

    if (is_wp_error($fallback_terms) || !is_array($fallback_terms)) {
        return [];
    }

    return array_values(
        array_filter(
            $fallback_terms,
            static function ($term) {
                return $term instanceof WP_Term;
            }
        )
    );
}
