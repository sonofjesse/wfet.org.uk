<?php

namespace FlyingPress;

class QueueViewer
{
  private const TEST_REST_NAMESPACE = 'flying-press';
  private const TEST_REST_ROUTE = '/queue/ping/';
  private const TEST_AJAX_ACTION = 'flying_press_queue_test_rest';

  public static function init()
  {
    add_action('admin_menu', [__CLASS__, 'register_page']);
    add_action('rest_api_init', [__CLASS__, 'register_test_rest_route']);
    add_action('wp_ajax_' . self::TEST_AJAX_ACTION, [__CLASS__, 'handle_rest_test_ajax']);
  }

  public static function register_page()
  {
    if (!Auth::is_allowed()) {
      return;
    }

    add_management_page(
      'FlyingPress Queue',
      'FlyingPress Queue',
      'edit_posts',
      'flying-press-queue',
      [__CLASS__, 'render_page']
    );
  }

  public static function render_page()
  {
    global $wpdb;

    $table = $wpdb->prefix . 'flying_press_queue';
    $status_filter = sanitize_key((string) ($_GET['status'] ?? 'all'));
    $allowed_status_filters = ['all', 'pending', 'processing', 'failed'];
    if (!in_array($status_filter, $allowed_status_filters, true)) {
      $status_filter = 'all';
    }
    $current_page = max(1, (int) ($_GET['paged'] ?? 1));
    $per_page = 10;
    $offset = ($current_page - 1) * $per_page;
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;

    echo '<div class="wrap">';
    echo '<h1>FlyingPress Queue</h1>';
    self::render_rest_test_control();
    echo '<style>
      .fp-queue-status {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.6;
      }
      .fp-queue-status-pending { background: #fff3cd; color: #664d03; }
      .fp-queue-status-processing { background: #cff4fc; color: #055160; }
      .fp-queue-status-failed { background: #f8d7da; color: #842029; }
      .fp-queue-status-default { background: #e2e3e5; color: #41464b; }
    </style>';

    if (!$exists) {
      echo '<p>Queue table not found.</p>';
      echo '</div>';
      return;
    }

    $counts = $wpdb->get_results(
      "SELECT status, COUNT(*) AS total FROM $table GROUP BY status",
      ARRAY_A
    );
    $summary = ['pending' => 0, 'processing' => 0, 'failed' => 0];

    foreach ($counts as $row) {
      $status = (string) ($row['status'] ?? '');
      $summary[$status] = (int) ($row['total'] ?? 0);
    }

    if ($status_filter === 'all') {
      $total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    } else {
      $total_items = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", $status_filter)
      );
    }

    $total_pages = max(1, (int) ceil($total_items / $per_page));

    if ($status_filter === 'all') {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT id, group_name, callback_action, task_data, priority, status, attempts, created_at, updated_at, last_error
        FROM $table
        ORDER BY
          CASE status
            WHEN 'processing' THEN 0
            WHEN 'pending' THEN 1
            WHEN 'failed' THEN 2
            ELSE 3
          END ASC,
          priority ASC,
          created_at ASC,
          id ASC
        LIMIT %d OFFSET %d",
          $per_page,
          $offset
        ),
        ARRAY_A
      );
    } else {
      $rows = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT id, group_name, callback_action, task_data, priority, status, attempts, created_at, updated_at, last_error
        FROM $table
        WHERE status = %s
        ORDER BY
          priority ASC,
          created_at ASC,
          id ASC
        LIMIT %d OFFSET %d",
          $status_filter,
          $per_page,
          $offset
        ),
        ARRAY_A
      );
    }

