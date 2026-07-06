<?php
/**
 * Security Enhancements
 *
 * @package SOJ_Core_Modern
 * @since 2.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstrap & head cleanups
 */
add_action('init', function () {
    // Remove WP version
    remove_action('wp_head', 'wp_generator');

    // Unnecessary meta tags
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);

    // REST/oEmbed discovery links (head + headers)
    remove_action('wp_head', 'rest_output_link_wp_head', 10);
    remove_action('template_redirect', 'rest_output_link_header', 11);
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');

    // Disable emoji scripts/styles
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');

    // Disable XML-RPC entirely (also block /xmlrpc.php in Nginx)
    add_filter('xmlrpc_enabled', '__return_false');

    // Remove XML-RPC pingbacks
    add_filter('xmlrpc_methods', function ($methods) {
        unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
        return $methods;
    });

    // Redirect author archives to home
    add_action('template_redirect', function () {
        if (is_author()) {
            wp_redirect(home_url(), 301);
            exit;
        }
    });
});

/**
 * REST API hardening (selective)
 * - Block users & comments endpoints for visitors without the required capability
 * - Use rest_dispatch_request (not rest_endpoints) to avoid auth recursion / memory exhaustion
 */
add_filter('rest_dispatch_request', function ($result, $request, $route, $handler) {
    $path = $request->get_route();

    if (preg_match('#^/wp/v2/users(?:/|$)#', $path)) {
        // /wp/v2/users/me has its own permission checks in core.
        if (!preg_match('#^/wp/v2/users/me(?:/|$)#', $path) && !current_user_can('list_users')) {
            return new WP_Error('rest_forbidden', 'Access denied.', ['status' => 403]);
        }
    }

    if (preg_match('#^/wp/v2/comments(?:/|$)#', $path) && !current_user_can('moderate_comments')) {
        return new WP_Error('rest_forbidden', 'Access denied.', ['status' => 403]);
    }

    return $result;
}, 10, 4);

/**
 * Disable author sitemaps
 */
add_filter('wp_sitemaps_users_query_args', function ($args) {
    return ['number' => 0];
});

/**
 * File editor & SSL admin
 * (Prefer in wp-config.php)
 */
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}
// In wp-config.php add: define('FORCE_SSL_ADMIN', true);

/**
 * Remove core editors from admin menus
 */
add_action('admin_menu', function () {
    global $submenu;

    if (isset($submenu['themes.php'])) {
        foreach ($submenu['themes.php'] as $k => $item) {
            if (!empty($item[2]) && strpos($item[2], 'theme-editor.php') !== false) {
                unset($submenu['themes.php'][$k]);
            }
        }
    }
    if (isset($submenu['plugins.php'])) {
        foreach ($submenu['plugins.php'] as $k => $item) {
            if (!empty($item[2]) && strpos($item[2], 'plugin-editor.php') !== false) {
                unset($submenu['plugins.php'][$k]);
            }
        }
    }
}, 99999);

/**
 * Login UX & brute-force resistance (pair with Nginx limit_req)
 */
add_filter('login_errors', function () {
    return 'Login failed. Please try again.';
});

// Helper to get reliable IP address
function soj_get_client_ip() {
    // If behind a proxy/load balancer, check forwarded IP (configure based on your setup)
    // IMPORTANT: Only use X-Forwarded-For if you trust your reverse proxy
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        // Cloudflare
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = 'unknown';
    }

    // Validate IP address
    $ip = filter_var($ip, FILTER_VALIDATE_IP);
    return $ip ?: 'unknown';
}

// IP-based throttle via transients (reset on success)
add_action('wp_login_failed', function ($username) {
    $ip  = soj_get_client_ip();
    $key = 'login_attempts_' . wp_hash($ip); // Use wp_hash instead of md5
    $att = (int) get_transient($key);
    $att++;
    set_transient($key, $att, 15 * MINUTE_IN_SECONDS);
    if ($att >= 5) {
        set_transient('login_blocked_' . wp_hash($ip), 1, 30 * MINUTE_IN_SECONDS);
    }
});
add_filter('authenticate', function ($user) {
    $ip = soj_get_client_ip();
    if (get_transient('login_blocked_' . wp_hash($ip))) {
        return new WP_Error('too_many_attempts', 'Too many login attempts. Please try again later.');
    }
    return $user;
}, 30);
add_action('wp_login', function () {
    $ip = soj_get_client_ip();
    delete_transient('login_attempts_' . wp_hash($ip));
    delete_transient('login_blocked_' . wp_hash($ip));
}, 10, 2);

/**
 * SVG (admins only)
 *
 * WARNING: This regex-based sanitization provides BASIC protection only.
 * For production sites, use a proper SVG sanitizer library like:
 * - enshrined/svg-sanitize: https://github.com/darylldoyle/svg-sanitizer
 * - Install via composer: composer require enshrined/svg-sanitize
 *
 * Known limitations of this approach:
 * - Cannot detect obfuscated malicious code
 * - May not catch all XSS vectors (CSS expressions, foreign objects, etc.)
 * - Does not validate SVG structure
 */
