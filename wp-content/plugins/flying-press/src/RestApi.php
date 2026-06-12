<?php

namespace FlyingPress;

class RestApi
{
  public static function init()
  {
    add_action('rest_api_init', [__CLASS__, 'register_rest_apis']);
    add_action('admin_notices', [__CLASS__, 'rest_api_notice']);
  }

  public static function register_rest_apis()
  {
    // Only allow access to the REST API for users with the specified roles
    if (!Auth::is_allowed()) {
      return;
    }

    register_rest_route('flying-press', '/cache_status/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'get_cache_status'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/config/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'update_config'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/purge-current-page/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'purge_current_page'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/preload-cache/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'preload_cache'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/purge-pages-and-preload/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'purge_pages_and_preload'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/purge-everything/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'purge_everything'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press/image-optimizer', '/optimize/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'optimize_images'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press/image-optimizer', '/restore/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'restore_images'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press/image-optimizer', '/delete/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'delete_images'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press/image-optimizer', '/stop/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'stop_optimization'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press/image-optimizer', '/status/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'image_optimizer_status'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('flying-press', '/activate-license/', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'activate_license'],
      'permission_callback' => '__return_true',
    ]);
  }

  public static function rest_api_notice()
  {
    if (!is_admin() || !Auth::is_allowed() || self::is_update_screen()) {
      return;
    }

    // Backup current user
    $current_user = wp_get_current_user();

    // Simulate logged-out user
    wp_set_current_user(0);

    $result = apply_filters('rest_authentication_errors', null);

    // Restore user
    wp_set_current_user((int) ($current_user->ID ?? 0));

    $is_blocked = is_wp_error($result);

    if (!$is_blocked) {
      return;
    }

    echo "<div class='notice notice-error'><p><strong>FlyingPress:</strong> WordPress REST API is disabled. It must be accessible (including for non-logged-in users) for FlyingPress to work.</p></div>";
  }

  private static function is_update_screen()
  {
    global $pagenow;

    return in_array($pagenow, ['update.php', 'update-core.php'], true);
  }

  public static function get_cache_status()
  {
    return [
      'pages_cached' => Caching::count_pages(FLYING_PRESS_CACHE_DIR),
      'pages_in_queue' => Preload::get_remaining_tasks_count(),
    ];
  }

  public static function update_config($request)
  {
    $config = $request->get_json_params();

    if (empty($config)) {
      return new \WP_Error('flying-press/invalid-config', 'Invalid config');
    }

    Config::update_config($config);
    return Config::$config;
  }

  public static function preload_cache()
  {
    Preload::preload_cache();
    return ['success' => true];
  }

  public static function purge_current_page($request)
  {
    function_exists('fastcgi_finish_request') && fastcgi_finish_request();
    $url = $request->get_param('url');

    if (empty($url)) {
      return new \WP_Error('flying-press/invalid-url', 'Invalid URL');
    }

    Purge::purge_urls([$url]);
    Preload::preload_urls([$url], 11);
    return ['success' => true];
  }

  public static function purge_pages_and_preload()
  {
    Purge::purge_pages();
    Preload::preload_cache();
    return ['success' => true];
  }

  public static function purge_everything()
  {
    Purge::purge_everything();
    return ['success' => true];
  }

  public static function optimize_images()
  {
    return ImageOptimizer::optimize_images();
  }

  public static function stop_optimization()
  {
    return ImageOptimizer::stop_optimization();
  }

  public static function restore_images()
  {
    return ImageOptimizer::restore_images();
  }

  public static function delete_images()
  {
    return ImageOptimizer::delete_original_images();
  }

  public static function image_optimizer_status()
  {
    return ImageOptimizer::get_status();
  }

  public static function activate_license($request)
  {
    $license_key = $request->get_param('license_key');

    if (empty($license_key)) {
      return new \WP_Error('flying-press/invalid-license-key', 'Invalid License Key');
    }

    try {
      License::activate_license($license_key);
      return Config::$config;
    } catch (\Exception $e) {
      return new \WP_Error('flying-press/invalid-license-key', $e->getMessage());
    }
  }
}
