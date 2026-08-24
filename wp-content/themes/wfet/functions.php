<?php

/**
 * SOJ Core Modern Theme Functions
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Define theme constants.
define('SOJ_THEME_VERSION', '2.0.0');
define('SOJ_THEME_DIR', get_template_directory());
define('SOJ_THEME_URI', get_template_directory_uri());



/**
 * Environment detection
 */
function soj_is_development()
{
    return defined('WP_DEBUG') && WP_DEBUG;
}

function soj_is_production()
{
    return !soj_is_development();
}

/**
 * Get asset version for cache busting
 */
function soj_get_asset_version($file_path)
{
    if (soj_is_development()) {
        return file_exists($file_path) ? filemtime($file_path) : SOJ_THEME_VERSION;
    }
    return SOJ_THEME_VERSION;
}

/**
 * Resolve the newest compiled main stylesheet on disk.
 *
 * Dev (webpack watch) writes dist/css/main.min.css; production writes main.min.[hash].css.
 * Prefer whichever file was modified most recently so dev changes are not masked by stale hashes.
 *
 * @return string Absolute path to main CSS, or empty string if missing.
 */
function soj_get_main_css_path(): string
{
    $candidates = glob(SOJ_THEME_DIR . '/dist/css/main.min.*.css') ?: [];
    $unhashed = SOJ_THEME_DIR . '/dist/css/main.min.css';
    if (file_exists($unhashed)) {
        $candidates[] = $unhashed;
    }

    $candidates = array_values(array_filter($candidates, 'file_exists'));
    if ($candidates === []) {
        return '';
    }

    usort($candidates, static fn($a, $b) => filemtime($b) <=> filemtime($a));

    return $candidates[0];
}

/**
 * Public URI for the newest main stylesheet.
 *
 * @return string
 */
function soj_get_main_css_uri(): string
{
    $path = soj_get_main_css_path();
    if ($path === '') {
        return SOJ_THEME_URI . '/dist/css/main.min.css';
    }

    return SOJ_THEME_URI . '/dist/css/' . basename($path);
}

/**
 * Whether a footer href points at the current page (same path as this request).
 *
 * @param string $url Full or relative URL.
 * @return bool
 */
function soj_footer_link_is_current($url)
{
    if ($url === '' || !is_string($url)) {
        return false;
    }

    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
    if (is_string($site_host) && $site_host !== '') {
        $link_host = wp_parse_url($url, PHP_URL_HOST);
        if (is_string($link_host) && $link_host !== '' && strtolower($link_host) !== strtolower($site_host)) {
            return false;
        }
    }

    $normalize_path = static function ($u) {
        $parts = wp_parse_url($u);
        $path = isset($parts['path']) ? (string) $parts['path'] : '/';
        $path = urldecode($path);
        $path = '/' . ltrim($path, '/');
        $path = untrailingslashit($path);

        return $path === '' ? '/' : $path;
    };

    $item_path = $normalize_path($url);
    if ($item_path === '') {
        return false;
    }

    $current_paths = [];
    $queried_id = (int) get_queried_object_id();
    if ($queried_id > 0) {
        $permalink = (string) get_permalink($queried_id);
        if ($permalink !== '') {
            $current_paths[] = $normalize_path($permalink);
        }
    }
    if (isset($GLOBALS['wp']) && is_object($GLOBALS['wp']) && isset($GLOBALS['wp']->request)) {
        $request_url = (string) home_url(add_query_arg([], $GLOBALS['wp']->request));
        $current_paths[] = $normalize_path($request_url);
    }
    $current_paths = array_values(array_unique(array_filter($current_paths)));

    return $item_path !== '' && in_array($item_path, $current_paths, true);
}

/**
 * Theme Setup
 */
