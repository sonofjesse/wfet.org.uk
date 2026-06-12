<?php

namespace FlyingPress;

class WPConfig
{
  // Locate wp-config.php in ABSPATH or its parent
  private static function get_config_path()
  {
    $paths = [ABSPATH . 'wp-config.php', dirname(ABSPATH) . '/wp-config.php'];
    foreach ($paths as $p) {
      if (is_readable($p) && is_writable($p)) {
        return $p;
      }
    }
    return false;
  }

  // Atomically write $content to $path via a temp file + rename
  private static function write_atomic($path, $content)
  {
    $tmp = $path . '.flying-press.tmp.php';
    if (file_put_contents($tmp, $content) === false) {
      return false;
    }
    return rename($tmp, $path);
  }

  // Add or update a define() in wp-config.php
  public static function add_constant($name, $value)
  {
    // Skip if already defined correctly
    if (defined($name) && constant($name) === $value) {
      return;
    }

    // Get config path
    $path = self::get_config_path();
    if (!$path) {
      return;
    }

    // Read file
    $content = file_get_contents($path);
    if ($content === false || $content === '') {
      return;
    }

    // Remove any existing define line for this name
    $pattern = '/^\s*define\(\s*[\'"]' . preg_quote($name, '/') . '[\'"].*$/m';
    $content = preg_replace($pattern, '', $content);

    // Prepare new define line
    $formatted = var_export($value, true);
    $define = "define('{$name}', {$formatted}); // Added by FlyingPress\n";

    // Inject after the opening <?php
    $content = preg_replace('/^<\?php/m', "<?php\n{$define}", $content, 1);

    // Write back atomically
    self::write_atomic($path, $content);
  }

  // Remove our define() line from wp-config.php
  public static function remove_constant($name)
  {
    // Get config path
    $path = self::get_config_path();
    if (!$path) {
      return;
    }

    // Read file
    $content = file_get_contents($path);
    if ($content === false || $content === '') {
      return;
    }

    // Remove any define line for this name
    $pattern = '/^\s*define\(\s*[\'"]' . preg_quote($name, '/') . '[\'"].*$/m';
    $content = preg_replace($pattern, '', $content);

    // Write back atomically
    self::write_atomic($path, $content);
  }
}
