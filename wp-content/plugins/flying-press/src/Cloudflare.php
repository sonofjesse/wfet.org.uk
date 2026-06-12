<?php

namespace FlyingPress;

use FlyingPress\{Config, Caching};

class Cloudflare
{
  // Increment when Cloudflare rule definitions change
  private const RULES_VERSION = 1;

  private static $host;

  public static function init()
  {
    self::$host = parse_url(site_url(), PHP_URL_HOST);
    add_action('init', [__CLASS__, 'register_purge_hooks']);
    add_action('activated_plugin', [__CLASS__, 'on_activation']);
    add_action('deactivate_flying-press/flying-press.php', [__CLASS__, 'on_deactivation']);
    add_action('flying_press_upgraded', [__CLASS__, 'on_upgrade']);

    // Action passes ($new_config, $prev_config)
    add_action('flying_press_update_config:after', [__CLASS__, 'on_config_updated'], 10, 2);

    self::verify_signature();
  }

  public static function register_purge_hooks()
  {
    add_action('flying_press_purge_urls:before', [__CLASS__, 'purge_urls']);
    add_action('flying_press_purge_pages:before', [__CLASS__, 'purge_pages']);
    add_action('flying_press_purge_everything:before', [__CLASS__, 'purge_everything']);
  }

  public static function verify_signature()
  {
    $config = Config::$config;

    if (!isset($config['cf_rules_signature'])) {
      return;
    }

    if ($config['cf_rules_signature'] === md5(self::$host)) {
      return;
    }

    Config::update_config(
      [
        'cf_cache_ruleset_id' => '',
        'cf_cache_rule_id' => '',
        'cf_cache_file_rule_id' => '',
        'cf_rewrite_ruleset_id' => '',
        'cf_rewrite_rule_id' => '',
        'cf_rules_version' => 0,
        'cf_rules_signature' => md5(self::$host),
        'cf_page_caching' => false,
      ],
      true
    );
  }

  public static function on_activation($plugin)
  {
    if (strpos($plugin, 'flying-press/flying-press.php') === false) {
      return;
    }
    if (self::is_config_ready()) {
      self::ensure_rules();
    } else {
      self::delete_rules();
    }
  }

  public static function on_deactivation()
  {
    self::delete_rules();
  }

  public static function on_upgrade()
  {
    if (!self::is_config_ready()) {
      return;
    }

    $current_rules_version = Config::$config['cf_rules_version'] ?? 0;
    if ($current_rules_version === self::RULES_VERSION) {
      return;
    }

    self::delete_rules(true);
    self::ensure_rules();
  }

  public static function on_config_updated($new, $prev)
  {
    $was_active =
      !empty($prev['cdn']) &&
      $prev['cdn_type'] === 'cloudflare' &&
      !empty($prev['cf_page_caching']);

    $now_active =
      !empty($new['cdn']) && $new['cdn_type'] === 'cloudflare' && !empty($new['cf_page_caching']);

    if (
      $was_active === $now_active &&
      $prev['cache_mobile'] === $new['cache_mobile'] &&
      $prev['cache_bypass_cookies'] === $new['cache_bypass_cookies']
    ) {
      return;
    }

    if ($now_active && self::is_config_ready()) {
      self::ensure_rules();
    } elseif (!$now_active) {
      self::delete_rules();
    }
    // else: active but config not ready → do nothing
  }

  private static function is_config_ready()
  {
    $config = Config::$config;
    return !empty($config['cdn']) &&
      $config['cdn_type'] === 'cloudflare' &&
      !empty($config['cf_zone_id']) &&
      !empty($config['cf_api_key']) &&
      !empty($config['cf_email']) &&
      !empty($config['cf_page_caching']);
  }

  private static function ensure_rules()
  {
    $cache_ruleset_id = self::get_or_create_ruleset('cache');
    $rewrite_ruleset_id = self::get_or_create_ruleset('rewrite');

    $cache_rule_id = self::get_or_create_rule($cache_ruleset_id, 'cache');
    $cache_file_rule_id = self::get_or_create_rule($cache_ruleset_id, 'cache_file');
    $rewrite_rule_id = self::get_or_create_rule($rewrite_ruleset_id, 'rewrite');

    if (
      $cache_ruleset_id &&
      $cache_rule_id &&
      $cache_file_rule_id &&
      $rewrite_ruleset_id &&
      $rewrite_rule_id
    ) {
      Config::update_config(
        [
          'cf_cache_ruleset_id' => $cache_ruleset_id,
          'cf_cache_rule_id' => $cache_rule_id,
          'cf_cache_file_rule_id' => $cache_file_rule_id,
          'cf_rewrite_ruleset_id' => $rewrite_ruleset_id,
          'cf_rewrite_rule_id' => $rewrite_rule_id,
          'cf_rules_version' => self::RULES_VERSION,
          'cf_rules_signature' => md5(self::$host),
        ],
        true
      );
    }
  }