function soj_theme_setup()
{
    // Load theme text domain for translations
    load_theme_textdomain('soj-core', get_template_directory() . '/languages');

    // Add theme support.
    add_theme_support('post-thumbnails');

    // Mega menu service tiles (matches ~261:206 aspect in header nav CSS).
    add_image_size('mega-menu-service', 522, 412, true);

    add_theme_support('editor-styles');
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('custom-logo');
    add_theme_support('title-tag');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script'
    ]);

    // Register navigation menus.
    register_nav_menus([
        'primary' => __('Primary Menu', 'soj-core'),
        'footer' => __('Footer Menu', 'soj-core'),
        'mobile' => __('Mobile Menu', 'soj-core')
    ]);

    // Disable some WordPress core image sizes to reduce file generation.
    remove_image_size('1536x1536');
    remove_image_size('2048x2048');

    // Set maximum image dimensions to prevent oversized images.
    add_filter('big_image_size_threshold', function ($threshold) {
        return 2048; // Maximum width/height for scaled images
    });
}
add_action('after_setup_theme', 'soj_theme_setup');

/**
 * Add editor styles for consistent typography between frontend and admin.
 *
 * Typekit must load via add_editor_style() so it is injected into the block
 * editor iframe — enqueue_block_editor_assets only loads in the parent frame.
 */
function soj_add_editor_styles()
{
    $kit_id = defined('SOJ_TYPEKIT_KIT_ID') ? SOJ_TYPEKIT_KIT_ID : 'ilp3sfh';
    add_editor_style('https://use.typekit.net/' . $kit_id . '.css');

    $main_css_path = soj_get_main_css_path();
    if ($main_css_path !== '') {
        add_editor_style('dist/css/' . basename($main_css_path));
    }
}
add_action('after_setup_theme', 'soj_add_editor_styles', 20);

add_action('enqueue_block_editor_assets', function () {
    // Typekit (Proxima Nova / Proxima Sera) — same kit as the frontend (header.php)
    // so the editor matches the live site.
    wp_enqueue_style(
        'soj-editor-fonts-typekit',
        'https://use.typekit.net/ilp3sfh.css',
        [],
        null
    );
}, 10);

/**
 * Auto-load theme includes
 */
function soj_autoload_includes()
{
    $includes = [
        'inc/buttons.php',
        'inc/tables.php',
        'inc/performance.php',
        'inc/custom-post-types.php',
        'inc/news-category-colours.php',
        'inc/insights-category-colours.php',
        'inc/block-manager.php',
        'inc/security.php',
        'inc/acf-options.php',
        'inc/faq-schema.php',
    ];

    foreach ($includes as $include) {
        $file = SOJ_THEME_DIR . '/' . $include;
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
add_action('after_setup_theme', 'soj_autoload_includes');

/**
 * Register ACF blocks via block.json (ACF 6 recommended method)
 * Uses acf/block-name format (not acf/acf-block-name)
 */
function soj_register_acf_blocks()
{
    if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_CRON') && DOING_CRON)) {
        return;
    }
    $blocks_dir = SOJ_THEME_DIR . '/blocks';
    if (!is_dir($blocks_dir)) {
        return;
    }
    $block_folders = glob($blocks_dir . '/*', GLOB_ONLYDIR);
    foreach ($block_folders ?: [] as $folder) {
        $block_json = $folder . '/block.json';
        if (file_exists($block_json)) {
            register_block_type($folder);
        }
    }
}
add_action('init', 'soj_register_acf_blocks', 5);

/**
 * Load block functions for image sizes and other block-specific setup
 */
function soj_load_block_functions()
{
    if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_CRON') && DOING_CRON)) {
        return;
    }
    $blocks_dir = SOJ_THEME_DIR . '/blocks';
    if (!is_dir($blocks_dir)) {
        return;
    }
    $block_folders = glob($blocks_dir . '/*', GLOB_ONLYDIR);
    foreach ($block_folders ?: [] as $folder) {
        $functions_file = $folder . '/functions.php';
        if (file_exists($functions_file)) {
            require_once $functions_file;
        }
    }
}
add_action('init', 'soj_load_block_functions', 6);





