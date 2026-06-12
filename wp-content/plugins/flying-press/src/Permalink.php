<?php
namespace FlyingPress;

class Permalink
{
  public static function init()
  {
    add_action('admin_notices', [__CLASS__, 'check_permalink_structure']);
  }

  public static function check_permalink_structure()
  {
    $permalink_structure = get_option('permalink_structure');

    // Skip if a permalink structure is set
    if (!empty($permalink_structure)) {
      return;
    }

    $configure = admin_url('options-permalink.php');

    echo sprintf(
      "<div class='notice notice-error'>
        <p><b>FlyingPress</b>: %s <a href='%s'>%s</a></p>
      </div>",
      __('A Permalink structure is required.', 'flying-press'),
      esc_url($configure),
      __('Configure now', 'flying-press')
    );
  }
}