add_filter('upload_mimes', function ($mimes) {
    if (is_admin() && current_user_can('manage_options')) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
    }
    return $mimes;
});
add_filter('wp_handle_upload_prefilter', function ($file) {
    if (!empty($file['type']) && $file['type'] === 'image/svg+xml') {
        if (!current_user_can('manage_options')) {
            $file['error'] = 'SVG uploads are restricted to administrators only.';
            return $file;
        }

        $svg = file_get_contents($file['tmp_name']);
        if ($svg === false) {
            $file['error'] = 'Unable to read SVG file.';
            return $file;
        }

        // Basic sanitization - NOT comprehensive
        $patterns = [
            '/<script\b[^>]*>.*?<\/script>/is',           // Remove script tags
            '/\s(on\w+)\s*=\s*(["\']).*?\2/iu',           // Remove event handlers
            '/\bxlink:href\s*=\s*(["\'])(?!#).*?\1/iu',   // Remove external xlink:href
            '/\b(?:href|src)\s*=\s*(["\'])(?:javascript:|data:).*?\1/iu', // Remove javascript: and data: URIs
            '/<\?(?:php|=).*?\?>/is',                     // Remove PHP tags
            '/<!DOCTYPE.*?>/is',                          // Remove DOCTYPE
            '/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', // Remove foreign objects
        ];
        $svg = preg_replace($patterns, '', $svg);

        if (file_put_contents($file['tmp_name'], $svg) === false) {
            $file['error'] = 'Unable to sanitize SVG file.';
            return $file;
        }
    }
    return $file;
});
add_filter('wp_prepare_attachment_for_js', function ($response) {
    if (!empty($response['mime']) && $response['mime'] === 'image/svg+xml' && !current_user_can('manage_options')) {
        $response['error'] = 'SVG files are restricted to administrators only.';
    }
    return $response;
}, 10);

/**
 * Security headers (frontend) — better via Nginx
 *
 * NOTE: CSP is configured with 'unsafe-inline' for compatibility.
 * For better security, implement nonce-based CSP and remove 'unsafe-inline'.
 * This requires adding nonce attributes to all inline scripts/styles.
 */
add_action('send_headers', function () {
    if (is_admin()) {
        return;
    }
    if (is_ssl()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');

    // Content Security Policy with specific domains
    // Adjust these domains based on your actual third-party services

    // HubSpot (forms / meetings / Quotes — some Quote UIs load iframes from *.azurewebsites.net)
    $hubspot_hosts = implode(' ', [
        'https://*.hubspot.com',
        'https://*.hubapi.com',
        'https://*.hsforms.net',
        'https://*.hsforms.com',
        'https://*.hs-banner.com',
        'https://*.hs-scripts.com',
        'https://*.hsleadflows.net',
        'https://*.hs-sites.com',
        'https://*.hubspotusercontent00.net',
        'https://*.hubspotusercontent10.net',
        'https://*.hubspot.eu',
        'https://*.azurewebsites.net',
        'https://*.hsappstatic.net',
    ]);

    $csp = [
        "default-src 'self'",
        // Scripts: WordPress inline + analytics + HubSpot loaders
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google-analytics.com https://www.googletagmanager.com {$hubspot_hosts}",
        // Styles: inline + Typekit (Adobe Fonts) + HubSpot embeds
        "style-src 'self' 'unsafe-inline' https://use.typekit.net {$hubspot_hosts}",
        // Images / tracking pixels etc.
        "img-src 'self' data: https://www.google-analytics.com https://secure.gravatar.com https://gravatar.com {$hubspot_hosts}",
        // Fonts (Typekit / Adobe Fonts)
        "font-src 'self' data: https://use.typekit.net https://p.typekit.net {$hubspot_hosts}",
        // XHR/fetch/beacon used by HubSpot embeds + analytics
        "connect-src 'self' https://www.google-analytics.com {$hubspot_hosts}",
        // iframes: HubSpot form frames often use js.hsforms.net; Quotes may hit Azure backends
        "frame-src 'self' {$hubspot_hosts}",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self' {$hubspot_hosts}",
        "upgrade-insecure-requests"
    ];

    // Apply filter to allow customization per page/plugin
    $csp = apply_filters('soj_csp_directives', $csp);

    header('Content-Security-Policy: ' . implode('; ', $csp));
});

/**
 * Admin bar tweak
 */
add_action('wp_before_admin_bar_render', function () {
    if (is_admin_bar_showing()) {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('wp-logo');
    }
});

/**
 * Feeds off (if not needed)
 */
function soj_disable_feed() {
    wp_redirect(home_url());
    exit;
}
add_action('do_feed', 'soj_disable_feed', 1);
add_action('do_feed_rdf', 'soj_disable_feed', 1);
add_action('do_feed_rss', 'soj_disable_feed', 1);
add_action('do_feed_rss2', 'soj_disable_feed', 1);
add_action('do_feed_atom', 'soj_disable_feed', 1);

/**
 * Application Passwords: disable for non-admins.
 * Must use the *_for_user filter — calling current_user_can() on
 * wp_is_application_passwords_available causes infinite recursion during REST auth.
 */
add_filter('wp_is_application_passwords_available_for_user', function ($available, $user) {
    return user_can($user, 'manage_options');
}, 10, 2);