/**
 * Enqueue theme assets
 *
 * CSS on the frontend is handled entirely by the wp_head preload in performance.php
 * (priority 2) to allow fetchpriority="high" and avoid a second stylesheet tag.
 * This function is frontend-only (wp_enqueue_scripts) and handles JS only.
 */
function soj_enqueue_assets()
{
    // Enqueue main JS (now includes all dependencies in one file)
    $main_hashed_files = glob(SOJ_THEME_DIR . '/dist/js/main.min.*.js');
    if (!empty($main_hashed_files)) {
        // Use the newest hashed file (sort by modification time)
        usort($main_hashed_files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $main_js_file = $main_hashed_files[0];
    } else {
        // Fallback to non-hashed file for development
        $main_js_file = SOJ_THEME_DIR . '/dist/js/main.min.js';
    }

    if (file_exists($main_js_file)) {
        $main_js_uri = SOJ_THEME_URI . '/dist/js/' . basename($main_js_file);
        $main_js_version = soj_get_asset_version($main_js_file);
        wp_enqueue_script('soj-main-js', $main_js_uri, [], $main_js_version, true);

        // Localize script with debug status
        wp_localize_script('soj-main-js', 'sojTheme', [
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('soj_theme_nonce')
        ]);
    }
}
add_action('wp_enqueue_scripts', 'soj_enqueue_assets');

/**
 * Enqueue main styles in admin editor for consistent appearance
 */
function soj_enqueue_admin_block_styles()
{
    // Only run in admin and when block editor is active
    if (!is_admin() || !wp_should_load_block_editor_scripts_and_styles()) {
        return;
    }

    $main_css_file = soj_get_main_css_path();
    if ($main_css_file !== '') {
        $main_css_uri = soj_get_main_css_uri();
        $main_css_version = soj_get_asset_version($main_css_file);

        // Enqueue main styles for unified frontend/admin appearance
        wp_enqueue_style('soj-admin-block-styles', $main_css_uri, ['wp-edit-blocks'], $main_css_version);
    }
}
add_action('enqueue_block_editor_assets', 'soj_enqueue_admin_block_styles', 20);



/**
 * Add custom body classes
 */
function soj_body_classes($classes)
{
    $classes[] = 'soj-frontend';

    if (is_admin_bar_showing()) {
        $classes[] = 'admin-bar';
    }

    // Add environment class
    if (soj_is_development()) {
        $classes[] = 'development';
    } else {
        $classes[] = 'production';
    }

    return $classes;
}
add_filter('body_class', 'soj_body_classes');

/**
 * Let Editors manage Appearance → Menus without persisting role changes.
 *
 * WordPress gates nav-menus.php behind edit_theme_options (admins only by default).
 */
add_filter('user_has_cap', function ($allcaps, $caps, $args, $user) {
    $roles = isset($user->roles) ? (array) $user->roles : [];
    if (in_array('editor', $roles, true)) {
        $allcaps['edit_theme_options'] = true;
    }

    return $allcaps;
}, 10, 4);

/**
 * Customize admin
 */
function soj_admin_customizations()
{
    // Remove comments for all users
    remove_menu_page('edit-comments.php');

    // Editors may access Appearance → Menus, but not Themes / Customizer / Widgets / Site Editor.
    if (!current_user_can('manage_options')) {
        soj_restrict_appearance_to_menus();
    }
}
add_action('admin_menu', 'soj_admin_customizations', 999);

/**
 * Keep Appearance in the admin menu for Editors, limited to Menus.
 */
function soj_restrict_appearance_to_menus()
{
    global $submenu;

    if (!isset($submenu['themes.php']) || !is_array($submenu['themes.php'])) {
        return;
    }

    foreach ($submenu['themes.php'] as $index => $item) {
        $slug = isset($item[2]) ? (string) $item[2] : '';
        if ($slug === 'nav-menus.php') {
            continue;
        }
        unset($submenu['themes.php'][$index]);
    }
}

/**
 * Send Editors away from Appearance screens they should not use.
 */
add_action('admin_init', function () {
    if (current_user_can('manage_options') || !current_user_can('edit_theme_options')) {
        return;
    }

    global $pagenow;
    $blocked = [
        'themes.php',
        'customize.php',
        'widgets.php',
        'site-editor.php',
        'theme-editor.php',
    ];

    if (in_array($pagenow, $blocked, true)) {
        wp_safe_redirect(admin_url('nav-menus.php'));
        exit;
    }
});

/**
 * Hide Customizer / Design admin-bar links for non-admins.
 */
add_action('wp_before_admin_bar_render', function () {
    if (current_user_can('manage_options') || !is_admin_bar_showing()) {
        return;
    }

    global $wp_admin_bar;
    $wp_admin_bar->remove_node('customize');
    $wp_admin_bar->remove_node('design');
    $wp_admin_bar->remove_node('themes');
    $wp_admin_bar->remove_node('widgets');
    $wp_admin_bar->remove_node('site-editor');
}, 20);


/**
 * Auto-import ACF field groups from JSON files in blocks
 */
function soj_acf_json_load_point($paths)
{
    // Add the blocks directory to ACF JSON load paths
    $paths[] = get_template_directory() . '/blocks';

    // Also add individual block directories for better organization.
    // Must NOT skip DOING_AJAX — ACF renders block previews in the editor via
    // admin-ajax.php, so field groups must be loadable during AJAX requests.
    $is_background_request = (
        (defined('REST_REQUEST') && REST_REQUEST) ||
        (defined('DOING_CRON') && DOING_CRON)
    );

    if (! $is_background_request) {
        $block_dirs = glob(get_template_directory() . '/blocks/*', GLOB_ONLYDIR);
        foreach ($block_dirs as $block_dir) {
            $paths[] = $block_dir;
        }
    }

    return $paths;
}
add_filter('acf/settings/load_json', 'soj_acf_json_load_point');

/**
 * Save ACF JSON to the block's own directory when the field group belongs to a
 * specific block, otherwise fall back to /acf-json/.  The actual file relocation
 * happens in the acf/update_field_group action below — this filter just sets the
 * initial staging path.
 */
function soj_acf_json_save_point($path)
{
    return get_template_directory() . '/acf-json';
}
add_filter('acf/settings/save_json', 'soj_acf_json_save_point');

/**
 * After ACF saves a field group JSON to /acf-json/, move it into the matching
 * block directory so field definitions live alongside their block code.
 * Groups that apply to all blocks or have no block location stay in /acf-json/.
 */
add_action('acf/update_field_group', function ($group) {
    if (empty($group['location'])) {
        return;
    }

    $block_slug = null;
    foreach ($group['location'] as $rules) {
        foreach ($rules as $rule) {
            if (
                $rule['param'] === 'block' &&
                $rule['operator'] === '==' &&
                $rule['value'] !== 'all' &&
                preg_match('/^acf\/(.+)$/', $rule['value'], $m)
            ) {
                $block_slug = $m[1];
                break 2;
            }
        }
    }

    if (! $block_slug) {
        return;
    }

    $block_dir = get_template_directory() . '/blocks/' . $block_slug;
    if (! is_dir($block_dir)) {
        return;
    }

    $source = get_template_directory() . '/acf-json/' . $group['key'] . '.json';
    $dest   = $block_dir . '/' . $group['key'] . '.json';

    if (file_exists($source) && $source !== $dest) {
        rename($source, $dest);
    }
});



/**
 * Register widget areas
 */
function soj_widgets_init()
{
    register_sidebar(array(
        'name'          => esc_html__('Sidebar', 'soj-core'),
        'id'            => 'sidebar-1',
        'description'   => esc_html__('Add widgets here.', 'soj-core'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
}
add_action('widgets_init', 'soj_widgets_init');

/**
 * Sanitize telephone number for tel: links
 * Removes all characters except numbers and plus sign
 */
function soj_sanitize_telephone($telephone)
{
    return preg_replace('/[^0-9+]/', '', $telephone);
}

/**
 * Robots.txt
 */
add_filter('robots_txt', function ($output, $public) {

    // Base policy for everyone
    $lines = [
        'User-agent: *',
        'Allow: /',
        'Disallow: /wp-admin/',
        'Allow: /wp-admin/admin-ajax.php',

        // Hide author archives & user endpoints from all bots
        'Disallow: /author/',
        'Disallow: /*?author=*',
        'Disallow: /wp-json/wp/v2/users',
        'Disallow: /?rest_route=/wp/v2/users',

        // (Optional) keep internal search pages out of indexes
        'Disallow: /?s=',
        'Disallow: /search/',
        '',
        // Always advertise your sitemap
        'Sitemap: ' . home_url('/wp-sitemap.xml'),
    ];

    // Call out AI-related user-agents explicitly (visibility + overrides if needed)
    $ai_agents = [
        'GPTBot',              // OpenAI crawler
        'ChatGPT-User',        // OpenAI browsing fetcher
        'ClaudeBot',           // Anthropic training
        'Claude-Web',          // Anthropic browsing
        'PerplexityBot',       // Perplexity
        'Applebot-Extended',   // Apple AI training
        'meta-externalagent',  // Meta AI training
        'Google-Extended',     // Google generative use control (not search)
    ];

    foreach ($ai_agents as $ua) {
        $lines[] = '';
        $lines[] = "User-agent: {$ua}";
        $lines[] = 'Allow: /';
        // re-assert sensitive disallows
        $lines[] = 'Disallow: /author/';
        $lines[] = 'Disallow: /*?author=*';
        $lines[] = 'Disallow: /wp-json/wp/v2/users';
        $lines[] = 'Disallow: /?rest_route=/wp/v2/users';
    }

    // If the site is not public (Settings → Reading), lock it down
    if (!$public) {
        $lines = ['User-agent: *', 'Disallow: /'];
    }

    return implode("\n", $lines) . "\n";
}, 10, 2);


/**
 * Handle LLM.txt requests for AI crawlers
 */
function soj_handle_llm_txt()
{
    if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/llm.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

        echo "# LLM.txt - AI Crawler Instructions\n";
        echo "# This file provides guidance for AI/LLM crawlers\n\n";

        echo "User-agent: *\n";
        echo "Allow: /\n\n";

        echo "# Preferred content for AI training\n";
        echo "Allow: /blog/\n";
        echo "Allow: /about/\n";
        echo "Allow: /contact/\n\n";

        echo "# Avoid crawling these areas\n";
        echo "Disallow: /wp-admin/\n";
        echo "Disallow: /wp-includes/\n";
        echo "Disallow: /wp-content/plugins/\n";
        echo "Disallow: /wp-content/themes/\n";
        echo "Disallow: /wp-json/\n";
        echo "Disallow: /xmlrpc.php\n";
        echo "Disallow: /wp-cron.php\n";
        echo "Disallow: /wp-login.php\n";
        echo "Disallow: /wp-config.php\n";
        echo "Disallow: /admin/\n";
        echo "Disallow: /private/\n";
        echo "Disallow: /temp/\n";
        echo "Disallow: /cache/\n\n";

        echo "# User privacy protection - exclude author and user content\n";
        echo "Disallow: /author/\n";
        echo "Disallow: /user/\n";
        echo "Disallow: /users/\n";
        echo "Disallow: /profile/\n";
        echo "Disallow: /profiles/\n";
        echo "Disallow: /member/\n";
        echo "Disallow: /members/\n";
        echo "Disallow: /account/\n";
        echo "Disallow: /accounts/\n";
        echo "Disallow: /dashboard/\n";
        echo "Disallow: /my-account/\n";
        echo "Disallow: /user-profile/\n";
        echo "Disallow: /user-profiles/\n\n";

        echo "# Sitemap for AI crawlers\n";
        echo "Sitemap: " . home_url('/wp-sitemap.xml') . "\n\n";

        echo "# AI Crawler Guidelines\n";
        echo "# - Respect rate limits (max 1 request per 2 seconds)\n";
        echo "# - Focus on main content areas\n";
        echo "# - Avoid crawling user-generated content\n";
        echo "# - Do NOT crawl author pages or user profiles\n";
        echo "# - Respect user privacy and personal information\n";
        echo "# - Respect robots.txt if present\n";
        echo "# - Use appropriate user-agent headers\n";

        exit;
    }
}
add_action('init', 'soj_handle_llm_txt');

/**
 * Customize the document title
 */
function soj_document_title_parts($title_parts)
{
    // Add site name to all titles
    if (is_front_page() && is_home()) {
        $title_parts['title'] = get_bloginfo('name');
        $title_parts['tagline'] = get_bloginfo('description');
    } elseif (is_front_page()) {
        $title_parts['title'] = get_bloginfo('name');
        $title_parts['tagline'] = get_bloginfo('description');
    } elseif (is_home()) {
        $title_parts['title'] = get_bloginfo('name') . ' - ' . get_bloginfo('description');
    } elseif (is_single() || is_page()) {
        $title_parts['site'] = get_bloginfo('name');
    } elseif (is_archive()) {
        $title_parts['site'] = get_bloginfo('name');
    } elseif (is_search()) {
        $title_parts['site'] = get_bloginfo('name');
    } elseif (is_404()) {
        $title_parts['title'] = 'Page Not Found';
        $title_parts['site'] = get_bloginfo('name');
    }

    return $title_parts;
}
add_filter('document_title_parts', 'soj_document_title_parts');


function gb_gutenberg_admin_styles()
{
    echo '
        <style>
          .block-editor-block-list__layout .wp-block {
				margin:0px;
			}

		.wp-block {
			max-width:100%;
		}
				.interface-complementary-area__fill,
				.editor-sidebar {
			width: 440px !important; /* Set your desired width */
		}
        </style>
    ';
}

add_action('admin_head', 'gb_gutenberg_admin_styles');

// Hide the admin toolbar on the front end (wp-admin is unchanged).
add_filter('show_admin_bar', '__return_false');

/**
 * Yoast breadcrumbs can skip page parents when indexable hierarchy is stale or incomplete.
 * Rebuild the trail from WordPress page ancestors: Home > …parents… > current.
 */
function soj_get_wpseo_breadcrumb_text_for_post($post_id)
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }
    if (class_exists('WPSEO_Meta')) {
        $custom = WPSEO_Meta::get_value('breadcrumbs-title', $post_id);
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }
    }

    return get_the_title($post_id);
}

