<?php

/**
 * All Insights Block - Setup
 * Block registration is via block.json (ACF Block API v3)
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Posts per page for the All Insights listing (Settings → Reading → “Blog pages show at most”).
 */
function soj_all_insights_posts_per_page(): int
{
    $per_page = (int) get_option('posts_per_page', 10);

    return max(1, $per_page);
}

/**
 * Current pagination page for a static page hosting the All Insights block.
 */
function soj_all_insights_current_page(): int
{
    $paged = (int) get_query_var('paged');

    if ($paged < 1) {
        $paged = (int) get_query_var('page');
    }

    return max(1, $paged);
}

/**
 * Default WP_Query args for all published insights, newest first.
 *
 * @param int $paged Page number (0 = resolve from the request).
 * @return array<string, mixed>
 */
function soj_all_insights_query_args(int $paged = 0, int $category_id = 0): array
{
    if ($paged < 1) {
        $paged = soj_all_insights_current_page();
    }

    $args = [
        'post_type'           => 'insight',
        'post_status'         => 'publish',
        'posts_per_page'      => soj_all_insights_posts_per_page(),
        'paged'               => $paged,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
    ];

    if ($category_id > 0) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'insights-category',
                'field'    => 'term_id',
                'terms'    => $category_id,
            ],
        ];
    }

    return $args;
}

/**
 * Render the All Insights results markup for the block or AJAX responses.
 */
function soj_all_insights_render_results_html(
    WP_Query $query,
    int $paged,
    int $context_post_id,
    bool $show_category,
    bool $is_preview = false,
    bool $eager_images = false
): string {
    ob_start();

    get_template_part(
        'template-parts/all-insights/results',
        null,
        [
            'query'           => $query,
            'paged'           => $paged,
            'context_post_id' => $context_post_id,
            'show_category'   => $show_category,
            'is_preview'      => $is_preview,
            'eager_images'    => $eager_images,
        ]
    );

    $html = ob_get_clean();

    return is_string($html) ? $html : '';
}

/**
 * AJAX handler for filtering All Insights posts by category.
 */
function soj_all_insights_filter_ajax(): void
{
    check_ajax_referer('soj_theme_nonce', 'nonce');

    $category_id     = isset($_POST['categoryId']) ? (int) $_POST['categoryId'] : 0;
    $paged           = isset($_POST['paged']) ? max(1, (int) $_POST['paged']) : 1;
    $context_post_id = isset($_POST['contextPostId']) ? (int) $_POST['contextPostId'] : 0;
    $show_category   = !empty($_POST['showCategory']);

    if ($category_id > 0) {
        $term = get_term($category_id, 'insights-category');

        if (!$term instanceof WP_Term) {
            wp_send_json_error(['message' => __('Invalid category.', 'soj-core')], 400);
        }
    }

    $query = new WP_Query(soj_all_insights_query_args($paged, $category_id));

    wp_send_json_success(
        [
            'html' => soj_all_insights_render_results_html(
                $query,
                $paged,
                $context_post_id,
                $show_category,
                false,
                true
            ),
        ]
    );
}

add_action('wp_ajax_soj_filter_all_insights', 'soj_all_insights_filter_ajax');
add_action('wp_ajax_nopriv_soj_filter_all_insights', 'soj_all_insights_filter_ajax');

/**
 * Pagination markup for the All Insights block on a static page.
 */
function soj_all_insights_pagination_html(WP_Query $query, int $current_page, int $context_post_id): string
{
    $total_pages = (int) $query->max_num_pages;

    if ($total_pages < 2) {
        return '';
    }

    $permalink = $context_post_id > 0 ? get_permalink($context_post_id) : '';

    if (!$permalink) {
        $permalink = get_pagenum_link(1, false);
    }

    if (!$permalink) {
        return '';
    }

    global $wp_rewrite;

    $permalink = trailingslashit((string) $permalink);

    if ($wp_rewrite->using_permalinks()) {
        $base   = $permalink . '%_%';
        $format = user_trailingslashit('page/%#%', '');
    } else {
        $base   = $permalink . '%_%';
        $format = '?page=%#%';
    }

    $links = paginate_links(
        [
            'base'      => $base,
            'format'    => $format,
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_next' => false,
            'mid_size'  => 2,
            'type'      => 'list',
        ]
    );

    return is_string($links) ? $links : '';
}

/**
 * Enqueue news block styles on single insight pages for the recent insights section.
 */
function soj_enqueue_single_insight_recent_styles(): void
{
    if (!is_singular('insight')) {
        return;
    }

    $css_file = SOJ_THEME_DIR . '/blocks/news/css/style.css';

    if (!file_exists($css_file)) {
        return;
    }

    wp_enqueue_style(
        'soj-news-block',
        SOJ_THEME_URI . '/blocks/news/css/style.css',
        [],
        soj_get_asset_version($css_file)
    );
}

add_action('wp_enqueue_scripts', 'soj_enqueue_single_insight_recent_styles');
