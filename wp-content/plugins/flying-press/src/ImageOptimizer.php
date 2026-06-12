<?php

namespace FlyingPress;

class ImageOptimizer
{
  const API_URL = 'https://image-optimizer.flyingpress.com/optimizer/';
  const META_KEY = 'flying_press_image_optimizer_data';
  const MIME_OPTIMIZABLE = ['image/jpeg', 'image/jpg', 'image/png'];
  const MIME_ALL = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/avif'];

  private static $optimize_queue, $restore_queue, $delete_queue;
  private static $wpdb, $excludes;

  public static function init()
  {
    global $wpdb;
    self::$wpdb = $wpdb;

    add_action('flying_press_optimize_image', [__CLASS__, 'optimize_single_image']);
    add_action('flying_press_restore_image', [__CLASS__, 'restore_single_image']);
    add_action('flying_press_delete_image', [__CLASS__, 'delete_original_image']);
    add_action('flying_press_purge_and_preload', [__CLASS__, 'purge_and_preload_cache']);
    add_filter('wp_generate_attachment_metadata', [__CLASS__, 'auto_optimize_images'], 10, 3);
    add_action('delete_attachment', [__CLASS__, 'delete_related_optimized_images']);
    add_filter('wp_prepare_attachment_for_js', [__CLASS__, 'individual_image_stats'], 10, 2);
    add_action('add_meta_boxes_attachment', [__CLASS__, 'individual_image_stats_meta_box']);
    add_action('admin_footer-upload.php', [__CLASS__, 'set_stats_in_media_modal']);

    self::$optimize_queue = new Queue('image-optimize', 'flying_press_optimize_image', 3);
    self::$restore_queue = new Queue('image-restore', 'flying_press_restore_image', 3);
    self::$delete_queue = new Queue('image-delete', 'flying_press_delete_image', 3);

    self::$excludes = Config::$config['image_optimizer_excludes'];
  }

  public static function optimize_single_image($task)
  {
    if (!empty($task['purge_and_preload_cache'])) {
      self::purge_and_preload_cache();
      return;
    }

    $image_id = (int) ($task['image_id'] ?? 0);
    if (!$image_id) {
      return;
    }

    $metadata = wp_get_attachment_metadata($image_id);
    if (empty($metadata)) {
      return;
    }

    $optimized = [];
    $optimization_data = get_post_meta($image_id, self::META_KEY, true) ?: [];

    // Optimize each image variant
    foreach (self::get_optimizable_image_sizes($image_id, $metadata, $optimization_data) as $size) {
      $image_url = wp_get_attachment_image_url($image_id, $size);

      // If the image keyword is excluded, skip optimization for this image
      if (Utils::any_keywords_match_string(self::$excludes, basename($image_url))) {
        break;
      }

      $image_path = self::get_image_data($image_url, 'original', 'path');

      if (!is_file($image_path)) {
        $optimization_data['unoptimized'][$size] = 'File does not exist';
        continue;
      }

      $original_mime = wp_get_image_mime($image_path);
      if (!in_array($original_mime, self::MIME_OPTIMIZABLE, true)) {
        $optimization_data['unoptimized'][$size] = 'Unsupported image format: ' . $original_mime;
        continue;
      }

      $already_optimized = $optimized[$image_url] ?? null;

      $response =
        $optimization_data['optimized_data'][$already_optimized] ??
        self::fetch_optimized_image($image_url);

      $status = (int) ($response['code'] ?? 200);

      if ($status >= 500 || !in_array($status, [413, 422, 200, 415], true)) {
        throw new \Exception("Optimizer API Error {$status}: " . ($response['error'] ?? 'Unknown'));
      }

      // Mark image as unoptimizable
      if (isset($response['error'])) {
        $optimization_data['unoptimized'][$size] = $response['error'];
        continue;
      }

      // Rename original image to "[filename].flying-press-original.[ext]" if optimized with original format
      if (!$already_optimized && 'original' === Config::$config['image_format']) {
        self::rename_files([$image_path]);
      }

      $optimized_data = self::get_image_data($image_url, 'optimized');

      // Save optimized image
      if (
        isset($response['body']) &&
        file_put_contents($optimized_data['path'], $response['body']) === false
      ) {
        throw new \Exception("Failed to save optimized image for URL: {$image_url}");
      }
      unset($response['body']);

      $optimization_data['replacements'][$image_url] = $optimized_data['url'];
      $optimization_data['optimized_data'][$size] = array_merge($response, $optimized_data);

      $optimized[$image_url] = $size;
    }

    self::update_image_metadata($image_id, $optimization_data, $metadata);
    self::replace_images($optimization_data['replacements'] ?? []);
  }

