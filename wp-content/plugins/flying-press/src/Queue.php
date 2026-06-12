<?php

namespace FlyingPress;

class Queue
{
  private const REST_NAMESPACE = 'flying-press';
  private const REST_ROUTE = '/queue/run/';
  private const TASK_LOCK_TIMEOUT = 120;
  private const RUNNER_WINDOW_SECONDS = 20;
  private const DEFAULT_MAX_RETRIES = 3;
  private const DEFAULT_CONCURRENCY = 1;
  private const MAX_CONCURRENCY = 4;
  private const RETRY_BACKOFF_BASE_SECONDS = 30;
  private const RETRY_BACKOFF_MAX_SECONDS = 300;
  private const WATCHDOG_HOOK = 'flying_press_queue_watchdog';
  private const WATCHDOG_SCHEDULE = '1min';
  private const WATCHDOG_BATCH = 10;

  private static $queue_max_retries = [];
  private static $queue_concurrency = [];

  private $group_name;
  private $callback_action;

  public static function init()
  {
    add_action('flying_press_upgraded', [__CLASS__, 'maybe_create_table']);
    add_action('flying_press_upgraded', [__CLASS__, 'clear_legacy_action_scheduler_tasks']);
    add_action('init', [__CLASS__, 'setup_watchdog']);
    add_action(self::WATCHDOG_HOOK, [__CLASS__, 'run_watchdog']);
    add_action('rest_api_init', [__CLASS__, 'register_rest_route']);
    register_activation_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'maybe_create_table']);
    register_deactivation_hook(FLYING_PRESS_FILE_NAME, [__CLASS__, 'clear_watchdog']);
  }

  public function __construct(
    $group_name,
    $callback_action,
    $max_retries = self::DEFAULT_MAX_RETRIES,
    $concurrency = self::DEFAULT_CONCURRENCY
  ) {
    $this->group_name = $group_name;
    $this->callback_action = $callback_action;

    self::set_max_retries($group_name, $callback_action, $max_retries);
    self::set_concurrency($group_name, $callback_action, $concurrency);
  }

  public function add_task($task_data, $priority = 20)
  {
    global $wpdb;

    $table = self::table_name();
    $task_data = $this->normalize_task_data($task_data);
    $task_json = wp_json_encode($task_data);
    $task_hash = $this->build_task_hash($task_data);
    $priority = max(0, (int) $priority);

    $sql = $wpdb->prepare(
      "INSERT INTO $table
      (group_name, callback_action, task_data, task_hash, priority, status, created_at, updated_at)
      VALUES (%s, %s, %s, %s, %d, 'pending', UTC_TIMESTAMP(), UTC_TIMESTAMP())
      ON DUPLICATE KEY UPDATE
      id = LAST_INSERT_ID(id),
      priority = LEAST(priority, VALUES(priority)),
      status = IF(status = 'failed', 'pending', status),
      updated_at = UTC_TIMESTAMP()",
      $this->group_name,
      $this->callback_action,
      $task_json,
      $task_hash,
      $priority
    );

    $wpdb->query($sql);
    return (int) $wpdb->insert_id;
  }

  public function start_queue()
  {
    self::dispatch_runners($this->group_name, $this->callback_action);
  }

  public function get_pending_count()
  {
    global $wpdb;
    $table = self::table_name();

    return (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE group_name = %s AND callback_action = %s AND status IN ('pending', 'processing')",
        $this->group_name,
        $this->callback_action
      )
    );
  }

  public function clear_queue()
  {
    global $wpdb;
    $table = self::table_name();

    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM $table WHERE group_name = %s AND callback_action = %s",
        $this->group_name,
        $this->callback_action
      )
    );
  }

  public static function maybe_create_table()
  {
    global $wpdb;

    $table = self::table_name();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      group_name VARCHAR(32) NOT NULL,
      callback_action VARCHAR(64) NOT NULL,
      task_data LONGTEXT NOT NULL,
      task_hash CHAR(64) NOT NULL,
      priority INT NOT NULL DEFAULT 20,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      lock_token VARCHAR(64) NULL,
      attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      last_error TEXT NULL,
      created_at DATETIME NOT NULL,
      locked_at DATETIME NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY uniq_group_task (group_name, task_hash),
      KEY idx_runner (group_name, callback_action, status, priority, created_at, id),
      KEY idx_lock (status, locked_at)
    ) $charset_collate;";

    dbDelta($sql);
  }

  public static function setup_watchdog()
  {
    $next = wp_next_scheduled(self::WATCHDOG_HOOK);
    $schedule = $next ? wp_get_schedule(self::WATCHDOG_HOOK) : false;

    if (!$next || $schedule !== self::WATCHDOG_SCHEDULE) {
      self::clear_watchdog();
      wp_schedule_event(time(), self::WATCHDOG_SCHEDULE, self::WATCHDOG_HOOK);
    }
  }

  public static function clear_watchdog()
  {
    wp_clear_scheduled_hook(self::WATCHDOG_HOOK);
  }

  public static function delete_table()
  {
    global $wpdb;

    self::clear_watchdog();
    $wpdb->query('DROP TABLE IF EXISTS ' . self::table_name());
  }

  public static function clear_legacy_action_scheduler_tasks()
  {
    global $wpdb;

    $actions_table = $wpdb->prefix . 'actionscheduler_actions';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $actions_table)) !== $actions_table) {
      return;
    }

    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM $actions_table
        WHERE status = %s
          AND hook IN (%s, %s, %s, %s, %s)",
        'pending',
        'flying_press_preload_url',
        'flying_press_optimize_image',
        'flying_press_restore_image',
        'flying_press_delete_image',
        'flying_press_purge_and_preload'
      )
    );
  }

  public static function run_watchdog()
  {
    global $wpdb;

    $table = self::table_name();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    if (!$exists) {
      return;
    }

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT group_name, callback_action
        FROM $table
        GROUP BY group_name, callback_action
        HAVING
          SUM(
            CASE
              WHEN status = 'processing'
                AND locked_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)
              THEN 1 ELSE 0
            END
          ) = 0
          AND (
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) > 0
            OR SUM(
              CASE
                WHEN status = 'processing'
                  AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)
                THEN 1 ELSE 0
              END
            ) > 0
          )
        LIMIT %d",
        self::TASK_LOCK_TIMEOUT,
        self::TASK_LOCK_TIMEOUT,
        self::WATCHDOG_BATCH
      ),
      ARRAY_A
    );

    foreach ($rows as $row) {
      $group_name = sanitize_text_field((string) ($row['group_name'] ?? ''));
      $callback_action = sanitize_text_field((string) ($row['callback_action'] ?? ''));

      if ($group_name === '' || $callback_action === '') {
        continue;
      }

      self::dispatch_runners($group_name, $callback_action);
    }
  }

  public static function register_rest_route()
  {
    register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
      'methods' => 'POST',
      'permission_callback' => '__return_true',
      'callback' => [__CLASS__, 'run_queue_from_rest'],
    ]);
  }

  public static function run_queue_from_rest($request)
  {
    $group_name = sanitize_text_field((string) $request->get_param('group'));
    $callback_action = sanitize_text_field((string) $request->get_param('callback'));
    $token = sanitize_text_field((string) $request->get_param('token'));

    if (
      empty($group_name) ||
      empty($callback_action) ||
      !hash_equals(self::build_runner_token($group_name, $callback_action), $token)
    ) {
      return new \WP_Error('flying-press/invalid-queue-runner', 'Invalid runner request', [
        'status' => 403,
      ]);
    }

    $started_at = microtime(true);
    $processed = 0;
    while (microtime(true) - $started_at < self::RUNNER_WINDOW_SECONDS) {
      $task = self::claim_next_task($group_name, $callback_action);

      if (empty($task)) {
        break;
      }

      $processed++;
      self::process_task($task);
    }

    $has_more = self::has_pending_tasks($group_name, $callback_action);

    if ($has_more) {
      self::dispatch_runners($group_name, $callback_action);
    }

    return [
      'success' => true,
      'processed' => $processed,
      'has_more' => $has_more,
    ];
  }

  private static function claim_next_task($group_name, $callback_action)
  {
    global $wpdb;
    $table = self::table_name();

    $wpdb->query(
      $wpdb->prepare(
        "UPDATE $table
        SET status = 'pending', lock_token = NULL, locked_at = NULL, updated_at = UTC_TIMESTAMP()
        WHERE group_name = %s
          AND callback_action = %s
          AND status = 'processing'
          AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)",
        $group_name,
        $callback_action,
        self::TASK_LOCK_TIMEOUT
      )
    );

    $lock_token = wp_generate_password(32, false, false);

    $wpdb->query(
      $wpdb->prepare(
        "UPDATE $table
        SET status = 'processing',
          lock_token = %s,
          locked_at = UTC_TIMESTAMP(),
          attempts = attempts + 1,
          updated_at = UTC_TIMESTAMP()
        WHERE group_name = %s
          AND callback_action = %s
          AND status = 'pending'
          AND (locked_at IS NULL OR locked_at <= UTC_TIMESTAMP())
        ORDER BY priority ASC, created_at ASC, id ASC
        LIMIT 1",
        $lock_token,
        $group_name,
        $callback_action
      )
    );

    if (!$wpdb->rows_affected) {
      return null;
    }

    return $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $table WHERE lock_token = %s LIMIT 1", $lock_token),
      ARRAY_A
    );
  }

  private static function process_task($task)
  {
    global $wpdb;

    $table = self::table_name();
    $task_data = json_decode($task['task_data'], true);
    $task_data = is_array($task_data) ? $task_data : [];

    try {
      do_action_ref_array($task['callback_action'], [$task_data]);

      usleep((int) round((float) apply_filters('flying_press_preload_delay', 0.5) * 1_000_000));

      $wpdb->query(
        $wpdb->prepare(
          "DELETE FROM $table WHERE id = %d AND lock_token = %s",
          (int) $task['id'],
          $task['lock_token']
        )
      );
    } catch (\Throwable $e) {
      $group_name = (string) ($task['group_name'] ?? '');
      $callback_action = (string) ($task['callback_action'] ?? '');
      $attempts = (int) ($task['attempts'] ?? 0);
      $max_retries = self::get_max_retries($group_name, $callback_action);
      $last_error = substr($e->getMessage(), 0, 1000);

      if ($attempts < $max_retries) {
        $retry_backoff_seconds = self::get_retry_backoff_seconds($attempts);

        $wpdb->query(
          $wpdb->prepare(
            "UPDATE $table
            SET status = 'pending',
              lock_token = NULL,
              locked_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND),
              last_error = %s,
              updated_at = UTC_TIMESTAMP()
            WHERE id = %d",
            $retry_backoff_seconds,
            $last_error,
            (int) $task['id']
          )
        );
      } else {
        $wpdb->query(
          $wpdb->prepare(
            "UPDATE $table
            SET status = 'failed',
              lock_token = NULL,
              locked_at = NULL,
              last_error = %s,
              updated_at = UTC_TIMESTAMP()
            WHERE id = %d",
            $last_error,
            (int) $task['id']
          )
        );
      }

      usleep((int) round((float) apply_filters('flying_press_preload_delay', 0.5) * 1_000_000));
    }
  }

  private static function has_pending_tasks($group_name, $callback_action)
  {
    global $wpdb;

    $table = self::table_name();
    $count = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*)
        FROM $table
        WHERE group_name = %s
          AND callback_action = %s
          AND (
            status = 'processing'
            OR (status = 'pending' AND (locked_at IS NULL OR locked_at <= UTC_TIMESTAMP()))
          )",
        $group_name,
        $callback_action
      )
    );

    return $count > 0;
  }

  private static function get_max_retries($group_name, $callback_action)
  {
    $key = self::queue_config_key($group_name, $callback_action);
    $max_retries = self::$queue_max_retries[$key] ?? self::DEFAULT_MAX_RETRIES;

    return max(0, $max_retries);
  }

  private static function get_concurrency($group_name, $callback_action)
  {
    $key = self::queue_config_key($group_name, $callback_action);
    $concurrency = self::$queue_concurrency[$key] ?? self::DEFAULT_CONCURRENCY;

    return min(self::MAX_CONCURRENCY, max(1, (int) $concurrency));
  }

  private static function get_retry_backoff_seconds($attempts)
  {
    $attempt_index = max(0, (int) $attempts - 1);
    $seconds = (int) round(self::RETRY_BACKOFF_BASE_SECONDS * 2 ** $attempt_index);
    return min(self::RETRY_BACKOFF_MAX_SECONDS, max(1, $seconds));
  }

  private static function set_max_retries($group_name, $callback_action, $max_retries)
  {
    $key = self::queue_config_key($group_name, $callback_action);
    self::$queue_max_retries[$key] = max(0, (int) $max_retries);
  }

  private static function set_concurrency($group_name, $callback_action, $concurrency)
  {
    $key = self::queue_config_key($group_name, $callback_action);
    self::$queue_concurrency[$key] = min(self::MAX_CONCURRENCY, max(1, (int) $concurrency));
  }

  private static function queue_config_key($group_name, $callback_action)
  {
    return $group_name . '|' . $callback_action;
  }

  private static function dispatch_runners($group_name, $callback_action)
  {
    $active_count = self::get_active_runner_count($group_name, $callback_action);
    $concurrency = self::get_concurrency($group_name, $callback_action);
    $available_slots = $concurrency - $active_count;

    if ($available_slots <= 0) {
      return;
    }

    $claimable_pending_count = self::get_claimable_pending_count($group_name, $callback_action);
    $dispatch_count = min($available_slots, $claimable_pending_count);

    for ($i = 0; $i < $dispatch_count; $i++) {
      self::dispatch_runner($group_name, $callback_action);
    }
  }

  private static function dispatch_runner($group_name, $callback_action)
  {
    $url = rest_url(trim(self::REST_NAMESPACE, '/') . self::REST_ROUTE);

    wp_remote_post($url, [
      'timeout' => 0.01,
      'blocking' => false,
      'sslverify' => false,
      'body' => [
        'group' => $group_name,
        'callback' => $callback_action,
        'token' => self::build_runner_token($group_name, $callback_action),
      ],
    ]);
  }

  private static function get_active_runner_count($group_name, $callback_action)
  {
    global $wpdb;

    $table = self::table_name();
    return (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*)
        FROM $table
        WHERE group_name = %s
          AND callback_action = %s
          AND status = 'processing'
          AND locked_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)",
        $group_name,
        $callback_action,
        self::TASK_LOCK_TIMEOUT
      )
    );
  }

  private static function get_claimable_pending_count($group_name, $callback_action)
  {
    global $wpdb;

    $table = self::table_name();
    return (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*)
        FROM $table
        WHERE group_name = %s
          AND callback_action = %s
          AND status = 'pending'
          AND (locked_at IS NULL OR locked_at <= UTC_TIMESTAMP())",
        $group_name,
        $callback_action
      )
    );
  }

  private static function build_runner_token($group_name, $callback_action)
  {
    return hash_hmac('sha256', $group_name . '|' . $callback_action, wp_salt('auth'));
  }

  private static function table_name()
  {
    global $wpdb;

    return $wpdb->prefix . 'flying_press_queue';
  }

  private function normalize_task_data($task_data)
  {
    if (!is_array($task_data)) {
      return [$task_data];
    }

    return $this->is_assoc($task_data) ? $task_data : array_values($task_data);
  }

  private function build_task_hash($task_data)
  {
    return hash(
      'sha256',
      $this->group_name . '|' . wp_json_encode($this->canonicalize_for_hash($task_data))
    );
  }

  private function is_assoc($array)
  {
    return array_keys($array) !== range(0, count($array) - 1);
  }

  private function canonicalize_for_hash($data)
  {
    if (!is_array($data)) {
      return $data;
    }

    foreach ($data as $key => $value) {
      $data[$key] = $this->canonicalize_for_hash($value);
    }

    if ($this->is_assoc($data)) {
      ksort($data);
    }

    return $data;
  }
}