    echo '<p>';
    self::render_status_filter_links($summary, $status_filter);
    echo '</p>';

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>Group</th>';
    echo '<th>Callback</th>';
    echo '<th>Priority</th>';
    echo '<th>Status</th>';
    echo '<th>Attempts</th>';
    echo '<th>Created</th>';
    echo '<th>Updated</th>';
    echo '<th>Task Data</th>';
    echo '<th>Last Error</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
      echo '<tr><td colspan="10">No queue rows found.</td></tr>';
    } else {
      foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . (int) ($row['id'] ?? 0) . '</td>';
        echo '<td>' . esc_html((string) ($row['group_name'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string) ($row['callback_action'] ?? '')) . '</td>';
        echo '<td>' . (int) ($row['priority'] ?? 0) . '</td>';
        echo '<td>' . self::render_status_badge((string) ($row['status'] ?? '')) . '</td>';
        echo '<td>' . (int) ($row['attempts'] ?? 0) . '</td>';
        echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string) ($row['updated_at'] ?? '')) . '</td>';
        echo '<td>' . self::format_task_data((string) ($row['task_data'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string) ($row['last_error'] ?? '')) . '</td>';
        echo '</tr>';
      }
    }

    echo '</tbody></table>';
    self::render_pagination($current_page, $total_pages, $total_items, $status_filter);
    echo '</div>';
  }

  public static function register_test_rest_route()
  {
    register_rest_route(self::TEST_REST_NAMESPACE, self::TEST_REST_ROUTE, [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => [__CLASS__, 'test_rest_ping'],
    ]);
  }

  public static function test_rest_ping()
  {
    return [
      'success' => true,
      'message' => 'REST ping ok',
    ];
  }

  public static function handle_rest_test_ajax()
  {
    if (!Auth::is_allowed()) {
      wp_send_json_error(['message' => 'Not allowed'], 403);
    }

    check_ajax_referer(self::TEST_AJAX_ACTION, 'nonce');

    $url = rest_url(trim(self::TEST_REST_NAMESPACE, '/') . self::TEST_REST_ROUTE);
    $response = wp_remote_get($url, [
      'timeout' => 5,
      'sslverify' => false,
    ]);

    if (is_wp_error($response)) {
      wp_send_json_error([
        'message' => 'REST request failed: ' . $response->get_error_message(),
      ]);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    $is_valid = $code === 200 && is_array($body) && !empty($body['success']);

    if (!$is_valid) {
      wp_send_json_error([
        'message' => 'REST API test failed',
        'status_code' => $code,
      ]);
    }

    wp_send_json_success([
      'message' => 'REST API is enabled and reachable',
      'status_code' => $code,
    ]);
  }

  private static function render_rest_test_control()
  {
    $button_id = 'fp-queue-rest-test-button';
    $result_id = 'fp-queue-rest-test-result';
    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce(self::TEST_AJAX_ACTION);
    $ajax_action = self::TEST_AJAX_ACTION;

    echo '<p>';
    echo '<button type="button" class="button button-secondary" id="' .
      esc_attr($button_id) .
      '">Test REST API</button> ';
    echo '<span id="' . esc_attr($result_id) . '"></span>';
    echo '</p>';

    echo '<script>
      (function () {
        var button = document.getElementById(' .
      wp_json_encode($button_id) .
      ');
        var result = document.getElementById(' .
      wp_json_encode($result_id) .
      ');
        if (!button || !result) {
          return;
        }

        button.addEventListener("click", function () {
          button.disabled = true;
          result.style.color = "";
          result.textContent = "Testing...";

          var body = new URLSearchParams();
          body.append("action", ' .
      wp_json_encode($ajax_action) .
      ');
          body.append("nonce", ' .
      wp_json_encode($nonce) .
      ');

          fetch(' .
      wp_json_encode($ajax_url) .
      ', {
            method: "POST",
            credentials: "same-origin",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: body.toString()
          })
            .then(function (response) {
              return response.json();
            })
            .then(function (data) {
              if (data && data.success) {
                result.style.color = "#0a7d16";
                result.textContent = data.data && data.data.message ? data.data.message : "REST API test passed";
                return;
              }

              result.style.color = "#b42318";
              var failureMessage =
                data && data.data && data.data.message ? data.data.message : "REST API test failed";
              var statusCode =
                data && data.data && data.data.status_code ? data.data.status_code : null;

              if (statusCode) {
                failureMessage += " (HTTP " + statusCode + ")";
              }

              result.textContent = failureMessage;
            })
            .catch(function () {
              result.style.color = "#b42318";
              result.textContent = "REST API test failed";
            })
            .finally(function () {
              button.disabled = false;
            });
        });
      })();
    </script>';
  }

  private static function render_status_filter_links($summary, $status_filter)
  {
    $base_url = admin_url('tools.php?page=flying-press-queue');
    $all_count =
      (int) $summary['pending'] + (int) $summary['processing'] + (int) $summary['failed'];

    $links = [
      'all' => 'All: <strong>' . $all_count . '</strong>',
      'pending' => 'Pending: <strong>' . (int) $summary['pending'] . '</strong>',
      'processing' => 'Processing: <strong>' . (int) $summary['processing'] . '</strong>',
      'failed' => 'Failed: <strong>' . (int) $summary['failed'] . '</strong>',
    ];

    $output = [];
    foreach ($links as $status => $label) {
      if ($status === $status_filter) {
        $output[] = '<span>' . $label . '</span>';
        continue;
      }

      $url = add_query_arg(
        [
          'status' => $status,
          'paged' => 1,
        ],
        $base_url
      );

      $output[] = '<a href="' . esc_url($url) . '">' . $label . '</a>';
    }

    echo implode(' | ', $output);
  }

  private static function render_pagination(
    $current_page,
    $total_pages,
    $total_items,
    $status_filter = 'all'
  ) {
    if ($total_pages < 2) {
      return;
    }

    $base_url = admin_url('tools.php?page=flying-press-queue');
    $base_args = [];
    if ($status_filter !== 'all') {
      $base_args['status'] = $status_filter;
    }

    $prev_url = add_query_arg(
      array_merge($base_args, ['paged' => max(1, $current_page - 1)]),
      $base_url
    );
    $next_url = add_query_arg(
      array_merge($base_args, ['paged' => min($total_pages, $current_page + 1)]),
      $base_url
    );

    echo '<div class="tablenav"><div class="tablenav-pages" style="margin:12px 0;">';
    echo '<span class="displaying-num">' . number_format_i18n($total_items) . ' items</span> ';

    if ($current_page > 1) {
      echo '<a class="button" href="' . esc_url($prev_url) . '">Previous</a> ';
    } else {
      echo '<span class="button disabled">Previous</span> ';
    }

    echo '<span class="paging-input">Page ' .
      (int) $current_page .
      ' of ' .
      (int) $total_pages .
      '</span> ';

    if ($current_page < $total_pages) {
      echo '<a class="button" href="' . esc_url($next_url) . '">Next</a>';
    } else {
      echo '<span class="button disabled">Next</span>';
    }

    echo '</div></div>';
  }

  private static function format_task_data($task_data)
  {
    if ($task_data === '') {
      return '';
    }

    $decoded = json_decode($task_data, true);
    $formatted =
      json_last_error() === JSON_ERROR_NONE
        ? wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        : $task_data;

    return '<pre style="margin:0;max-width:420px;max-height:180px;overflow:auto;white-space:pre-wrap;">' .
      esc_html((string) $formatted) .
      '</pre>';
  }

  private static function render_status_badge($status)
  {
    $status = strtolower(trim((string) $status));
    $class = 'fp-queue-status-default';

    if ($status === 'pending') {
      $class = 'fp-queue-status-pending';
    } elseif ($status === 'processing') {
      $class = 'fp-queue-status-processing';
    } elseif ($status === 'failed') {
      $class = 'fp-queue-status-failed';
    }

    return '<span class="fp-queue-status ' .
      esc_attr($class) .
      '">' .
      esc_html($status ?: 'unknown') .
      '</span>';
  }
}