  private static function get_optimizable_image_sizes($image_id, $metadata, $optimization_data)
  {
    $all_sizes = array_merge(['full'], array_keys($metadata['sizes'] ?? []));

    // if compresssion level changed then allow all image for optimization
    if (
      Config::$config['image_compression_type'] !==
      ($optimization_data['compression_level'] ?? '')
    ) {
      delete_post_meta($image_id, self::META_KEY);
      return $all_sizes;
    }

    return array_diff($all_sizes, array_keys($optimization_data['sizes_unoptimized'] ?? []));
  }

  private static function fetch_optimized_image($image_url)
  {
    $response = wp_remote_post(self::API_URL, [
      'headers' => ['Content-Type' => 'application/json'],
      'body' => json_encode([
        'image_url' => $image_url,
        'config' => Config::safe_config(),
      ]),
      'timeout' => 60,
    ]);

    $status = wp_remote_retrieve_response_code($response);

    if (is_wp_error($response) || $status !== 200) {
      $error_message = is_wp_error($response)
        ? $response->get_error_message()
        : wp_remote_retrieve_response_message($response);
      $response_body = json_decode(wp_remote_retrieve_body($response), true);

      return [
        'error' => $response_body['message'] ?? $error_message,
        'code' => $status ?: 500,
      ];
    }

    return [
      'size' => wp_remote_retrieve_header($response, 'x-optimized-size'),
      'mime_type' => wp_remote_retrieve_header($response, 'content-type'),
      'body' => wp_remote_retrieve_body($response),
    ];
  }

  public static function restore_single_image($task)
  {
    if (!empty($task['purge_and_preload_cache'])) {
      self::purge_and_preload_cache();
      return;
    }

    $image_id = (int) ($task['image_id'] ?? 0);
    if (!$image_id) {
      return;
    }

    $optimizer_data = get_post_meta($image_id, self::META_KEY, true);
    if (empty($optimizer_data)) {
      return;
    }

    $replacements = $original_files = $optimized_files = [];
    $original_data = $optimizer_data['original_data'] ?? [];
    $original_ext = pathinfo($original_data['file'] ?? '', PATHINFO_EXTENSION);

    $guid = '';
    foreach ($optimizer_data['sizes_optimized'] ?? [] as $size) {
      $optimized_url = wp_get_attachment_image_url($image_id, $size);
      $optimized_ext = pathinfo($optimized_url, PATHINFO_EXTENSION);
      $original_url = self::change_extesion($optimized_url, $original_ext);

      if ($size === 'full') {
        $guid = $original_url;
      }

      $original_path = self::get_image_data($original_url, 'original', 'path');
      $optimized_files[] = self::change_extesion($original_path, $optimized_ext);
      $restoring_original = $original_ext === $optimized_ext;

      if ($restoring_original) {
        $original_url = self::change_extesion(
          $original_url,
          'flying-press-original.' . $original_ext
        );
        $original_files[] = self::change_extesion(
          $original_path,
          'flying-press-original.' . $original_ext
        );
      }

      $replacements[$optimized_url] = !$restoring_original ? $original_url : '';
    }

    self::delete_files($optimized_files);
    self::rename_files($original_files, true);

    // Restore original data
    if (!empty($original_data)) {
      self::update_guid($image_id, $guid);
      update_attached_file($image_id, $original_data['file']);
      wp_update_attachment_metadata($image_id, $original_data);
    }

    // Keep only unoptimizable record, otherwise remove optimization meta
    if (empty($optimizer_data['sizes_unoptimized'])) {
      delete_post_meta($image_id, self::META_KEY);
    } else {
      update_post_meta($image_id, self::META_KEY, [
        'compression_level' => $optimizer_data['compression_level'],
        'sizes_unoptimized' => $optimizer_data['sizes_unoptimized'],
      ]);
    }

    self::replace_images($replacements);
  }

