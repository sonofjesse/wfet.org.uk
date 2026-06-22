<?php
/**
 * Performance Optimizations
 *
 * @package SOJ_Core_Modern
 * @since 2.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disable WordPress pseudo-cron (visitor-triggered HTTP back-request).
 *
 * IMPORTANT: This must be paired with a real server-side cron job that calls
 * wp-cron.php on a schedule (e.g., every 5 minutes). Without a server cron,
 * scheduled tasks (emails, publishing, backups) will stop running.
 * On Kinsta: MyKinsta → Sites → Tools → Cron Jobs.
 *
 * With DISABLE_WP_CRON = true, WordPress no longer spawns a background HTTP
 * request on every page load, eliminating a full theme bootstrap per visit.
 */
if (!defined('DISABLE_WP_CRON')) {
    define('DISABLE_WP_CRON', true);
}

/**
 * Remove jQuery Migrate on the front end (correct timing)
 */
add_action('wp_default_scripts', function ($scripts) {
    if (!is_admin() && isset($scripts->registered['jquery'])) {
        $scripts->registered['jquery']->deps = array_diff(
            $scripts->registered['jquery']->deps,
            ['jquery-migrate']
        );
    }
});

/**
 * Emoji, TinyMCE emojis, and emoji DNS prefetch removal
 */
add_action('init', function () {
    // Front + admin
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

    // TinyMCE plugin
    add_filter('tiny_mce_plugins', function ($plugins) {
        return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
    });

    // Emoji DNS prefetch
    add_filter('wp_resource_hints', function ($urls, $relation_type) {
        if ($relation_type === 'dns-prefetch') {
            $emoji_svg = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
            $urls = array_diff($urls, [$emoji_svg]);
        }
        return $urls;
    }, 10, 2);
});

/**
 * Scripts & styles hygiene (run late to dequeue after others enqueue)
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }

    // Remove wp-embed
    wp_deregister_script('wp-embed');

    // Remove core block library CSS — layout/alignment is handled in theme SCSS.
    // Keep global-styles: theme.json presets (--wp--preset--spacing--*, colours),
    // group padding/blockGap, and per-block inline styles from the editor.
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme'); // classic theme styles alias
    wp_dequeue_style('classic-theme-styles');   // since WP 6.x
    wp_dequeue_style('wp-block-image');         // caption overlay handled in theme SCSS
    wp_dequeue_style('wp-block-pullquote');     // quote styling handled in theme SCSS
    wp_dequeue_style('wp-block-pullquote-theme');
    wp_dequeue_style('wp-block-quote');

    // WooCommerce blocks CSS (if you fully style yourself)
    wp_dequeue_style('wc-blocks-style');

    // Dashicons for non-logged visitors
    if (!is_user_logged_in()) {
        wp_dequeue_style('dashicons');
    }
}, 100);

/**
 * Head reductions (adjacent links, shortlink, WLW/RSD)
 */
add_action('init', function () {
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
    remove_action('wp_head', 'wp_shortlink_wp_head', 10);
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
});

/**
 * Image/WebP pipeline
 */
add_action('init', function () {
    // Add WebP mime support
    add_filter('upload_mimes', function ($mimes) {
        $mimes['webp'] = 'image/webp';
        return $mimes;
    });

    // Convert uploaded JPG/PNG to WebP
    add_filter('wp_handle_upload', 'soj_convert_to_webp', 10, 2);

    // Serve WebP in src/srcset when available & supported
    add_filter('wp_get_attachment_image_src', 'soj_serve_webp_image', 10, 4);
    add_filter('wp_calculate_image_srcset', 'soj_webp_srcset', 10, 5);
}, 0);

function soj_convert_to_webp($upload, $context) {
    if (!preg_match('/\.(jpg|jpeg|png)$/i', $upload['file'])) return $upload;
    if (!extension_loaded('gd') && !extension_loaded('imagick')) return $upload;

    $file_path = $upload['file'];
    $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file_path);
    if (file_exists($webp_path)) return $upload;

    if (soj_create_webp($file_path, $webp_path)) {
        $upload['webp_file'] = $webp_path;
        $base = wp_upload_dir();
        $upload['webp_url']  = str_replace($base['basedir'], $base['baseurl'], $webp_path);
    }
    return $upload;
}

