<?php

namespace FlyingPress\Integrations;

class APO
{
  private static $cf_api;

  public static function init()
  {
    if (!class_exists('Cloudflare\APO\WordPress\Hooks') && !class_exists('CF\WordPress\Hooks')) {
      return;
    }

    if (class_exists('Cloudflare\APO\WordPress\Hooks')) {
      self::$cf_api = new \Cloudflare\APO\WordPress\Hooks();
    } else {
      self::$cf_api = new \CF\WordPress\Hooks();
    }

    // Purge APO cache for an URL when it's purged from FlyingPress
    add_action('flying_press_purge_urls:before', [__CLASS__, 'purge_cloudflare_cache_by_urls']);

    // When Cloudflare plugin is active Purge Cloudflare APO cache before purging FP cache
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_cloudflare_cache']);

    // Purge Cloudflare cache before when entire FP cache is purged
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_cloudflare_cache']);
  }

  public static function purge_cloudflare_cache()
  {
    self::$cf_api->purgeCacheEverything();
  }

  public static function purge_cloudflare_cache_by_urls($urls)
  {
    if (!is_array($urls) || empty($urls)) {
      return;
    }

    $postids = [];
    foreach ($urls as $url) {
      $postids[] = url_to_postid($url);
    }
    $postids = array_unique($postids);
    self::$cf_api->purgeCacheByRelevantURLs($postids);
  }
}