  private static function update_image_metadata($image_id, $optimization_data, $metadata)
  {
    if (empty($optimization_data)) {
      return;
    }

    $optimized_data = $optimization_data['optimized_data'] ?? [];
    $prev_optimized_data = get_post_meta($image_id, self::META_KEY, true);

    // Always save flyingpress optimization data
    update_post_meta($image_id, self::META_KEY, [
      'status' => 'optimized',
      'compression_level' => Config::$config['image_compression_type'],
      'sizes_unoptimized' => array_merge(
        $optimization_data['unoptimized'] ?? [],
        $prev_optimized_data['sizes_unoptimized'] ?? []
      ),
      'sizes_optimized' => array_keys($optimized_data),
      'original_data' => $metadata,
    ]);

    if (empty($optimized_data)) {
      return;
    }

    // Update main file
    $main_file = $optimized_data['full'] ?? [];
    if (!empty($main_file) && !isset($main_file['error'])) {
      self::update_guid($image_id, $main_file['url']);
      update_attached_file($image_id, $main_file['relative_path']);

      $metadata['file'] = $main_file['relative_path'];
      $metadata['filesize'] = $main_file['size'];
    }

    // Update image metadata
    foreach ($metadata['sizes'] as $size_name => &$size_data) {
      if (!isset($optimized_data[$size_name]) || isset($optimized_data[$size_name]['error'])) {
        continue;
      }

      $size_data['file'] = basename($optimized_data[$size_name]['relative_path']);
      $size_data['mime-type'] = $optimized_data[$size_name]['mime_type'];
      $size_data['filesize'] = $optimized_data[$size_name]['size'];
    }

    unset($size_data);
    wp_update_attachment_metadata($image_id, $metadata);
  }

  private static function update_guid($image_id, $guid)
  {
    if (empty($image_id) || empty($guid) || get_post_field('guid', $image_id) === $guid) {
      return;
    }

    self::$wpdb->update(
      self::$wpdb->posts,
      ['guid' => $guid, 'post_mime_type' => 'image/' . pathinfo($guid, PATHINFO_EXTENSION)],
      ['ID' => $image_id]
    );
  }

  private static function replace_images_in_post_content($replacements)
  {
    if (empty($replacements)) {
      return [];
    }

    $like = array_map(
      fn($s) => self::$wpdb->prepare(
        'post_content LIKE %s',
        '%"' . self::$wpdb->esc_like($s) . '"%'
      ),
      array_keys($replacements)
    );

    $post_types = array_values(get_post_types(['public' => true]));
    $post_types_in = implode(',', array_fill(0, count($post_types), '%s'));

    $sql = self::$wpdb->prepare(
      'SELECT ID, post_content FROM ' .
        self::$wpdb->posts .
        ' WHERE (' .
        implode(' OR ', $like) .
        ") AND post_type IN ($post_types_in) AND post_status = 'publish' ORDER BY post_date DESC LIMIT 100",
      ...$post_types
    );

    $posts = self::$wpdb->get_results($sql);
    $affected_post_ids = [];
    $patterns = array_map(
      fn($url) => '/' . preg_quote($url, '/') . '(?=([^0-9A-Za-z]|$))/',
      array_keys($replacements)
    );

    foreach ($posts ?? [] as $post) {
      $updated_content = preg_replace($patterns, $replacements, $post->post_content);

      if ($updated_content !== $post->post_content) {
        self::$wpdb->update(
          self::$wpdb->posts,
          ['post_content' => $updated_content],
          ['ID' => $post->ID]
        );
        $affected_post_ids[] = $post->ID;
      }
    }

    return $affected_post_ids;
  }