function soj_create_webp($source_path, $webp_path) {
    $info = @getimagesize($source_path);
    if (!$info) return false;

    switch ($info['mime']) {
        case 'image/jpeg': $image = @imagecreatefromjpeg($source_path); break;
        case 'image/png':
            $image = @imagecreatefrompng($source_path);
            if ($image) { imagepalettetotruecolor($image); imagealphablending($image, true); imagesavealpha($image, true); }
            break;
        default: return false;
    }
    if (!$image) return false;

    $ok = imagewebp($image, $webp_path, 85);
    imagedestroy($image);
    return $ok;
}

function soj_browser_supports_webp() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'image/webp') !== false) return true;

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    foreach (['Chrome/', 'Firefox/', 'Safari/', 'Edge/', 'Opera/'] as $sig) {
        if (strpos($ua, $sig) !== false) return true;
    }
    return false;
}

function soj_get_webp_url($image_url) {
    $up = wp_upload_dir();
    $file = str_replace($up['baseurl'], $up['basedir'], $image_url);
    $webp = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file);
    if (!file_exists($webp)) {
        if (!soj_create_webp($file, $webp)) return false;
    }
    return str_replace($up['basedir'], $up['baseurl'], $webp);
}

function soj_serve_webp_image($image, $attachment_id, $size, $icon) {
    if (!is_array($image) || !soj_browser_supports_webp()) return $image;
    $webp = soj_get_webp_url($image[0]);
    if ($webp) $image[0] = $webp;
    return $image;
}

function soj_webp_srcset($sources, $size_array, $src, $meta, $attachment_id) {
    if (!soj_browser_supports_webp()) return $sources;
    foreach ($sources as $w => $item) {
        $webp = soj_get_webp_url($item['url']);
        if ($webp) $sources[$w]['url'] = $webp;
    }
    return $sources;
}

/**
 * Allow CSV uploads
 */
add_filter('upload_mimes', function ($mimes) {
    $mimes['csv'] = 'text/csv';
    return $mimes;
});

/**
 * Adobe Fonts (Typekit) kit ID — Proxima Nova / Proxima Sera.
 */
if (!defined('SOJ_TYPEKIT_KIT_ID')) {
    define('SOJ_TYPEKIT_KIT_ID', 'ilp3sfh');
}

/**
 * Fetch Typekit CSS and rewrite font-display:auto to swap for Lighthouse/FCP.
 *
 * Adobe serves font-display:auto by default. Swap shows fallback text immediately
 * while web fonts load. Cached daily; falls back to the kit stylesheet if fetch fails.
 *
 * @return string|false Inline CSS, or false on failure.
 */
function soj_get_typekit_css_with_swap()
{
    static $css = null;
    if ($css !== null) {
        return $css;
    }

    $cache_key = 'soj_typekit_css_' . SOJ_TYPEKIT_KIT_ID;
    $cached    = get_transient($cache_key);
    if (is_string($cached) && $cached !== '') {
        return $css = $cached;
    }

    $response = wp_remote_get(
        'https://use.typekit.net/' . SOJ_TYPEKIT_KIT_ID . '.css',
        [
            'timeout'    => 5,
            'user-agent' => 'SOJ Theme/' . SOJ_THEME_VERSION,
        ]
    );

    if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
        return $css = false;
    }

    $body = wp_remote_retrieve_body($response);
    if ($body === '') {
        return $css = false;
    }

    $body = preg_replace('/@import\s+url\([^)]+\);\s*/', '', $body);
    $body = str_replace('font-display:auto', 'font-display:swap', $body);

    set_transient($cache_key, $body, DAY_IN_SECONDS);

    return $css = $body;
}

/**
 * Preload primary Typekit WOFF2 files (regular body + heading weights).
 *
 * @param string $css Typekit CSS with @font-face rules.
 */
