<?php
namespace FlyingPress;

class Shortcuts
{
  public static function init()
  {
    add_filter('plugin_action_links_' . FLYING_PRESS_FILE_NAME, [__CLASS__, 'add_shortcuts']);
  }

  public static function add_shortcuts($links)
  {
    $docs_link = sprintf(
      '<a href="https://docs.flyingpress.com/" target="_blank">%s</a>',
      __('Documentation', 'flying-press')
    );

    $settings_link = sprintf(
      '<a href="%s" target="_blank">%s</a>',
      esc_url(admin_url('admin.php?page=flying-press')),
      __('Settings', 'flying-press')
    );

    // Use array_unshift to prepend the links
    array_unshift($links, $settings_link, $docs_link);

    return $links;
  }
}