  private static function replace_images_in_postmeta($replacements)
  {
    if (empty($replacements)) {
      return [];
    }

    $like = [];

    foreach ($replacements as $search => $replace) {
      $like[] = self::$wpdb->prepare(
        'pm.meta_value LIKE %s',
        '%"' . self::$wpdb->esc_like($search) . '"%'
      );
      $like[] = self::$wpdb->prepare(
        'pm.meta_value REGEXP %s',
        self::$wpdb->esc_like(str_replace('/', '\/', $search)) . '([^0-9A-Za-z]|$)'
      );
    }

    $post_types = array_values(get_post_types(['public' => true]));
    $post_types_in = implode(',', array_fill(0, count($post_types), '%s'));
    $posts = self::$wpdb->posts;
    $postmeta = self::$wpdb->postmeta;

    $sql = self::$wpdb->prepare(
      "SELECT pm.meta_id, pm.post_id, pm.meta_value FROM $postmeta pm INNER JOIN $posts p ON pm.post_id = p.ID WHERE (" .
        implode(' OR ', $like) .
        ") AND pm.meta_key NOT IN ('_wp_attached_file', '_wp_attachment_metadata', 'flying_press_image_optimizer_data', '_wp_attachment_backup_sizes') AND p.post_status = 'publish' AND p.post_type IN ($post_types_in)
      ORDER BY pm.meta_id DESC LIMIT 200",
      ...$post_types
    );

    $results = self::$wpdb->get_results($sql, ARRAY_A);
    $affected_post_ids = $updates = [];

    foreach ($results ?? [] as $row) {
      $meta_value = $row['meta_value'];

      if (is_serialized($meta_value)) {
        $data = maybe_unserialize($meta_value);
        $updated_data = maybe_serialize(self::recursive_replace($data, $replacements));
      } else {
        $decoded = json_decode($meta_value, true);
        $updated_data =
          json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? json_encode(self::recursive_replace($decoded, $replacements))
            : str_replace(array_keys($replacements), $replacements, $meta_value);
      }

      if ($updated_data !== $meta_value) {
        $updates[$row['meta_id']] = $updated_data;
        $affected_post_ids[] = $row['post_id'];
      }
    }

    // Update images from postmeta
    foreach ($updates ?? [] as $meta_id => $meta_val) {
      self::$wpdb->update(
        self::$wpdb->postmeta,
        ['meta_value' => $meta_val],
        ['meta_id' => $meta_id]
      );
    }

    return $affected_post_ids;
  }

  private static function replace_images($replacements)
  {
    self::replace_images_in_post_content($replacements);
    self::replace_images_in_postmeta($replacements);
  }

  private static function recursive_replace($data, $replacements)
  {
    // Patterns for exact match.
    $patterns = array_map(
      fn($url) => '/' . preg_quote($url, '/') . '(?=([^0-9A-Za-z]|$))/',
      array_keys($replacements)
    );

    if (is_string($data)) {
      // Exact replacements.
      return preg_replace($patterns, $replacements, $data);
    }

    if (is_array($data)) {
      foreach ($data as $key => $value) {
        $data[$key] = self::recursive_replace($value, $replacements);
      }
    } elseif (is_object($data)) {
      foreach ($data as $key => $value) {
        $data->$key = self::recursive_replace($value, $replacements);
      }
    }

    return $data;
  }

  public static function delete_original_image($task)
  {
    $image_id = (int) ($task['image_id'] ?? 0);
    if (!$image_id) {
      return;
    }

    $optimizer_data = get_post_meta($image_id, self::META_KEY, true);
    self::delete_files(self::get_original_image_paths($image_id, $optimizer_data));

    $optimizer_data['original_deleted'] = 1;
    update_post_meta($image_id, self::META_KEY, $optimizer_data);
  }

  public static function optimize_images()
  {
    $queue_args = [
      'post_mime_type' => self::MIME_OPTIMIZABLE,
      'meta_query' => [
        'relation' => 'OR',
        ['key' => self::META_KEY, 'compare' => 'NOT EXISTS'],
        [
          'relation' => 'AND',
          ['key' => self::META_KEY, 'compare' => 'LIKE', 'value' => ':"sizes_unoptimized"'],
          ['key' => self::META_KEY, 'compare' => 'NOT LIKE', 'value' => ':"original_deleted"'],
          ['key' => self::META_KEY, 'compare' => 'NOT LIKE', 'value' => ':"optimized"'],
        ],
      ],
    ];

    return self::process_queue(self::$optimize_queue, $queue_args);
  }

  public static function restore_images()
  {
    $queue_args = [
      'post_mime_type' => self::MIME_ALL,
      'meta_query' => [
        ['key' => self::META_KEY, 'compare' => 'NOT LIKE', 'value' => ':"original_deleted"'],
      ],
    ];

    return self::process_queue(self::$restore_queue, $queue_args);
  }

