<?php

namespace FlyingPress;

class Config
{
  // Variable to store the configuration
  public static $config;

  // Default configuration
  protected static $initial_config = [
    // License
    'license_key' => '',
    'license_active' => false,
    'license_status' => '',

    // Core Web Vitals
    'vitals' => true,

    // CSS & JavaScript Optimization
    'css_js_minify' => true,
    'css_rucss' => true,
    'css_rucss_include_selectors' => [],
    'js_delay' => true,
    'js_delay_method' => 'defer',
    'js_delay_excludes' => [],
    'js_delay_third_party' => false,
    'js_delay_third_party_excludes' => [],
    'js_delay_selected' => false,
    'js_delay_selected_includes' => [],
    'css_js_self_host_third_party' => true,

    // Image, Video & iFrame Optimization
    'lazy_load' => true,
    'lazy_load_exclusions' => [],
    'properly_size_images' => true,
    'youtube_placeholder' => true,
    'self_host_gravatars' => true,
    'image_compression_type' => 'lossy',
    'image_format' => 'avif',
    'image_auto_optimize_uploads' => false,
    'image_optimizer_excludes' => [],

    // Fonts Optimization
    'fonts_preload' => true,
    'fonts_optimize_google' => true,
    'fonts_display_swap' => true,

    // Rendering Optimization
    'lazy_render' => true,
    'lazy_render_excludes' => [],

    // Basic Caching
    'cache_link_prefetch' => true,
    'cache_mobile' => false,
    'cache_logged_in' => false,
    'cache_refresh' => false,
    'cache_refresh_interval' => '2hours',

    // Advanced Caching
    'cache_bypass_urls' => [],
    'cache_include_queries' => [],
    'cache_bypass_cookies' => [],

    // CDN
    'cdn' => false,
    'cdn_type' => 'custom',
    'cdn_url' => '',
    'cdn_file_types' => 'all',
    'flying_cdn_api_key' => '',

    // Cloudflare
    'cf_api_key' => '',
    'cf_email' => '',
    'cf_zone_id' => '',
    'cf_page_caching' => false,
    'cf_cache_ruleset_id' => '',
    'cf_cache_rule_id' => '',
    'cf_cache_file_rule_id' => '',
    'cf_rewrite_ruleset_id' => '',
    'cf_rewrite_rule_id' => '',
    'cf_rules_version' => 0,

    // Automatic Cleaning
    'db_auto_clean' => false,
    'db_auto_clean_interval' => 'daily',

    // Post Cleanup
    'db_post_revisions' => false,
    'db_post_auto_drafts' => false,
    'db_post_trashed' => false,

    // Comment Cleanup
    'db_comments_spam' => false,
    'db_comments_trashed' => false,

    // Table Optimization
    'db_transients_expired' => false,
    'db_optimize_tables' => false,

    // Remove Unnecessary Assets
    'bloat_disable_block_css' => false,
    'bloat_disable_dashicons' => false,
    'bloat_disable_emojis' => false,
    'bloat_disable_jquery_migrate' => false,

    // Disable Features
    'bloat_disable_xml_rpc' => false,
    'bloat_disable_rss_feed' => false,
    'bloat_disable_oembeds' => false,

    // Database & Activity
    'bloat_post_revisions_control' => false,
    'bloat_heartbeat_control' => false,
  ];

  /** Not included in {@see safe_config()} (CDN + Cloudflare credentials). */
  protected static $secret_keys = [
    'flying_cdn_api_key' => true,
    'cf_api_key' => true,
    'cf_email' => true,
    'cf_zone_id' => true,
  ];

  public static function safe_config()
  {
    return array_diff_key(self::$config, self::$secret_keys);
  }

  public static function init()
  {
    // Get the saved configuration from the database
    self::$config = get_option('FLYING_PRESS_CONFIG', []);

    // If the saved version is different from the current version, run the upgrade action
    $saved_version = get_option('FLYING_PRESS_VERSION');
    $current_version = FLYING_PRESS_VERSION;

    if ($saved_version !== $current_version || empty(self::$config)) {
      update_option('FLYING_PRESS_VERSION', $current_version);
      self::migrate_config();
    }

    // Remove the configuration when the plugin is deleted
    register_uninstall_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'on_uninstall']);
  }

  public static function migrate_config()
  {
    $prev = self::$config; // capture previous

    // Remove keys that don't exist in the initial config
    self::$config = array_intersect_key(self::$config, self::$initial_config);

    // Add new fields from the default configuration if they don't exist in the saved configuration
    self::$config = array_merge(self::$initial_config, self::$config);

    // Normalize legacy values
    if (in_array(self::$config['js_delay_method'], ['selected', 'all'], true)) {
      self::$config['js_delay_method'] = 'defer';
    }

    update_option('FLYING_PRESS_CONFIG', self::$config);

    add_action('init', function () use ($prev) {
      // Fire with (current, previous)
      do_action('flying_press_update_config:after', self::$config, $prev);
      do_action('flying_press_upgraded');
    });

    // Remove the tasks table
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tasks");
  }

  // Function to update the configuration
  public static function update_config($new_config = [], $silent = false)
  {
    $prev = self::$config; // capture previous

    self::$config = array_merge(self::$config, $new_config);
    update_option('FLYING_PRESS_CONFIG', self::$config);

    // Fire with (current, previous) if not silent
    !$silent && do_action('flying_press_update_config:after', self::$config, $prev);
  }

  public static function on_uninstall()
  {
    delete_option('FLYING_PRESS_CONFIG');
    delete_option('FLYING_PRESS_VERSION');
    Queue::delete_table();
  }
}