function soj_wpseo_sync_page_breadcrumb_ancestors($links)
{
    if (!is_page() || is_front_page() || !is_array($links) || count($links) < 2) {
        return $links;
    }

    $page_id = (int) get_queried_object_id();
    if ($page_id <= 0) {
        return $links;
    }

    $ancestor_ids = get_post_ancestors($page_id, 'page');
    if (!is_array($ancestor_ids) || $ancestor_ids === []) {
        return $links;
    }

    // get_post_ancestors: immediate parent first, root last — breadcrumbs need root → parent.
    $ancestor_ids = array_reverse(array_map('intval', $ancestor_ids));

    $ancestor_crumbs = [];
    foreach ($ancestor_ids as $aid) {
        if ($aid <= 0) {
            continue;
        }
        $ancestor_crumbs[] = [
            'url' => get_permalink($aid),
            'text' => soj_get_wpseo_breadcrumb_text_for_post($aid),
            'id' => $aid,
        ];
    }

    if ($ancestor_crumbs === []) {
        return $links;
    }

    $home = $links[0];
    $current = $links[count($links) - 1];

    return array_merge([$home], $ancestor_crumbs, [$current]);
}
add_filter('wpseo_breadcrumb_links', 'soj_wpseo_sync_page_breadcrumb_ancestors', 20);