  public static function delete_original_images()
  {
    $queue_args = [
      'post_mime_type' => self::MIME_ALL,
      'meta_query' => [
        ['key' => self::META_KEY, 'compare' => 'NOT LIKE', 'value' => ':"original_deleted"'],
      ],
    ];

    return self::process_queue(self::$delete_queue, $queue_args, 20);
  }

  private static function process_queue($queue, $args, $priority = 15)
  {
    $queue->clear_queue();
    wp_suspend_cache_addition(true);

    $paged = 1;
    $added_to_queue = false;

    do {
      $query = new \WP_Query(
        array_merge($args, [
          'post_type' => 'attachment',
          'post_status' => 'inherit',
          'posts_per_page' => 2000,
          'paged' => $paged++,
          'fields' => 'ids',
          'no_found_rows' => true,
          'update_post_meta_cache' => false,
          'update_post_term_cache' => false,
        ])
      );

      if (empty($query->posts)) {
        break;
      }

      $added_to_queue = self::queue_images($query->posts, $queue, $priority);
    } while ($query->have_posts());

    wp_suspend_cache_addition(false);

    if (!$added_to_queue) {
      return ['success' => $added_to_queue];
    }

    if ($queue !== self::$delete_queue) {
      // Sentinel task in the same queue ensures purge+preload runs after bulk optimize/restore.
      $queue->add_task(['purge_and_preload_cache' => true], 999);
    }

    $queue->start_queue();
    return ['success' => $added_to_queue];
  }

  private static function queue_images($image_ids, $queue = '', $priority = 15)
  {
    $queue = $queue ?: self::$optimize_queue;
    $image_ids = apply_filters('flying_press_optimization_image_ids', $image_ids);

    foreach ($image_ids ?? [] as $image_id) {
      $queue->add_task(['image_id' => $image_id], $priority);
    }

    return count($image_ids);
  }

  public static function purge_and_preload_cache($task = [])
  {
    wp_cache_flush();
    Purge::purge_pages();
    Preload::preload_cache();
  }

  public static function get_status()
  {
    self::$wpdb->query('SET SESSION sql_big_selects = 1');

    $total_original_size = $total_optimized_size = $total_optimized = $total_images = $non_optimizable = 0;
    $batch_size = 1000;
    $last_id = PHP_INT_MAX;
    $mime_in = "'" . implode("','", self::MIME_ALL) . "'";
    $posts = self::$wpdb->posts;
    $postmeta = self::$wpdb->postmeta;

    while (true) {
      $results = self::$wpdb->get_results(
        self::$wpdb->prepare(
          "SELECT p.ID, p.post_mime_type, pm.meta_value as attachment_metadata, pm1.meta_value as optimizer_data
            FROM $posts p
            LEFT JOIN $postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_metadata'
            LEFT JOIN $postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = %s
            WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type IN ($mime_in) AND p.ID < %d
            ORDER BY p.ID DESC LIMIT %d",
          self::META_KEY,
          $last_id,
          $batch_size
        ),
        ARRAY_A
      );

      if (empty($results)) {
        break;
      }

      foreach ($results as $row) {
        $last_id = $row['ID'];
        $meta = maybe_unserialize($row['attachment_metadata']);
        if (empty($meta)) {
          continue;
        }

        $opt_data = maybe_unserialize($row['optimizer_data']) ?: [];
        $orig_meta = $opt_data['original_data'] ?? $meta;

        $orig_stats = self::calculate_image_stats($orig_meta);
        $total_images += $orig_stats['count'];
        $total_original_size += $orig_stats['total_size'];

        if (isset($opt_data['status'])) {
          $opt_stats = self::calculate_image_stats($meta);
          $total_optimized += $opt_stats['count'] ?? 0;
          $total_optimized_size += $opt_stats['total_size'];
        } elseif (!in_array($row['post_mime_type'], self::MIME_OPTIMIZABLE, true)) {
          $non_optimizable += count($meta['sizes'] ?? []) + 1;
        }
      }
    }

    return [
      'total_images' => $total_images - $non_optimizable,
      'processed_images' => $total_optimized,
      'original_size' => $total_original_size,
      'optimized_size' => $total_optimized_size,
      'images_in_queue' => (int) self::$optimize_queue->get_pending_count(),
      'restore_in_queue' => (int) self::$restore_queue->get_pending_count(),
      'delete_in_queue' => (int) self::$delete_queue->get_pending_count(),
    ];
  }

