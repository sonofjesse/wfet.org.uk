<?php

/**
 * Shared resources category filter navigation (links).
 *
 * Used by the resources block and category archives.
 *
 * @package SOJ_Core_Modern
 *
 * @param array $args {
 *     Arguments passed via get_template_part() third parameter.
 *
 *     @type string   $all_url                 Required. URL for the "All" link.
 *     @type bool     $all_is_current          Whether "All" is the current view.
 *     @type WP_Term[] $categories             Category terms to list.
 *     @type int      $current_category_id     Optional. Term ID for the active category link.
 *     @type string   $resources_cpt_url       Optional. URL for "Resources" (CPT-only) view on the hub.
 *     @type bool     $resources_cpt_is_current Optional. Whether the CPT-only view is active.
 *     @type string   $aria_label              Optional. Nav aria-label; defaults to translatable string.
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$defaults = array(
    'all_url'                  => '',
    'all_is_current'           => false,
    'categories'               => array(),
    'current_category_id'      => 0,
    'resources_cpt_url'        => '',
    'resources_cpt_is_current' => false,
    'aria_label'               => '',
);

$nav = wp_parse_args(isset($args) && is_array($args) ? $args : array(), $defaults);

if ($nav['aria_label'] === '') {
    $nav['aria_label'] = __('Resource categories', 'soj-core');
}

$categories = array_values(
    array_filter(
        (array) $nav['categories'],
        static function ($term) {
            return $term instanceof WP_Term;
        }
    )
);

$resources_cpt_url = (string) $nav['resources_cpt_url'];
$show_resources_cpt = $resources_cpt_url !== '';

if ($nav['all_url'] === '' || ($categories === array() && !$show_resources_cpt)) {
    return;
}

$current_cat_id            = (int) $nav['current_category_id'];
$all_is_current            = (bool) $nav['all_is_current'];
$all_url                   = $nav['all_url'];
$aria_label                = (string) $nav['aria_label'];
$resources_cpt_is_current    = (bool) $nav['resources_cpt_is_current'];
$case_studies_slug         = function_exists('soj_resources_case_studies_category_slug')
    ? soj_resources_case_studies_category_slug()
    : 'case-studies';
?>

<nav class="resources__filters" aria-label="<?php echo esc_attr($aria_label); ?>">
    <h2 class="resources-filter-heading">
        <a href="<?php echo esc_url($all_url); ?>" class="resources-filter-button<?php echo $all_is_current ? ' is-active' : ''; ?>"<?php echo $all_is_current ? ' aria-current="page"' : ''; ?>>
            <?php esc_html_e('All', 'soj-core'); ?>
        </a>
    </h2>
    <?php
    $guides_link_emitted = false;
    foreach ($categories as $cat_term) :
        $category_url = get_category_link($cat_term->term_id);
        $is_current_cat = $current_cat_id > 0 && $current_cat_id === (int) $cat_term->term_id;
        ?>
        <h2 class="resources-filter-heading">
            <a href="<?php echo esc_url($category_url); ?>" class="resources-filter-button<?php echo $is_current_cat ? ' is-active' : ''; ?>"<?php echo $is_current_cat ? ' aria-current="page"' : ''; ?>>
                <?php echo esc_html($cat_term->name); ?>
            </a>
        </h2>
        <?php
        if ($show_resources_cpt && !$guides_link_emitted && $cat_term->slug === $case_studies_slug) :
            $guides_link_emitted = true;
            ?>
    <h2 class="resources-filter-heading">
        <a href="<?php echo esc_url($resources_cpt_url); ?>" class="resources-filter-button<?php echo $resources_cpt_is_current ? ' is-active' : ''; ?>"<?php echo $resources_cpt_is_current ? ' aria-current="page"' : ''; ?>>
            <?php esc_html_e('Guides', 'soj-core'); ?>
        </a>
    </h2>
            <?php
        endif;
    endforeach;
    ?>
    <?php if ($show_resources_cpt && !$guides_link_emitted) : ?>
    <h2 class="resources-filter-heading">
        <a href="<?php echo esc_url($resources_cpt_url); ?>" class="resources-filter-button<?php echo $resources_cpt_is_current ? ' is-active' : ''; ?>"<?php echo $resources_cpt_is_current ? ' aria-current="page"' : ''; ?>>
            <?php esc_html_e('Guides', 'soj-core'); ?>
        </a>
    </h2>
    <?php endif; ?>
</nav>
