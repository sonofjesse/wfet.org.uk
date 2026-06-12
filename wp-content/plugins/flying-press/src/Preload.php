<?php

namespace FlyingPress;

use FlyingPress\Config;

class Preload
{
  private static $queue;

  public static function init()
  {
    add_action('flying_press_preload_url', [__CLASS__, 'process_single_url'], 10, 1);

    $concurrency = defined('FLYING_PRESS_PRELOAD_CONCURRENCY')
      ? (int) FLYING_PRESS_PRELOAD_CONCURRENCY
      : 1;
    $concurrency = max(1, $concurrency);

    self::$queue = new Queue('preload-urls', 'flying_press_preload_url', 3, $concurrency);
  }

  public static function process_single_url($task)
  {
    $url = (string) ($task['url'] ?? '');
    $device = (string) ($task['device'] ?? 'desktop');
    $cookies = (string) ($task['cookies'] ?? '');

    if ($url === '') {
      return;
    }

    $user_agent = $device === 'mobile' ? Utils::$mobile_user_agent : Utils::$user_agent;

    $args = [
      'headers' => [
        'Range' => 'bytes=0-0',
        'x-flying-press-preload' => '1',
        'Cookie' => 'wordpress_logged_in_1=1;' . $cookies,
        'User-Agent' => $user_agent,
      ],
      'timeout' => 60,
      'sslverify' => false,
    ];

    // single, sequential request
    $response = wp_remote_get($url, $args);

    // normalize status
    $status =
      is_wp_error($response) || empty($response['response']['code'])
        ? 500
        : (int) $response['response']['code'];

    if ($status === 429 || $status >= 500) {
      throw new \Exception("HTTP {$status} error for URL: {$url}");
    }
  }

  public static function preload_urls($urls, $priority = 20, $cookies = '')
  {
    self::queue_urls($urls, $priority, $cookies);
    self::$queue->start_queue();
  }

  public static function preload_cache()
  {
    self::$queue->clear_queue();

    self::queue_urls([home_url()]);

    wp_suspend_cache_addition(true);

    $post_types = get_post_types(['public' => true, 'exclude_from_search' => false]);
    $paged = 1;

    do {
      $query = new \WP_Query([
        'post_status' => 'publish',
        'has_password' => false,
        'ignore_sticky_posts' => true,
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'order' => 'DESC',
        'orderby' => 'date',
        'post_type' => $post_types,
        'posts_per_page' => 10000, // Fetch 10k posts at a time
        'paged' => $paged,
        'fields' => 'ids', // Only get post IDs
      ]);

      $post_urls = [];
      foreach ($query->posts as $post_id) {
        $post_urls[] = get_permalink($post_id);
      }
      self::queue_urls($post_urls);

      $paged++;
    } while ($query->have_posts());

    // Fetch taxonomy URLs
    $taxonomies = get_taxonomies(['public' => true, 'rewrite' => true]);
    foreach ($taxonomies as $taxonomy) {
      $query_args = [
        'hide_empty' => true,
        'hierarchical' => false,
        'update_term_meta_cache' => false,
        'taxonomy' => $taxonomy,
      ];
      $terms = get_terms($query_args);
      $taxonomy_urls = [];
      foreach ($terms as $term) {
        $taxonomy_urls[] = get_term_link($term, $taxonomy);
      }
      self::queue_urls($taxonomy_urls);
    }

    // Fetch author URLs
    $user_ids = get_users([
      'role' => 'author',
      'count_total' => false,
      'fields' => 'ID',
    ]);

    $user_urls = [];
    foreach ($user_ids as $user_id) {
      $user_urls[] = get_author_posts_url($user_id);
    }
    self::queue_urls($user_urls);

    // Resume cache addition
    wp_suspend_cache_addition(false);

    self::$queue->start_queue();
  }

  private static function queue_urls($urls, $priority = 20, $cookies = '')
  {
    $urls = apply_filters('flying_press_preload_urls', $urls);

    foreach ($urls as $url) {
      if ('' === trim($url)) {
        continue;
      }

      if (!Caching::is_url_cacheable($url)) {
        continue;
      }

      self::$queue->add_task(
        ['url' => $url, 'device' => 'desktop', 'cookies' => $cookies],
        $priority
      );

      if (Config::$config['cache_mobile']) {
        self::$queue->add_task(
          ['url' => $url, 'device' => 'mobile', 'cookies' => $cookies],
          $priority
        );
      }
    }
  }

  public static function get_remaining_tasks_count()
  {
    return self::$queue->get_pending_count();
  }
}