  private static function delete_rule($ruleset_id, $rule_id)
  {
    if (empty($ruleset_id) || empty($rule_id)) {
      return;
    }

    return self::api_request('DELETE', "/rulesets/{$ruleset_id}/rules/{$rule_id}", [], false);
  }

  private static function delete_rules($delete_orphans = false)
  {
    $config = Config::$config;

    self::delete_rule($config['cf_cache_ruleset_id'], $config['cf_cache_rule_id']);
    self::delete_rule($config['cf_cache_ruleset_id'], $config['cf_cache_file_rule_id']);
    self::delete_rule($config['cf_rewrite_ruleset_id'], $config['cf_rewrite_rule_id']);

    // If ruleset and rule ids are set cleanup the config and return
    if (
      !empty($config['cf_cache_ruleset_id']) &&
      !empty($config['cf_cache_rule_id']) &&
      !empty($config['cf_cache_file_rule_id']) &&
      !empty($config['cf_rewrite_ruleset_id']) &&
      !empty($config['cf_rewrite_rule_id'])
    ) {
      Config::update_config(
        [
          'cf_cache_ruleset_id' => '',
          'cf_cache_rule_id' => '',
          'cf_cache_file_rule_id' => '',
          'cf_rewrite_ruleset_id' => '',
          'cf_rewrite_rule_id' => '',
          'cf_rules_version' => 0,
        ],
        true
      );
      return;
    }

    if ($delete_orphans) {
      foreach (['http_request_cache_settings', 'http_request_transform'] as $phase) {
        $ruleset = self::api_request('GET', "/rulesets/phases/{$phase}/entrypoint") ?: [];
        if (empty($ruleset['id'])) {
          continue;
        }

        foreach ($ruleset['rules'] ?? [] as $rule) {
          if (!empty($rule['id']) && strpos($rule['description'] ?? '', 'FlyingPress') !== false) {
            self::delete_rule($ruleset['id'], $rule['id']);
          }
        }
      }
    }
  }