function soj_preload_typekit_fonts($css)
{
    if (!preg_match_all('/url\("(https:\/\/use\.typekit\.net\/af\/[^"]+?\/l\?[^"]*fvd=n4[^"]*)"\)\s*format\("woff2"\)/', $css, $matches)) {
        return;
    }

    foreach (array_unique($matches[1]) as $url) {
        printf(
            '<link rel="preload" as="font" type="font/woff2" crossorigin href="%s">' . "\n",
            esc_url($url)
        );
    }
}

/**
 * Load Adobe Fonts early in head with font-display: swap.
 */
add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }

    $css = soj_get_typekit_css_with_swap();
    if ($css) {
        soj_preload_typekit_fonts($css);
        echo '<style id="soj-typekit-fonts">' . $css . '</style>' . "\n";
        return;
    }

    echo '<link rel="stylesheet" href="https://use.typekit.net/' . esc_attr(SOJ_TYPEKIT_KIT_ID) . '.css">' . "\n";
}, 1);

/**
 * Preload main CSS + critical fonts (early in head)
 */
add_action('wp_head', function () {
    $css_url = soj_get_main_css_uri();

    echo '<link rel="preload" as="style" href="' . esc_url($css_url) . '">';
    echo '<link rel="stylesheet" href="' . esc_url($css_url) . '" fetchpriority="high">';

}, 2);

/**
 * Resource hints (preconnect/dns-prefetch)
 */
add_filter('wp_resource_hints', function ($hints, $rel) {
    if ($rel === 'preconnect') {
        $hints[] = ['href' => 'https://use.typekit.net', 'crossorigin' => 'anonymous'];
        $hints[] = ['href' => 'https://p.typekit.net', 'crossorigin' => 'anonymous'];
        $hints[] = ['href' => 'https://www.google-analytics.com', 'crossorigin' => 'anonymous'];
    } elseif ($rel === 'dns-prefetch') {
        $hints[] = '//use.typekit.net';
        $hints[] = '//p.typekit.net';
        $hints[] = '//www.google-analytics.com';
    }
    return $hints;
}, 10, 2);

/**
 * Whether the current front-end request targets /webinars/ (and child paths).
 *
 * Used to skip page caching and send no-cache headers so password-gated or
 * personalised webinar flows are not served stale HTML.
 *
 * @return bool
 */
function soj_is_webinars_request()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_CRON') && DOING_CRON)) {
        return $cached = false;
    }

    if (!isset($_SERVER['REQUEST_URI']) || !is_string($_SERVER['REQUEST_URI'])) {
        return $cached = false;
    }

    $path = (string) strtok(wp_unslash($_SERVER['REQUEST_URI']), '?');
    $path = '/' . ltrim(rawurldecode($path), '/');

    $webinars = wp_parse_url(home_url('/webinars/'));
    if (!is_array($webinars) || empty($webinars['path'])) {
        return $cached = false;
    }

    $base = '/' . trim($webinars['path'], '/') . '/';
    $path_slash = '/' . trim($path, '/') . '/';

    return $cached = (strpos($path_slash, $base) === 0);
}

/**
 * Hint full-page cache plugins not to store /webinars/ HTML.
 */
add_action('init', function () {
    if (!soj_is_webinars_request()) {
        return;
    }
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
}, 0);

/**
 * Short HTML caching from PHP (assets caching should be in Nginx)
 */
add_action('send_headers', function () {
    if (is_admin()) {
        return;
    }

    if (soj_is_webinars_request()) {
        nocache_headers();
        return;
    }

    if (is_user_logged_in()) {
        return;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === '' || $ext === 'html') {
        header('Cache-Control: public, max-age=3600'); // 1 hour
        header('Vary: Accept-Encoding');
    }
}, 5);

// Image optimization
add_filter( 'imagify_get_custom_folders', function( $folders ) {
    $upload_dir = wp_upload_dir();
    $folders[] = $upload_dir['basedir'] . '/dynamic-images';
    return $folders;
});
add_filter( 'imagify_custom_folders', function( $folders ) {
    $upload_dir = wp_upload_dir();
    $folders[ trailingslashit( $upload_dir['basedir'] ) . 'dynamic-images' ] = 'Dynamic Images';
    return $folders;
});