  public static function stop_optimization()
  {
    self::$optimize_queue->clear_queue();
    return ['success' => true];
  }

  public static function auto_optimize_images($metadata, $attachment_id, $context)
  {
    if (
      !Config::$config['image_auto_optimize_uploads'] ||
      $context !== 'create' ||
      !in_array(get_post_mime_type($attachment_id), self::MIME_OPTIMIZABLE)
    ) {
      return $metadata;
    }

    self::queue_images([$attachment_id]);
    return $metadata;
  }

  public static function delete_related_optimized_images($image_id)
  {
    self::delete_files(self::get_original_image_paths($image_id));
  }

  public static function individual_image_stats($response, $attachment)
  {
    $original_mime = self::get_image_original_mime($attachment->ID) ?? $response['mime'];
    if (!in_array($original_mime, self::MIME_OPTIMIZABLE, true)) {
      return $response;
    }

    $response['flying_press_image_optimizer'] = self::get_image_stats_html($attachment);
    return $response;
  }

  public static function individual_image_stats_meta_box($post)
  {
    $original_mime = self::get_image_original_mime($post->ID) ?? $post->post_mime_type;
    if (!in_array($original_mime, self::MIME_OPTIMIZABLE, true)) {
      return;
    }

    add_meta_box(
      'flying_press_image_optimizer',
      'FlyingPress optimization',
      function ($post) {
        echo self::get_image_stats_html($post);
      },
      'attachment',
      'side'
    );
  }

  private static function get_image_stats_html($post)
  {
    $optimizer_data = get_post_meta($post->ID, self::META_KEY, true);

    // If not optimized or excluded
    if (empty($optimizer_data['status'])) {
      $status = Utils::any_keywords_match_string(self::$excludes, basename($post->guid))
        ? __('Excluded from Optimization', 'flying-press')
        : __('Not Optimized yet', 'flying-press');

      return sprintf(
        '<div class="status"><strong>%s:</strong> %s</div>',
        __('Status', 'flying-press'),
        $status
      );
    }

    // Status
    $status = empty($optimizer_data['sizes_optimized'])
      ? __('Not Optimized', 'flying-press')
      : __('Optimized', 'flying-press');
    $html = sprintf(
      '<div class="status"><strong>%s:</strong> %s</div>',
      __('Status', 'flying-press'),
      $status
    );

    // Before/After size
    if (!empty($optimizer_data['sizes_optimized'])) {
      $optimized_stat = self::calculate_image_stats(wp_get_attachment_metadata($post->ID));
      $original_stat = self::calculate_image_stats($optimizer_data['original_data']);

      $html .= sprintf(
        '<div class="total-size"><strong>%s:</strong> %s &rarr; %s</div>',
        __('Total Size', 'flying-press'),
        size_format($original_stat['total_size'], 1),
        size_format($optimized_stat['total_size'], 1)
      );
    }

    if (empty($optimizer_data['sizes_unoptimized'])) {
      return $html;
    }

    // Reasons for unoptimized sizes
    $html .= sprintf(
      '<div class="sizes-unoptimized"><strong>%s:</strong></div>',
      __('Sizes not optimized', 'flying-press')
    );

    foreach ($optimizer_data['sizes_unoptimized'] as $size => $reason) {
      $size_meta = $optimizer_data['original_data']['sizes'][$size] ?? [];
      $dimention = ($size_meta['width'] ?? 0) . 'x' . ($size_meta['height'] ?? 0);

      $html .= sprintf(
        '<div style="margin:0.5em 0 0 1em;padding:0;"><code>%s</code>: %s</div>',
        $size === 'full' ? __('Full', 'flying-press') : $dimention,
        esc_html($reason)
      );
    }

    return $html;
  }

  public static function set_stats_in_media_modal()
  {
    echo '<script id="flying-press-stats-js">
      ((attachment) => {
        if (!attachment) return;
        const originalRender = attachment.prototype.render;
        attachment.prototype.render = function() {
          originalRender.apply(this, arguments);
          const html = this.model.get("flying_press_image_optimizer");
          if (!html) return;
          this.el.querySelector(".settings")?.insertAdjacentHTML(
            "beforebegin",
            `<div class="flying-press-image-stats details">${html}</div>`
          );
        };
      })(wp?.media?.view?.Attachment);
    </script>';
  }

