<?php

/**
 * News category filter navigation.
 *
 * Used by the All News block (AJAX buttons) and category archives (links).
 *
 * @package SOJ_Core_Modern
 *
 * @param array $args {
 *     Arguments passed via get_template_part() third parameter.
 *
 *     @type WP_Term[] $categories          Optional. Category terms to list; defaults to soj_get_news_filter_categories().
 *     @type int       $current_category_id Optional. Term ID for the active category.
 *     @type bool      $ajax                When true, render filter buttons for AJAX filtering.
 *     @type string    $aria_label          Optional. Nav aria-label; defaults to translatable string.
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$defaults = [
    'categories'          => function_exists('soj_get_news_filter_categories')
        ? soj_get_news_filter_categories()
        : [],
    'current_category_id' => 0,
    'ajax'                => false,
    'aria_label'          => '',
];

$nav = wp_parse_args(isset($args) && is_array($args) ? $args : [], $defaults);

if ($nav['aria_label'] === '') {
    $nav['aria_label'] = __('News categories', 'soj-core');
}

$categories = array_values(
    array_filter(
        (array) $nav['categories'],
        static function ($term) {
            return $term instanceof WP_Term;
        }
    )
);

if ($categories === []) {
    return;
}

$current_cat_id = (int) $nav['current_category_id'];
$aria_label     = (string) $nav['aria_label'];
$is_ajax        = !empty($nav['ajax']);
$has_active     = $current_cat_id > 0;
$nav_class      = 'news-filters' . ($has_active ? ' has-active' : '');
?>

<nav
    class="<?php echo esc_attr($nav_class); ?>"
    aria-label="<?php echo esc_attr($aria_label); ?>"
    <?php echo $is_ajax ? ' data-news-filters' : ''; ?>
>
    <p class="news-filters__label"><?php esc_html_e('Filter by', 'soj-core'); ?></p>
    <div class="news-filters__links">
        <?php foreach ($categories as $cat_term) :
            $colour = function_exists('soj_get_news_category_colour')
                ? soj_get_news_category_colour($cat_term)
                : 'moss-dark';
            $is_current_cat = $current_cat_id > 0 && $current_cat_id === (int) $cat_term->term_id;
            $button_class   = 'news-filters__button news-filters__button--' . sanitize_html_class($colour);

            if ($is_current_cat) {
                $button_class .= ' is-active';
            }

            if ($is_ajax) :
                ?>
                <button
                    type="button"
                    class="<?php echo esc_attr($button_class); ?>"
                    data-category-id="<?php echo esc_attr((string) $cat_term->term_id); ?>"
                    <?php echo $is_current_cat ? ' aria-pressed="true"' : ' aria-pressed="false"'; ?>
                >
                    <?php echo esc_html($cat_term->name); ?>
                </button>
            <?php else :
                $category_url = get_category_link($cat_term->term_id);

                if (!$category_url || is_wp_error($category_url)) {
                    continue;
                }
                ?>
                <a
                    href="<?php echo esc_url($category_url); ?>"
                    class="<?php echo esc_attr($button_class); ?>"
                    <?php echo $is_current_cat ? ' aria-current="page"' : ''; ?>
                >
                    <?php echo esc_html($cat_term->name); ?>
                </a>
            <?php endif;
        endforeach;

        if ($is_ajax) :
            ?>
            <button
                type="button"
                class="news-filters__clear"
                data-news-filters-clear
                aria-label="<?php esc_attr_e('Clear filter', 'soj-core'); ?>"
            >
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                    <path d="M2.5 2.5L11.5 11.5M11.5 2.5L2.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>
        <?php endif; ?>
    </div>
</nav>
