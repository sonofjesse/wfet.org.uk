<?php

namespace FlyingPress;

class Vitals
{
  public static function init()
  {
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_vitals_script']);
  }

  public static function enqueue_vitals_script()
  {
    if (is_admin()) {
      return;
    }

    $license_key = Config::$config['license_key'];
    $vitals = Config::$config['vitals'];

    if (empty($license_key) || !$vitals) {
      return;
    }

    $site_id = md5(wp_parse_url(get_site_url(), PHP_URL_HOST) . $license_key);

    wp_enqueue_script(
      'flying-press-vitals',
      FLYING_PRESS_PLUGIN_URL . 'assets/vitals.min.js',
      [],
      null,
      true
    );

    wp_localize_script('flying-press-vitals', 'flying_press_vitals', [
      'site_id' => $site_id,
    ]);
  }
}
