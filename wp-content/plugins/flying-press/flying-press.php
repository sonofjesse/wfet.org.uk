<?php

/**
 * Plugin Name: FlyingPress
 * Plugin URI: https://flyingpress.com
 * Description: Lightning-Fast WordPress on Autopilot
 * Version: 5.5.0-beta.10
 * Requires PHP: 7.4
 * Requires at least: 4.7
 * Author: FlyingWeb
 * Text Domain: flying-press
 * Domain Path: /languages
 */

defined('ABSPATH') or die('No script kiddies please!');

require_once dirname(__FILE__) . '/vendor/autoload.php';

define('FLYING_PRESS_VERSION', '5.5.0-beta.10');
define('FLYING_PRESS_FILE', __FILE__);
define('FLYING_PRESS_FILE_NAME', plugin_basename(__FILE__));
define('FLYING_PRESS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLYING_PRESS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLYING_PRESS_CACHE_DIR', WP_CONTENT_DIR . '/cache/flying-press/');
define('FLYING_PRESS_CACHE_URL', WP_CONTENT_URL . '/cache/flying-press/');

!is_dir(FLYING_PRESS_CACHE_DIR) && mkdir(FLYING_PRESS_CACHE_DIR, 0755, true);

add_action('init', function () {
  load_plugin_textdomain('flying-press', false, FLYING_PRESS_PLUGIN_DIR . 'languages/');
});

FlyingPress\Config::init();
FlyingPress\WPCache::init();
FlyingPress\Htaccess::init();
FlyingPress\AdvancedCache::init();
FlyingPress\Integrations::init();
FlyingPress\AutoPurge::init();
FlyingPress\License::init();
FlyingPress\Preload::init();
FlyingPress\ImageOptimizer::init();
FlyingPress\Cron::init();
FlyingPress\Caching::init();
FlyingPress\RestApi::init();
FlyingPress\AdminBar::init();
FlyingPress\Optimizer::init();
FlyingPress\Cloudflare::init();
FlyingPress\FlyingCDN::init();
FlyingPress\Dashboard::init();
FlyingPress\Database::init();
FlyingPress\Compatibility::init();
FlyingPress\Permalink::init();
FlyingPress\Shortcuts::init();
FlyingPress\Vitals::init();
FlyingPress\WpCLI::init();
FlyingPress\Queue::init();
FlyingPress\QueueViewer::init();