  private static function delete_files($files)
  {
    foreach (array_unique($files) ?? [] as $file) {
      file_exists($file) && wp_delete_file($file);
    }
  }

  private static function rename_files($files, $restore = false)
  {
    foreach (array_unique($files) ?? [] as $file) {
      if (!file_exists($file)) {
        continue;
      }

      $ext = pathinfo($file, PATHINFO_EXTENSION);

      $new_file = $restore
        ? str_replace('.flying-press-original', '', $file)
        : self::change_extesion($file, 'flying-press-original.' . $ext);

      rename($file, $new_file);
    }
  }

  private static function calculate_image_stats($metadata)
  {
    $total_stats = [
      'total_size' => 0,
      'count' => 0,
    ];

    // Skip calculation if missing data or excluded
    if (
      empty($metadata['file']) ||
      Utils::any_keywords_match_string(self::$excludes, basename($metadata['file']))
    ) {
      return $total_stats;
    }

    $total_stats['total_size'] += (int) ($metadata['filesize'] ?? 0);
    $total_stats['count'] += (int) ($metadata['file'] ? 1 : 0);
    $sizes = $metadata['sizes'] ?? [];

    foreach ($sizes as $meta) {
      $total_stats['total_size'] += (int) ($meta['filesize'] ?? 0);
      $total_stats['count']++;
    }

    return $total_stats;
  }

  private static function get_original_image_paths($image_id, $optimizer_data = [])
  {
    $optimizer_data = $optimizer_data ?: get_post_meta($image_id, self::META_KEY, true);
    if (empty($optimizer_data['sizes_optimized'])) {
      return [];
    }

    $original_ext = pathinfo($optimizer_data['original_data']['file'] ?? '', PATHINFO_EXTENSION);

    if (empty($original_ext)) {
      return [];
    }

    $original_image_paths = [];

    // Add original (non-scaled) image when available
    if (!empty($optimizer_data['original_data']['original_image'])) {
      $original_image_paths[] = wp_get_original_image_path($image_id);
    }

    foreach ($optimizer_data['sizes_optimized'] as $size) {
      $optimized_url = wp_get_attachment_image_url($image_id, $size);
      $optimized_path = self::get_image_data($optimized_url, 'optimized', 'path');
      $original_path = self::change_extesion($optimized_path, $original_ext);
      $optimized_ext = pathinfo($optimized_url, PATHINFO_EXTENSION);

      $original_image_paths[] =
        $original_ext === $optimized_ext
          ? self::change_extesion($original_path, 'flying-press-original.' . $original_ext)
          : $original_path;
    }

    return $original_image_paths;
  }

  private static function get_image_data($original_image_url, $context = '', $field = 'all')
  {
    $uploads = wp_get_upload_dir();
    if (empty($uploads['baseurl']) || empty($uploads['basedir'])) {
      return;
    }

    $optimized_ext = Config::$config['image_format'];
    $image_url =
      $context === 'optimized' && $optimized_ext !== 'original'
        ? self::change_extesion($original_image_url, $optimized_ext)
        : $original_image_url;

    $file_path = str_starts_with($image_url, $uploads['baseurl'])
      ? wp_normalize_path(str_replace($uploads['baseurl'], $uploads['basedir'], $image_url))
      : '';

    $image_data = [
      'url' => $image_url,
      'path' => $file_path,
      'relative_path' => _wp_relative_upload_path($file_path),
    ];

    return $field === 'all' ? $image_data : $image_data[$field] ?? null;
  }

  private static function change_extesion($url_or_path, $new_extesion)
  {
    if (empty($url_or_path) || empty($new_extesion)) {
      return;
    }

    $original_ext = pathinfo($url_or_path, PATHINFO_EXTENSION);

    if ($original_ext === $new_extesion) {
      return $url_or_path;
    }

    return preg_replace('/\.[^\.\/]+$/', '.' . $new_extesion, $url_or_path);
  }

  // Get the original mime type of the image before optimization
  private static function get_image_original_mime($image_id)
  {
    $optimizer_data = get_post_meta($image_id, self::META_KEY, true);
    if (empty($optimizer_data) || empty($optimizer_data['original_data']['file'])) {
      return;
    }

    return 'image/' . pathinfo($optimizer_data['original_data']['file'], PATHINFO_EXTENSION);
  }
}