  private static function api_request($method, $endpoint, $body = [], $blocking = true)
  {
    $config = Config::$config;
    if (
      empty($config['cf_zone_id']) ||
      empty($config['cf_email']) ||
      empty($config['cf_api_key'])
    ) {
      return null;
    }

    $url = "https://api.cloudflare.com/client/v4/zones/{$config['cf_zone_id']}{$endpoint}";
    $resp = wp_remote_request($url, [
      'method' => $method,
      'blocking' => $blocking,
      'headers' => [
        'X-Auth-Email' => $config['cf_email'],
        'X-Auth-Key' => $config['cf_api_key'],
        'Content-Type' => 'application/json',
      ],
      'body' => $body ? wp_json_encode($body) : null,
    ]);

    if (!$blocking) {
      return null;
    }
    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
      error_log("Cloudflare API error [{$method} {$endpoint}]: " . wp_remote_retrieve_body($resp));
      return null;
    }
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    return $json['result'] ?? null;
  }

  private static function get_or_create_ruleset($type)
  {
    $config = Config::$config;

    $phase = $type === 'cache' ? 'http_request_cache_settings' : 'http_request_transform';
    $key = $type === 'cache' ? 'cf_cache_ruleset_id' : 'cf_rewrite_ruleset_id';

    if (!empty($config[$key])) {
      return $config[$key];
    }

    $ruleset = self::api_request('GET', "/rulesets/phases/{$phase}/entrypoint") ?: [];
    if (!empty($ruleset['id'])) {
      return $ruleset['id'];
    }

    $created =
      self::api_request('POST', '/rulesets', [
        'kind' => 'zone',
        'phase' => $phase,
        'name' => $type . ' ruleset',
      ]) ?:
      [];

    return $created['id'] ?? null;
  }

  private static function get_or_create_rule($ruleset_id, $type)
  {
    if (!$ruleset_id) {
      return null;
    }

    $config = Config::$config;

    if ($type === 'cache') {
      $payload = self::cache_rule_payload();
      $rule_id_key = 'cf_cache_rule_id';
    } elseif ($type === 'cache_file') {
      $payload = self::cache_file_rule_payload();
      $rule_id_key = 'cf_cache_file_rule_id';
    } else {
      $payload = self::rewrite_rule_payload();
      $rule_id_key = 'cf_rewrite_rule_id';
    }

    $payload['ref'] = md5(self::$host . '-' . $rule_id_key);

    $rule_id = $config[$rule_id_key] ?? '';
    if ($rule_id) {
      if (isset($payload['position'])) {
        unset($payload['position']);
      }
      self::api_request('PATCH', "/rulesets/{$ruleset_id}/rules/{$rule_id}", $payload, false);
      return $rule_id;
    }

    $created = self::api_request('POST', "/rulesets/{$ruleset_id}/rules", $payload) ?: [];
    foreach ($created['rules'] ?? [] as $rule) {
      if (($rule['ref'] ?? '') === $payload['ref']) {
        return $rule['id'] ?? null;
      }
    }

    return null;
  }

  public static function purge_urls($urls)
  {
    $files = [];
    foreach ($urls as $url) {
      $files[] = ['url' => $url, 'headers' => ['CF-Device-Type' => 'desktop']];
      $files[] = ['url' => $url, 'headers' => ['CF-Device-Type' => 'mobile']];
      $files[] = ['url' => $url, 'headers' => ['CF-Device-Type' => 'tablet']];
    }

    // Sending in batch to respect CF purge limits
    foreach (array_chunk($files, 100) as $batch) {
      self::api_request('POST', '/purge_cache', ['files' => $batch], false);
    }
  }

  public static function purge_pages()
  {
    self::api_request('POST', '/purge_cache', ['tags' => [self::$host]], false);
  }

  public static function purge_everything()
  {
    self::api_request('POST', '/purge_cache', ['purge_everything' => true], false);
  }

  private static function cache_rule_payload()
  {
    $config = Config::$config;
    $cookies = array_values(
      array_unique(
        array_filter(['wordpress_logged_in', ...((array) ($config['cache_bypass_cookies'] ?? []))])
      )
    );

    $expr_parts = array_map(fn($cookie) => ' not http.cookie contains "' . $cookie . '"', $cookies);

    return [
      'action' => 'set_cache_settings',
      'expression' => '(http.request.uri contains "/" and' . implode(' and', $expr_parts) . ')',
      'description' => 'FlyingPress cache page',
      'enabled' => true,
      'position' => ['index' => 1],
      'action_parameters' => [
        'cache' => true,
        'browser_ttl' => ['mode' => 'respect_origin'],
        'edge_ttl' => ['mode' => 'bypass_by_default'],
        'cache_key' => [
          'cache_by_device_type' => !empty($config['cache_mobile']),
          'cache_deception_armor' => true,
        ],
      ],
    ];
  }

  private static function cache_file_rule_payload()
  {
    return [
      'action' => 'set_cache_settings',
      'expression' =>
        '(http.request.uri.path.extension in {"7z" "avi" "avif" "apk" "bin" "bmp" "bz2" "class" "css" "csv" "doc" "docx" "dmg" "ejs" "eot" "eps" "exe" "flac" "gif" "gz" "ico" "iso" "jar" "jpg" "jpeg" "js" "mid" "midi" "mkv" "mp3" "mp4" "ogg" "otf" "pdf" "pict" "pls" "png" "ppt" "pptx" "ps" "rar" "svg" "svgz" "swf" "tar" "tif" "tiff" "ttf" "webm" "webp" "woff" "woff2" "xls" "xlsx" "zip" "zst"})',
      'description' => 'FlyingPress cache static files',
      'action_parameters' => [
        'cache' => true,
        'browser_ttl' => ['mode' => 'override_origin', 'default' => 31536000],
        'edge_ttl' => ['mode' => 'override_origin', 'default' => 31536000],
      ],
      'enabled' => true,
    ];
  }

  private static function rewrite_rule_payload()
  {
    $params = apply_filters('flying_press_ignore_queries', Caching::$default_ignore_queries);

    $expr_parts = array_map(
      fn($param) => "(http.request.uri.query contains \"{$param}\")",
      $params
    );

    return [
      'action' => 'rewrite',
      'action_parameters' => [
        'uri' => [
          'query' => [
            'expression' =>
              'regex_replace( http.request.uri.query, "((^|&)(' .
              implode('|', $params) .
              ')=[^&]*)+&?", "" )',
          ],
        ],
      ],
      'description' => 'FlyingPress cache ignore query params',
      'enabled' => true,
      'position' => ['index' => 1],
      'expression' => implode(' or ', $expr_parts),
    ];
  }
}
