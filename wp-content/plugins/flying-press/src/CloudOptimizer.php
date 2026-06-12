<?php

namespace FlyingPress;

class CloudOptimizer
{
  const API_URL = 'https://page-optimizer.flyingpress.com/optimizer/';

  public static $optimizations;

  // Fetches optimizations from cache or API
  public static function fetch_optimizations($html)
  {
    $hash = self::get_hash($html);
    $cache_file = Caching::get_cache_path() . Caching::get_cache_file_name('json');

    // Try reading from cache
    if (is_readable($cache_file)) {
      $json = json_decode(gzdecode(file_get_contents($cache_file)));
      if (($json->structure_hash ?? '') === $hash) {
        return self::$optimizations = $json;
      }
    }

    // Fetch fresh data
    $json = self::fetch_from_api($html, $hash);
    if (!$json) {
      return null;
    }

    $json->structure_hash = $hash;
    file_put_contents($cache_file, gzencode(json_encode($json)));

    return self::$optimizations = $json;
  }

  // Sends request to the optimizer API
  private static function fetch_from_api($html, $hash)
  {
    global $wp_version;

    $config = wp_json_encode(Config::safe_config());

    $device = wp_is_mobile() && Config::$config['cache_mobile'] ? 'mobile' : 'desktop';
    $metadata = wp_json_encode([
      'language' => get_locale(),
      'php_version' => PHP_VERSION,
      'wordpress_version' => $wp_version,
      'phpredis' => extension_loaded('redis'),
      'relay' => extension_loaded('relay'),
      'redis' =>
        defined('WP_REDIS_HOST') || defined('WP_REDIS_SERVERS') || defined('WP_REDIS_PATH'),
      'object_cache' => wp_using_ext_object_cache(),
    ]);

    $response = wp_remote_post(self::API_URL, [
      'headers' => [
        'content-type' => 'text/html; charset=utf-8',
        'x-url' => site_url($_SERVER['REQUEST_URI']),
        'x-device' => $device,
        'x-version' => FLYING_PRESS_VERSION,
        'x-cache-file-name' => Caching::get_cache_file_name(),
        'x-hash' => $hash,
        'x-config' => $config,
        'x-metadata' => $metadata,
      ],
      'body' => $html,
      'timeout' => 15,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      $message = is_wp_error($response)
        ? $response->get_error_message()
        : 'HTTP ' .
          wp_remote_retrieve_response_code($response) .
          ': ' .
          wp_remote_retrieve_body($response);
      error_log('FlyingPress CloudOptimizer: ' . $message);
      return null;
    }

    return json_decode(wp_remote_retrieve_body($response));
  }

  // Creates a structure hash based on HTML tag structure, IDs, classes, etc.
  private static function get_hash($html)
  {
    $html = html_entity_decode($html);

    preg_match_all('/<\s*([a-zA-Z][\w:-]*)\b[^>]*>/i', $html, $tags);
    preg_match_all('/\bid\s*=\s*["\']([^"\']+)["\']/i', $html, $ids);
    preg_match_all('/\bclass\s*=\s*["\']([^"\']+)["\']/i', $html, $classes);
    preg_match_all(
      '/<link[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\']/i',
      $html,
      $stylesheets
    );
    preg_match_all('/<script[^>]*src=["\']([^"\']+)["\']/i', $html, $scripts);
    preg_match_all('/background[^:]*:url\s*\([\'"]?(https?:\/\/[^\'")]+)/i', $html, $bg_images);

    // Add rucss include selectors
    $include_selectors = Config::$config['css_rucss_include_selectors'] ?? [];

    // Flatten and clean class list
    $class_list = [];
    foreach ($classes[1] as $class_string) {
      foreach (preg_split('/\s+/', trim($class_string)) as $class) {
        $class = preg_replace('/\d.*$/', '', trim($class));
        if ($class !== '') {
          $class_list[] = $class;
        }
      }
    }

    // Clean IDs
    $id_list = array_map(fn($id) => preg_replace('/\d.*$/', '', $id), $ids[1] ?? []);

    // Clean tag names
    $tag_list = array_map(fn($tag) => '<' . strtolower($tag) . '>', $tags[1] ?? []);

    // Combine and hash
    $all = array_merge(
      $id_list,
      $class_list,
      $stylesheets[1] ?? [],
      $scripts[1] ?? [],
      $bg_images[1] ?? [],
      $tag_list,
      $include_selectors
    );
    $all = array_unique(array_filter($all));
    sort($all);

    return md5(implode(' ', $all));
  }
}
