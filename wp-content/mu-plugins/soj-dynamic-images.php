<?php

/**
 * Plugin Name: SOJ Dynamic Image Sizes
 * Description: Generates cropped/resized attachment images on demand and serves them from a dedicated cache folder.
 */

defined('ABSPATH') || exit;

// Verbose logging (e.g. dimension snap lines). Override in wp-config.php: define('SOJ_DYNAMIC_IMAGES_DEBUG', true);
if (!defined('SOJ_DYNAMIC_IMAGES_DEBUG')) {
    define('SOJ_DYNAMIC_IMAGES_DEBUG', false);
}

class SOJ_Dynamic_Images
{
    const CACHE_FOLDER = 'dynamic-images';
    /** @var int JPEG fallback quality (82 is a good balance vs core/Imagify-sized output). */
    const JPEG_QUALITY = 82;
    /** @var int WebP quality (75–78 typically matches smaller core block derivatives). */
    const WEBP_QUALITY = 76;
    /**
     * @var int AVIF quality. AVIF's scale differs from JPEG/WebP: ~50 is roughly
     * visually comparable to WebP ~76 on photos, at a notably smaller file size.
     */
    const AVIF_QUALITY = 50;
    const CACHE_EXPIRY = 3600;
    const MAX_IMAGE_WIDTH = 3840;
    const MAX_IMAGE_HEIGHT = 2160;
    const MAX_CACHE_SIZE = 1073741824; // 1GB in bytes
    
    /**
     * Approved width ladder for size quantization.
     * Requests are snapped to nearest (or next-highest) value to reduce derivative count.
     *
     * @var array<int>
     */
    private static $width_ladder = [240, 320, 360, 420, 480, 640, 720, 840, 960, 1200, 1440, 1600, 2000, 2400];

    public static function init()
    {
        add_action('delete_attachment', [__CLASS__, 'purge_attachment_cache']);
        add_action('soj_cleanup_image_cache', [__CLASS__, 'cleanup_orphaned_cache']);

        if (!wp_next_scheduled('soj_cleanup_image_cache')) {
            wp_schedule_event(time(), 'daily', 'soj_cleanup_image_cache');
        }

        // Admin page for bulk WebP generation
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        add_action('wp_ajax_soj_generate_webp_bulk', [__CLASS__, 'ajax_generate_webp_bulk']);
    }

    public static function get_image_src($attachment_id, $width = 0, $height = 0, $crop = true)
    {
        $attachment_id = (int) $attachment_id;

        // Security: Ensure positive integer to prevent path traversal
        if ($attachment_id <= 0 || $attachment_id > 2147483647) {
            return false;
        }

        if (!$width && !$height) {
            return false;
        }

        // Security: Comprehensive dimension validation
        if ($width < 0 || $height < 0) {
            return false;
        }

        if ($width > self::MAX_IMAGE_WIDTH || $height > self::MAX_IMAGE_HEIGHT) {
            return false;
        }

        // Prevent very small images that could cause issues
        if (($width > 0 && $width < 10) || ($height > 0 && $height < 10)) {
            return false;
        }
        
        // Store original requested dimensions for logging
        $requested_width = (int) $width;
        $requested_height = (int) $height;

        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return false;
        }

        $source_file = get_attached_file($attachment_id);
        if (!$source_file || !file_exists($source_file)) {
            return false;
        }

        // Validate mime type
        $mime_type = get_post_mime_type($attachment_id);
        if (!$mime_type || !wp_match_mime_types('image', $mime_type)) {
            return false;
        }
        
        // Get source image dimensions to prevent upscaling
        $source_metadata = wp_get_attachment_metadata($attachment_id);
        $source_width = isset($source_metadata['width']) ? (int) $source_metadata['width'] : 0;
        $source_height = isset($source_metadata['height']) ? (int) $source_metadata['height'] : 0;
        
        // If metadata not available, try to get from file
        if (!$source_width || !$source_height) {
            $image_info = wp_getimagesize($source_file);
            if ($image_info) {
                $source_width = (int) ($image_info[0] ?? 0);
                $source_height = (int) ($image_info[1] ?? 0);
            }
        }
        
        // Snap dimensions to approved ladder (reduces derivative count)
        // Only snap if both width and height are provided
        if ($requested_width > 0 && $requested_height > 0) {
            $snapped = self::snap_dimensions($requested_width, $requested_height, $source_width, $source_height);
            $width = $snapped[0];
            $height = $snapped[1];
            
            // Verbose snap logging only when SOJ_DYNAMIC_IMAGES_DEBUG is true (see wp-config.php)
            if (SOJ_DYNAMIC_IMAGES_DEBUG && ($width !== $requested_width || $height !== $requested_height)) {
                error_log(sprintf(
                    'SOJ Dynamic Images: snapped %dx%d -> %dx%d for attachment %d (source: %dx%d)',
                    $requested_width,
                    $requested_height,
                    $width,
                    $height,
                    $attachment_id,
                    $source_width,
                    $source_height
                ));
            }
        } else {
            // If only one dimension provided, use as-is (existing behavior)
            $width = $requested_width;
            $height = $requested_height;
        }

        // Check object cache first (using snapped dimensions so multiple requests share cache)
        $crop_flag = $crop ? 'c' : 'nc';
        $cache_key = "soj_img_{$attachment_id}_{$width}x{$height}_{$crop_flag}";
        $cached = wp_cache_get($cache_key, 'soj_dynamic_images');
        if ($cached !== false) {
            return $cached;
        }

        $cache_root_dir = trailingslashit($upload_dir['basedir']) . self::CACHE_FOLDER;
        $cache_root_url = trailingslashit($upload_dir['baseurl']) . self::CACHE_FOLDER;

        if (!wp_mkdir_p($cache_root_dir)) {
            return false;
        }

        $ext = strtolower((string) pathinfo($source_file, PATHINFO_EXTENSION));

        // Security: Whitelist only resizable raster image formats
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed_extensions, true)) {
            error_log(sprintf('SOJ Dynamic Images: Unsupported extension "%s" for attachment %d. Only JPG, PNG, and WebP are supported for resizing.', $ext, $attachment_id));
            return false;
        }

        // Get original filename for SEO-friendly URLs
        $original_filename = wp_basename($source_file, '.' . $ext);
        // Sanitize filename for URL (remove special chars, keep alphanumeric, hyphens, underscores)
        $sanitized_filename = sanitize_file_name($original_filename);
        // Remove extension if it was included in sanitize_file_name
        $sanitized_filename = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $sanitized_filename);
        // If filename is empty after sanitization, use a fallback
        if (empty($sanitized_filename)) {
            $sanitized_filename = 'image-' . $attachment_id;
        }

        $crop_flag = $crop ? 'c' : 'nc';
        
        // Create folder structure: {attachment_id}/{width}x{height}/
        $size_folder = sprintf('%dx%d', (int) $width, (int) $height);
        $attachment_dir = trailingslashit($cache_root_dir) . $attachment_id . '/' . $size_folder;
        $attachment_url = trailingslashit($cache_root_url) . $attachment_id . '/' . $size_folder;

        if (!wp_mkdir_p($attachment_dir)) {
            return false;
        }

        // Always generate JPEG derivative (even if source is PNG/WebP)
        // Filename: {original-filename}-{crop_flag}.jpg
        $target_basename_jpg = sprintf('%s-%s.jpg', $sanitized_filename, $crop_flag);
        $target_file_jpg     = trailingslashit($attachment_dir) . $target_basename_jpg;
        $target_url_jpg      = trailingslashit($attachment_url) . $target_basename_jpg;
        
        // WebP derivative filename
        $target_basename_webp = sprintf('%s-%s.webp', $sanitized_filename, $crop_flag);
        $target_file_webp     = trailingslashit($attachment_dir) . $target_basename_webp;
        $target_url_webp      = trailingslashit($attachment_url) . $target_basename_webp;

        // AVIF derivative filename (only used when AVIF output is enabled).
        $avif_enabled         = self::avif_enabled();
        $target_basename_avif = sprintf('%s-%s.avif', $sanitized_filename, $crop_flag);
        $target_file_avif     = trailingslashit($attachment_dir) . $target_basename_avif;
        $target_url_avif      = trailingslashit($attachment_url) . $target_basename_avif;

        // Use JPEG as the primary target file for generation
        $target_file = $target_file_jpg;
        $target_url  = $target_url_jpg;

        // Check if we need to regenerate (either JPEG or WebP missing or outdated)
        $needs_regeneration = !file_exists($target_file_jpg) || 
                             !file_exists($target_file_webp) ||
                             filemtime($target_file_jpg) < filemtime($source_file) ||
                             (file_exists($target_file_webp) && filemtime($target_file_webp) < filemtime($source_file));

        // When AVIF is enabled, a missing/outdated AVIF also triggers (re)generation.
        if ($avif_enabled && !$needs_regeneration) {
            $needs_regeneration = !file_exists($target_file_avif) ||
                                  filemtime($target_file_avif) < filemtime($source_file);
        }
        
        if ($needs_regeneration) {
            // Security: Rate limiting only when generating NEW files (not serving cached ones)
            // Rate limit per-size to allow multiple sizes per image without hitting limit
            $rate_key = sprintf('soj_img_rate_%d_%dx%d_%s', $attachment_id, (int) $width, (int) $height, $crop_flag);
            $request_count = (int) wp_cache_get($rate_key, 'soj_dynamic_images');

            // Increased limit: 50 new file generations per hour per size (more reasonable for legitimate use)
            $rate_limit = apply_filters('soj_dynamic_images_rate_limit', 50, $attachment_id, $width, $height);
            $rate_window = apply_filters('soj_dynamic_images_rate_window', 3600, $attachment_id); // 1 hour default

            if ($request_count >= $rate_limit) {
                // Only log if WP_DEBUG is enabled to reduce noise
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'SOJ Dynamic Images: Rate limit exceeded for attachment %d, size %dx%d (%s). Limit: %d per %d seconds',
                        $attachment_id,
                        (int) $width,
                        (int) $height,
                        $crop_flag,
                        $rate_limit,
                        $rate_window
                    ));
                }
                // Return false to prevent generation, but don't block serving if file somehow exists
                return false;
            }

            // Increment rate limit counter
            wp_cache_set($rate_key, $request_count + 1, 'soj_dynamic_images', $rate_window);

            // Increase memory limit for large images
            @ini_set('memory_limit', '256M');

            $editor = wp_get_image_editor($source_file);

            if (is_wp_error($editor)) {
                error_log(sprintf(
                    'SOJ Dynamic Images: Failed to load editor for attachment %d: %s',
                    $attachment_id,
                    $editor->get_error_message()
                ));
                return false;
            }

            if ($width && $height) {
                if ($crop) {
                    // Crop from center: resize to fit, then crop from center
                    $current_size = $editor->get_size();
                    $current_width = $current_size['width'];
                    $current_height = $current_size['height'];
                    
                    // If the source is smaller than the requested crop box in both dimensions,
                    // avoid attempting an exact crop (can result in padded/black pixels).
                    // Instead, fall back to a proportional resize that fits within the target box.
                    if ($current_width > 0 && $current_height > 0 && $current_width < $width && $current_height < $height) {
                        $editor->resize($width, $height, false);
                        goto soj_dynamic_images_save;
                    }
                    
                    // Calculate aspect ratios
                    $target_aspect = $width / $height;
                    $current_aspect = $current_width / $current_height;
                    
                    // Resize to fit within target dimensions while maintaining aspect ratio
                    if ($current_aspect > $target_aspect) {
                        // Image is wider - fit to height
                        $editor->resize(null, $height, false);
                    } else {
                        // Image is taller - fit to width
                        $editor->resize($width, null, false);
                    }
                    
                    // Get new size after resize
                    $new_size = $editor->get_size();
                    $new_width = $new_size['width'];
                    $new_height = $new_size['height'];
                    
                    // Safety: only crop if the resized image fully covers the crop box.
                    // If not, keep the resized image (no black padding).
                    if ($new_width < $width || $new_height < $height) {
                        goto soj_dynamic_images_save;
                    }
                    
                    // Calculate center crop position
                    $crop_x = max(0, floor(($new_width - $width) / 2));
                    $crop_y = max(0, floor(($new_height - $height) / 2));
                    
                    // Crop from center
                    $editor->crop($crop_x, $crop_y, $width, $height);
                } else {
                    // No crop - just resize
                    $editor->resize($width, $height, false);
                }
            } elseif ($width) {
                $editor->resize($width, null, false);
                $size   = $editor->get_size();
                $height = $size['height'];
            } else {
                $editor->resize(null, $height, false);
                $size  = $editor->get_size();
                $width = $size['width'];
            }

            soj_dynamic_images_save:
            $jpeg_quality = (int) apply_filters('soj_dynamic_images_jpeg_quality', self::JPEG_QUALITY, $attachment_id, $width, $height);
            $webp_quality = (int) apply_filters('soj_dynamic_images_webp_quality', self::WEBP_QUALITY, $attachment_id, $width, $height);
            $avif_quality = (int) apply_filters('soj_dynamic_images_avif_quality', self::AVIF_QUALITY, $attachment_id, $width, $height);
            $jpeg_quality = max(1, min(100, $jpeg_quality));
            $webp_quality = max(1, min(100, $webp_quality));
            $avif_quality = max(1, min(100, $avif_quality));

            // WebP from the resized bitmap (avoids JPEG → WebP double compression).
            self::save_webp_from_editor($editor, $target_file_webp, $webp_quality);

            // AVIF from the resized bitmap (opt-in; encoding is CPU-heavy).
            if ($avif_enabled) {
                self::save_avif_from_editor($editor, $target_file_avif, $avif_quality);
            }

            $editor->set_quality($jpeg_quality);
            $result = $editor->save($target_file_jpg);

            if (is_wp_error($result)) {
                error_log(sprintf(
                    'SOJ Dynamic Images: Failed to save resized JPEG for attachment %d: %s',
                    $attachment_id,
                    $result->get_error_message()
                ));
                return false;
            }

            if (!file_exists($target_file_webp)) {
                self::generate_webp_version($target_file_jpg, $target_file_webp, $webp_quality);
            }

            if ($avif_enabled && !file_exists($target_file_avif)) {
                self::generate_avif_version($target_file_jpg, $target_file_avif, $avif_quality);
            }

            $width  = (int) $result['width'];
            $height = (int) $result['height'];
        } else {
            // Get dimensions from existing JPEG file
            $image_info = wp_getimagesize($target_file_jpg);
            if (!$image_info) {
                return false;
            }

            $width  = (int) ($image_info[0] ?? 0);
            $height = (int) ($image_info[1] ?? 0);
        }

        // Check if WebP / AVIF exist
        $webp_url = file_exists($target_file_webp) ? $target_url_webp : null;
        $avif_url = ($avif_enabled && file_exists($target_file_avif)) ? $target_url_avif : null;

        // Always return JPEG as original/fallback, WebP / AVIF as separate
        $result = [
            'src'          => $target_url_jpg, // JPEG is always the fallback
            'src_original' => $target_url_jpg, // JPEG for picture element fallback
            'width'        => $width,  // Actual dimensions from generated file
            'height'       => $height, // Actual dimensions from generated file
            'src_webp'     => $webp_url, // WebP URL (null if not available)
            'src_avif'     => $avif_url, // AVIF URL (null if disabled/not available)
        ];

        // Cache the result
        wp_cache_set($cache_key, $result, 'soj_dynamic_images', self::CACHE_EXPIRY);

        return $result;
    }

    private static function maybe_get_webp_url($file_path, $file_url)
    {
        $candidates = [
            $file_path . '.webp',
            preg_replace('/\.[^.]+$/', '.webp', $file_path),
        ];

        $url_candidates = [
            $file_url . '.webp',
            preg_replace('/\.[^.]+$/', '.webp', $file_url),
        ];

        foreach ($candidates as $index => $candidate) {
            if ($candidate && file_exists($candidate)) {
                return $url_candidates[$index] ?? null;
            }
        }

        return null;
    }

    /**
     * Snap requested dimensions to approved ladder to reduce derivative count.
     *
     * @param int $requested_width Requested width in pixels.
     * @param int $requested_height Requested height in pixels.
     * @param int $source_width Source image width (0 if unknown).
     * @param int $source_height Source image height (0 if unknown).
     * @return array{int, int} Snapped [width, height] array.
     */
    private static function snap_dimensions($requested_width, $requested_height, $source_width = 0, $source_height = 0)
    {
        // Get width ladder (filterable)
        $ladder = apply_filters('soj_dynamic_images_width_ladder', self::$width_ladder);
        if (!is_array($ladder) || empty($ladder)) {
            $ladder = self::$width_ladder;
        }
        
        // Ensure ladder is sorted ascending
        sort($ladder, SORT_NUMERIC);
        
        // Get snap mode (filterable): 'ceil' (next-highest), 'nearest', or 'floor'
        $snap_mode = apply_filters('soj_dynamic_images_snap_mode', 'ceil');
        if (!in_array($snap_mode, ['ceil', 'nearest', 'floor'], true)) {
            $snap_mode = 'ceil';
        }
        
        // Calculate aspect ratio from requested dimensions
        $aspect_ratio = $requested_height > 0 ? ($requested_height / $requested_width) : 1.0;
        
        // Snap width to ladder
        $snapped_width = self::snap_to_ladder($requested_width, $ladder, $snap_mode);
        
        // Preserve aspect ratio: compute height from snapped width
        $snapped_height = max(1, (int) round($snapped_width * $aspect_ratio));
        
        // Clamp to max limits
        $snapped_width = min($snapped_width, self::MAX_IMAGE_WIDTH);
        $snapped_height = min($snapped_height, self::MAX_IMAGE_HEIGHT);
        
        // Optionally prevent upscaling above source dimensions (filterable, default: false = allow upscaling)
        $prevent_upscaling = apply_filters('soj_dynamic_images_prevent_upscaling', false);
        
        if ($prevent_upscaling && $source_width > 0 && $source_height > 0) {
            // Check if we need to scale down to fit within source dimensions
            $width_scale = $snapped_width > $source_width ? ($source_width / $snapped_width) : 1.0;
            $height_scale = $snapped_height > $source_height ? ($source_height / $snapped_height) : 1.0;
            
            // Use the smaller scale factor to ensure we fit within both dimensions
            $scale_factor = min($width_scale, $height_scale);
            
            if ($scale_factor < 1.0) {
                // Scale down proportionally while maintaining aspect ratio
                $snapped_width = max(1, (int) round($snapped_width * $scale_factor));
                $snapped_height = max(1, (int) round($snapped_height * $scale_factor));
            }
        } elseif ($prevent_upscaling && $source_width > 0 && $snapped_width > $source_width) {
            // Only width constraint
            $snapped_width = $source_width;
            $snapped_height = max(1, (int) round($snapped_width * $aspect_ratio));
        } elseif ($prevent_upscaling && $source_height > 0 && $snapped_height > $source_height) {
            // Only height constraint
            $snapped_height = $source_height;
            $snapped_width = max(1, (int) round($snapped_height / $aspect_ratio));
        }
        
        // Ensure minimum 1px
        $snapped_width = max(1, (int) $snapped_width);
        $snapped_height = max(1, (int) $snapped_height);
        
        return [(int) $snapped_width, (int) $snapped_height];
    }
    
    /**
     * Snap a value to the nearest ladder value based on mode.
     *
     * @param int $value Value to snap.
     * @param array<int> $ladder Sorted array of ladder values.
     * @param string $mode 'ceil' (next-highest), 'nearest', or 'floor'.
     * @return int Snapped value.
     */
    private static function snap_to_ladder($value, $ladder, $mode = 'ceil')
    {
        if (empty($ladder)) {
            return $value;
        }
        
        // If value is already in ladder, return as-is
        if (in_array($value, $ladder, true)) {
            return $value;
        }
        
        // Find the appropriate ladder value
        switch ($mode) {
            case 'ceil':
                // Next-highest (preferred to avoid upscaling blur)
                foreach ($ladder as $ladder_value) {
                    if ($ladder_value >= $value) {
                        return $ladder_value;
                    }
                }
                // If value exceeds all ladder values, return max ladder value
                return max($ladder);
                
            case 'floor':
                // Next-lowest
                $best = null;
                foreach ($ladder as $ladder_value) {
                    if ($ladder_value <= $value) {
                        $best = $ladder_value;
                    } else {
                        break;
                    }
                }
                return $best !== null ? $best : min($ladder);
                
            case 'nearest':
            default:
                // Nearest value
                $best = null;
                $best_diff = PHP_INT_MAX;
                foreach ($ladder as $ladder_value) {
                    $diff = abs($ladder_value - $value);
                    if ($diff < $best_diff) {
                        $best_diff = $diff;
                        $best = $ladder_value;
                    }
                }
                return $best !== null ? $best : $value;
        }
    }

    /**
     * Whether WebP can be written via Imagick or the WP image editor.
     */
    public static function supports_webp_output()
    {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            return false;
        }

        $formats = Imagick::queryFormats('WEBP');

        return !empty($formats);
    }

    /**
     * Apply Imagick WebP encoder options for stronger compression.
     *
     * @param \Imagick $imagick Imagick instance.
     * @param int       $quality Quality 1–100.
     */
    private static function apply_imagick_webp_options($imagick, $quality)
    {
        $imagick->setImageFormat('webp');
        $imagick->setImageCompressionQuality(max(1, min(100, (int) $quality)));
        $imagick->stripImage();

        $method = (int) apply_filters('soj_dynamic_images_webp_method', 6);
        $method = max(0, min(6, $method));

        if (method_exists($imagick, 'setOption')) {
            $imagick->setOption('webp:method', (string) $method);
            $imagick->setOption('webp:auto-filter', 'true');
            $imagick->setOption('webp:lossless', 'false');
        }
    }

    /**
     * Save WebP directly from a WP image editor (resized bitmap, not a JPEG intermediate).
     *
     * @param WP_Image_Editor $editor     Image editor after resize/crop.
     * @param string          $webp_path Target file path.
     * @param int             $quality   WebP quality.
     * @return bool
     */
    private static function save_webp_from_editor($editor, $webp_path, $quality)
    {
        if (!self::supports_webp_output()) {
            return false;
        }

        $editor->set_quality(max(1, min(100, (int) $quality)));
        $saved = $editor->save($webp_path, 'image/webp');

        if (is_wp_error($saved) || !file_exists($webp_path)) {
            return false;
        }

        return true;
    }

    /**
     * Generate WebP from a raster file (fallback when editor WebP save is unavailable).
     *
     * @param string      $image_path Path to the source image file.
     * @param string|null $webp_path  Optional target WebP path.
     * @param int         $quality    WebP quality.
     * @return bool|string Path to WebP file on success, false on failure.
     */
    public static function generate_webp_version($image_path, $webp_path = null, $quality = 75)
    {
        if (!self::supports_webp_output()) {
            return false;
        }

        if ($webp_path === null) {
            $webp_path = preg_replace('/\.[^.]+$/', '.webp', $image_path);
        }

        if (file_exists($webp_path)) {
            return $webp_path;
        }

        try {
            $imagick_class = 'Imagick';
            /** @var \Imagick $imagick */
            $imagick = new $imagick_class($image_path);
            self::apply_imagick_webp_options($imagick, $quality);
            $imagick->writeImage($webp_path);
            $imagick->clear();
            $imagick->destroy();

            return file_exists($webp_path) ? $webp_path : false;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'SOJ Dynamic Images: Failed to generate WebP for %s: %s',
                    $image_path,
                    $e->getMessage()
                ));
            }
            return false;
        }
    }

    /**
     * Whether AVIF output is enabled for this site.
     *
     * Disabled by default because AVIF encoding is CPU-heavy and the derivatives
     * are generated on first request. Enable via the SOJ_DYNAMIC_IMAGES_AVIF
     * constant (wp-config.php) or the `soj_dynamic_images_enable_avif` filter.
     *
     * @return bool
     */
    public static function avif_enabled()
    {
        $enabled = defined('SOJ_DYNAMIC_IMAGES_AVIF') ? (bool) SOJ_DYNAMIC_IMAGES_AVIF : false;
        $enabled = (bool) apply_filters('soj_dynamic_images_enable_avif', $enabled);

        return $enabled && self::supports_avif_output();
    }

    /**
     * Whether AVIF can be written via Imagick.
     *
     * @return bool
     */
    public static function supports_avif_output()
    {
        static $supported = null;

        if ($supported !== null) {
            return $supported;
        }

        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            $supported = false;
            return $supported;
        }

        $supported = !empty(Imagick::queryFormats('AVIF'));

        return $supported;
    }

    /**
     * Apply Imagick AVIF encoder options.
     *
     * @param \Imagick $imagick Imagick instance.
     * @param int       $quality Quality 1–100 (AVIF scale; ~50 ≈ WebP ~76).
     */
    private static function apply_imagick_avif_options($imagick, $quality)
    {
        $imagick->setImageFormat('avif');
        $imagick->setImageCompressionQuality(max(1, min(100, (int) $quality)));
        $imagick->stripImage();

        // Speed 0 (slowest/smallest) – 8 (fastest/largest). 6 keeps on-demand
        // encoding tolerable while still beating WebP on size.
        $speed = (int) apply_filters('soj_dynamic_images_avif_speed', 6);
        $speed = max(0, min(8, $speed));

        if (method_exists($imagick, 'setOption')) {
            $imagick->setOption('heic:speed', (string) $speed);
            $imagick->setOption('avif:speed', (string) $speed);
        }
    }

    /**
     * Save AVIF directly from a WP image editor (resized bitmap).
     *
     * Falls back to a fresh Imagick encode of the bitmap if the editor cannot
     * write AVIF directly (so encoder options are always applied).
     *
     * @param WP_Image_Editor $editor    Image editor after resize/crop.
     * @param string          $avif_path Target file path.
     * @param int             $quality   AVIF quality.
     * @return bool
     */
    private static function save_avif_from_editor($editor, $avif_path, $quality)
    {
        if (!self::supports_avif_output()) {
            return false;
        }

        $editor->set_quality(max(1, min(100, (int) $quality)));
        $saved = $editor->save($avif_path, 'image/avif');

        if (!is_wp_error($saved) && file_exists($avif_path)) {
            return true;
        }

        return false;
    }

    /**
     * Generate AVIF from a raster file (fallback when editor AVIF save is unavailable).
     *
     * @param string      $image_path Path to the source image file (JPEG).
     * @param string|null $avif_path  Optional target AVIF path.
     * @param int         $quality    AVIF quality.
     * @return bool|string Path to AVIF file on success, false on failure.
     */
    public static function generate_avif_version($image_path, $avif_path = null, $quality = 50)
    {
        if (!self::supports_avif_output()) {
            return false;
        }

        if ($avif_path === null) {
            $avif_path = preg_replace('/\.[^.]+$/', '.avif', $image_path);
        }

        if (file_exists($avif_path)) {
            return $avif_path;
        }

        try {
            $imagick_class = 'Imagick';
            /** @var \Imagick $imagick */
            $imagick = new $imagick_class($image_path);
            self::apply_imagick_avif_options($imagick, $quality);
            $imagick->writeImage($avif_path);
            $imagick->clear();
            $imagick->destroy();

            return file_exists($avif_path) ? $avif_path : false;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'SOJ Dynamic Images: Failed to generate AVIF for %s: %s',
                    $image_path,
                    $e->getMessage()
                ));
            }
            return false;
        }
    }

    public static function purge_attachment_cache($attachment_id)
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return;
        }

        $cache_root_dir = trailingslashit($upload_dir['basedir']) . self::CACHE_FOLDER;
        if (!is_dir($cache_root_dir)) {
            return;
        }

        $attachment_dir = trailingslashit($cache_root_dir) . (int) $attachment_id;
        if (!is_dir($attachment_dir)) {
            return;
        }

        // Recursively delete all files and subdirectories (handles new folder structure with size subdirs)
        self::delete_directory_recursive($attachment_dir);

        // Clear object cache for this attachment (flush wildcard cache keys)
        wp_cache_flush_group('soj_dynamic_images');
    }

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param string $dir Directory path to delete.
     * @return bool True on success, false on failure.
     */
    private static function delete_directory_recursive($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        // Security: Escape glob patterns to prevent injection
        $safe_dir = str_replace(['*', '?', '['], ['\*', '\?', '\['], $dir);
        
        $files = array_diff(scandir($safe_dir), ['.', '..']);
        foreach ($files as $file) {
            $path = trailingslashit($dir) . $file;
            if (is_dir($path)) {
                self::delete_directory_recursive($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Cleanup orphaned cache folders for deleted attachments.
     */
    public static function cleanup_orphaned_cache()
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return;
        }

        $cache_root_dir = trailingslashit($upload_dir['basedir']) . self::CACHE_FOLDER;
        if (!is_dir($cache_root_dir)) {
            return;
        }

        // Security: Escape glob patterns to prevent injection
        $safe_cache_root = str_replace(['*', '?', '['], ['\*', '\?', '\['], $cache_root_dir);
        $dirs = glob($safe_cache_root . '/*', GLOB_ONLYDIR) ?: [];
        $cleaned = 0;

        foreach ($dirs as $dir) {
            $attachment_id = (int) basename($dir);

            if ($attachment_id <= 0) {
                continue;
            }

            // Check if attachment still exists
            if (!get_post($attachment_id)) {
                // Orphaned - attachment deleted without hook firing
                self::purge_attachment_cache($attachment_id);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            error_log(sprintf('SOJ Dynamic Images: Cleaned up %d orphaned cache folder(s)', $cleaned));
        }

        // Check cache size and clean if needed
        $cache_size = self::get_cache_size();
        if ($cache_size > self::MAX_CACHE_SIZE) {
            error_log(sprintf('SOJ Dynamic Images: Cache size %d bytes exceeds limit, cleaning oldest files', $cache_size));
            self::cleanup_old_cache_files();
        }
    }

    /**
     * Get total cache size in bytes.
     *
     * @return int Total size in bytes
     */
    private static function get_cache_size()
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return 0;
        }

        $cache_root_dir = trailingslashit($upload_dir['basedir']) . self::CACHE_FOLDER;
        if (!is_dir($cache_root_dir)) {
            return 0;
        }

        $size = 0;
        $safe_cache_root = str_replace(['*', '?', '['], ['\*', '\?', '\['], $cache_root_dir);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($safe_cache_root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Clean oldest cache files when cache size exceeds limit.
     */
    private static function cleanup_old_cache_files()
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return;
        }

        $cache_root_dir = trailingslashit($upload_dir['basedir']) . self::CACHE_FOLDER;
        if (!is_dir($cache_root_dir)) {
            return;
        }

        // Get all cached files with their modification times
        $files = [];
        $safe_cache_root = str_replace(['*', '?', '['], ['\*', '\?', '\['], $cache_root_dir);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($safe_cache_root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'path' => $file->getPathname(),
                    'time' => $file->getMTime(),
                    'size' => $file->getSize(),
                ];
            }
        }

        // Sort by modification time (oldest first)
        usort($files, function ($a, $b) {
            return $a['time'] - $b['time'];
        });

        // Delete oldest files until we're under the limit
        $current_size = self::get_cache_size();
        $target_size = self::MAX_CACHE_SIZE * 0.8; // Clean to 80% of max
        $deleted = 0;

        foreach ($files as $file) {
            if ($current_size <= $target_size) {
                break;
            }

            if (@unlink($file['path'])) {
                $current_size -= $file['size'];
                $deleted++;
            }
        }

        if ($deleted > 0) {
            error_log(sprintf('SOJ Dynamic Images: Deleted %d old cached files to free space', $deleted));
        }
    }

    /**
     * Add admin menu for bulk WebP generation.
     */
    public static function add_admin_menu()
    {
        add_media_page(
            'Generate WebP Images',
            'Generate WebP',
            'manage_options',
            'soj-generate-webp',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin scripts for bulk WebP generation.
     */
    public static function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'media_page_soj-generate-webp') {
            return;
        }

        // Enqueue jQuery (should already be loaded, but ensure it's available)
        wp_enqueue_script('jquery');

        // Add inline script for AJAX processing
        $nonce = wp_create_nonce('soj_generate_webp_bulk');
        $script = <<<JS
jQuery(document).ready(function($) {
    var processing = false;
    var processed = 0;
    var total = 0;
    var failed = 0;
    var skipped = 0;
    var currentOffset = 0;

    $("#soj-generate-webp-start").on("click", function(e) {
        e.preventDefault();
        
        if (processing) {
            return;
        }

        processing = true;
        processed = 0;
        failed = 0;
        skipped = 0;
        total = parseInt($("#soj-webp-total").text()) || 0;
        currentOffset = 0;
        
        $("#soj-generate-webp-start").prop("disabled", true);
        $("#soj-webp-progress").show();
        $("#soj-webp-status").html("Starting...");
        $("#soj-webp-processed").text("0");
        $("#soj-webp-failed").text("0");
        $("#soj-webp-skipped").text("0");

        processBatch(0, true);
    });

    function processBatch(offset, resetCache) {
        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "soj_generate_webp_bulk",
                offset: offset,
                reset_cache: resetCache ? 1 : 0,
                nonce: "{$nonce}"
            },
            timeout: 90000,
            success: function(response) {
                if (response.success) {
                    processed += response.data.processed || 0;
                    failed += response.data.failed || 0;
                    skipped += response.data.skipped || 0;
                    
                    if (response.data.total !== undefined) {
                        total = response.data.total;
                    }
                    
                    // Use the offset returned by the server (it's the next offset to use)
                    if (response.data.offset !== undefined) {
                        currentOffset = response.data.offset;
                    } else {
                        // Fallback: calculate from current offset + items handled
                        currentOffset = offset + (response.data.processed || 0) + (response.data.failed || 0) + (response.data.skipped || 0);
                    }
                    
                    $("#soj-webp-processed").text(processed);
                    $("#soj-webp-failed").text(failed);
                    $("#soj-webp-skipped").text(skipped);
                    
                    var percentage = total > 0 ? Math.round((currentOffset / total) * 100) : 0;
                    if (percentage > 100) percentage = 100;
                    $("#soj-webp-progress-bar").css("width", percentage + "%");
                    $("#soj-webp-progress-text").text(percentage + "%");

                    if (response.data.more) {
                        $("#soj-webp-status").html(
                            "Processing... (" + currentOffset + " / " + total + ") " +
                            "Processed: " + processed + 
                            (failed > 0 ? ", Failed: " + failed : "") +
                            (skipped > 0 ? ", Skipped: " + skipped : "")
                        );
                        setTimeout(function() {
                            processBatch(currentOffset, false);
                        }, 100);
                    } else {
                        processing = false;
                        $("#soj-generate-webp-start").prop("disabled", false);
                        $("#soj-webp-status").html(
                            "<strong>Complete!</strong> Processed: " + processed + 
                            (failed > 0 ? ", Failed: " + failed : "") +
                            (skipped > 0 ? ", Skipped: " + skipped : "")
                        );
                    }
                } else {
                    processing = false;
                    $("#soj-generate-webp-start").prop("disabled", false);
                    $("#soj-webp-status").html("<span style=\"color: red;\">Error: " + (response.data || "Unknown error") + "</span>");
                }
            },
            error: function(xhr, status, error) {
                processing = false;
                $("#soj-generate-webp-start").prop("disabled", false);
                if (status === "timeout") {
                    $("#soj-webp-status").html(
                        "<span style=\"color: orange;\">Request timed out. Progress saved. " +
                        "You can click the button again to continue from where it left off.</span>"
                    );
                } else {
                    $("#soj-webp-status").html("<span style=\"color: red;\">AJAX error occurred: " + error + "</span>");
                }
            }
        });
    }
});
JS;
        wp_add_inline_script('jquery', $script, 'after');
    }

    /**
     * Render admin page for bulk WebP generation.
     */
    public static function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        // Check if Imagick is available
        $imagick_available = extension_loaded('imagick');
        
        // Count images that need WebP conversion
        $upload_dir = wp_upload_dir();
        $cache_root_dir = trailingslashit($upload_dir['basedir']) . self::CACHE_FOLDER;
        $total_images = 0;
        $images_with_webp = 0;
        $images_without_webp = 0;

        if (is_dir($cache_root_dir)) {
            $safe_cache_root = str_replace(['*', '?', '['], ['\*', '\?', '\['], $cache_root_dir);
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($safe_cache_root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, $allowed_extensions, true)) {
                        $total_images++;
                        $webp_path = preg_replace('/\.[^.]+$/', '.webp', $file->getPathname());
                        if (file_exists($webp_path)) {
                            $images_with_webp++;
                        } else {
                            $images_without_webp++;
                        }
                    }
                }
            }
        }

        ?>
        <div class="wrap">
            <h1>Generate WebP Images</h1>
            
            <?php if (!$imagick_available): ?>
                <div class="notice notice-error">
                    <p><strong>Imagick extension is not available.</strong> WebP generation requires the Imagick PHP extension to be installed and enabled.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p>This tool will generate WebP versions for all images in the <code>dynamic-images</code> folder that don't already have WebP versions.</p>
                    <p><strong>Note:</strong> If the process times out, you can click the button again to continue from where it left off. The system automatically skips images that already have WebP versions.</p>
                </div>

                <div class="card" style="max-width: 800px;">
                    <h2>Statistics</h2>
                    <table class="form-table">
                        <tr>
                            <th>Total Images</th>
                            <td><strong id="soj-webp-total"><?php echo esc_html($total_images); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Already Have WebP</th>
                            <td><?php echo esc_html($images_with_webp); ?></td>
                        </tr>
                        <tr>
                            <th>Need WebP Generation</th>
                            <td><strong style="color: #2271b1;"><?php echo esc_html($images_without_webp); ?></strong></td>
                        </tr>
                    </table>

                    <?php if ($images_without_webp > 0): ?>
                        <h2>Generate WebP Versions</h2>
                        <p>
                            <button type="button" id="soj-generate-webp-start" class="button button-primary button-large">
                                Generate WebP for <?php echo esc_html($images_without_webp); ?> Images
                            </button>
                        </p>

                        <div id="soj-webp-progress" style="display: none; margin-top: 20px;">
                            <div style="background: #f0f0f1; border-radius: 4px; height: 30px; position: relative; overflow: hidden;">
                                <div id="soj-webp-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                                <div id="soj-webp-progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; color: #000;">0%</div>
                            </div>
                            <p id="soj-webp-status" style="margin-top: 10px;">Ready to start...</p>
                            <p>
                                Processed: <strong id="soj-webp-processed">0</strong> | 
                                Failed: <strong id="soj-webp-failed">0</strong> |
                                Skipped: <strong id="soj-webp-skipped">0</strong>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-success inline">
                            <p><strong>All images already have WebP versions!</strong></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX handler for bulk WebP generation.
     */
    public static function ajax_generate_webp_bulk()
    {
        check_ajax_referer('soj_generate_webp_bulk', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (!extension_loaded('imagick')) {
            wp_send_json_error('Imagick extension is not available');
        }

        // Increase time limit for batch processing
        @set_time_limit(60);
        @ini_set('max_execution_time', 60);

        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
        $reset_cache = isset($_POST['reset_cache']) ? (bool) $_POST['reset_cache'] : false;
        $batch_size = 5; // Reduced to 5 images per batch to avoid timeouts

        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            wp_send_json_error('Upload directory error');
        }

        $cache_root_dir = trailingslashit($upload_dir['basedir']) . self::CACHE_FOLDER;
        if (!is_dir($cache_root_dir)) {
            wp_send_json_error('Cache directory does not exist');
        }

        // Get or build cached list of images that need WebP generation
        $cache_key = 'soj_webp_bulk_images';
        $images = get_transient($cache_key);

        if ($reset_cache || $images === false || $offset === 0) {
            // Build list of images that need WebP generation (skip ones that already have WebP)
            $images = [];
            $safe_cache_root = str_replace(['*', '?', '['], ['\*', '\?', '\['], $cache_root_dir);
            $allowed_extensions = ['jpg', 'jpeg', 'png'];

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($safe_cache_root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, $allowed_extensions, true)) {
                        // Skip if WebP version already exists
                        $webp_path = preg_replace('/\.[^.]+$/', '.webp', $file->getPathname());
                        if (!file_exists($webp_path)) {
                            $images[] = $file->getPathname();
                        }
                    }
                }
            }

            // Cache the list for 1 hour
            set_transient($cache_key, $images, HOUR_IN_SECONDS);
        }

        // Process batch (only images that don't have WebP yet)
        $processed = 0;
        $failed = 0;
        $skipped = 0;
        $batch = array_slice($images, $offset, $batch_size);
        $items_handled = 0; // Track how many items we actually processed (including skipped)
        
        foreach ($batch as $image_path) {
            $items_handled++; // Count every item we attempt to process
            
            // Double-check WebP doesn't exist (might have been generated in another process)
            $webp_path = preg_replace('/\.[^.]+$/', '.webp', $image_path);
            if (file_exists($webp_path)) {
                $skipped++;
                continue;
            }

            // generate_webp_version() also checks if WebP exists and skips if it does
            $result = self::generate_webp_version($image_path);
            if ($result) {
                $processed++;
            } else {
                // Check if it was created (might have been created between check and generation)
                if (file_exists($webp_path)) {
                    $skipped++;
                } else {
                    $failed++;
                }
            }
        }

        // Calculate next offset: current offset + number of items we handled
        $next_offset = $offset + $items_handled;
        
        // Check if there are more images to process
        $more = $next_offset < count($images);

        wp_send_json_success([
            'processed' => $processed,
            'failed' => $failed,
            'skipped' => $skipped,
            'more' => $more,
            'total' => count($images),
            'offset' => $next_offset // Return the next offset to use
        ]);
    }
}

SOJ_Dynamic_Images::init();

/**
 * AVIF output is intentionally left disabled.
 *
 * The AVIF code path remains in the class, but this ImageMagick/libheif build
 * encodes AVIF inefficiently (output is equal to or larger than WebP at matched
 * settings), so enabling it only adds CPU cost without a size benefit. To revisit
 * with a better encoder, set define('SOJ_DYNAMIC_IMAGES_AVIF', true) in
 * wp-config.php or add: add_filter('soj_dynamic_images_enable_avif', '__return_true');
 */

/**
 * Exclude dynamic-images folder from Imagify optimization to prevent re-compression
 * of already optimized images.
 */
add_filter('imagify_add_forbidden_folders', function($folders) {
    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
        return $folders;
    }
    
    $dynamic_images_dir = trailingslashit($upload_dir['basedir']) . SOJ_Dynamic_Images::CACHE_FOLDER;
    $folders[] = $dynamic_images_dir;
    
    return $folders;
}, 10, 1);

/**
 * Whether the Imagify plugin is available.
 *
 * @return bool
 */
function soj_dynamic_images_imagify_is_active()
{
    return function_exists('imagify');
}

/**
 * Purge dynamic-image cache for a WordPress Media Library attachment.
 *
 * @param object $process Imagify optimization process (ProcessInterface).
 * @return void
 */
function soj_dynamic_images_maybe_purge_cache_for_imagify_process($process)
{
    if (!is_object($process) || !method_exists($process, 'get_media')) {
        return;
    }

    $media = $process->get_media();

    if (!$media || !method_exists($media, 'get_context') || 'wp' !== $media->get_context()) {
        return;
    }

    if (method_exists($media, 'is_image') && !$media->is_image()) {
        return;
    }

    $attachment_id = method_exists($media, 'get_id') ? (int) $media->get_id() : 0;

    if ($attachment_id <= 0) {
        return;
    }

    SOJ_Dynamic_Images::purge_attachment_cache($attachment_id);
}

/**
 * After Imagify optimizes the full-size file, rebuild dynamic derivatives from the new master.
 *
 * @param object $process            Imagify process instance.
 * @param object $file               Imagify file instance (unused).
 * @param string $thumb_size         Size key being optimized.
 * @param int    $optimization_level Optimization level (unused).
 * @param bool   $next_gen           Whether next-gen was generated (unused).
 * @param bool   $is_disabled        Whether this size is skipped.
 * @return void
 */
function soj_dynamic_images_purge_cache_after_imagify_size($process, $file, $thumb_size, $optimization_level, $next_gen, $is_disabled)
{
    if ($is_disabled || 'full' !== (string) $thumb_size) {
        return;
    }

    soj_dynamic_images_maybe_purge_cache_for_imagify_process($process);
}

/**
 * After Imagify restores a media item, purge cache so derivatives match the restored file.
 *
 * @param object         $process  Imagify process instance.
 * @param bool|WP_Error  $response Restore result.
 * @param array          $files    Files list (unused).
 * @param array          $data     Prior optimization data (unused).
 * @return void
 */
function soj_dynamic_images_purge_cache_after_imagify_restore($process, $response, $files, $data)
{
    if (is_wp_error($response)) {
        return;
    }

    soj_dynamic_images_maybe_purge_cache_for_imagify_process($process);
}

/**
 * Register Imagify hooks once the plugin has loaded.
 */
add_action('plugins_loaded', function () {
    if (!soj_dynamic_images_imagify_is_active()) {
        return;
    }

    add_action('imagify_after_optimize_size', 'soj_dynamic_images_purge_cache_after_imagify_size', 10, 6);
    add_action('imagify_after_restore_media', 'soj_dynamic_images_purge_cache_after_imagify_restore', 10, 4);
}, 20);

/**
 * Retrieve responsive picture preset settings.
 *
 * @param string $preset  Preset key.
 * @param array  $context Optional. Context to pass to filters.
 *
 * @return array{
 *     sources: array<int, array{media?:string,width?:int,height?:int,crop?:bool,fallback?:bool}>,
 *     fallback?: array{width?:int,height?:int,crop?:bool}
 * }
 */
function soj_dynamic_images_get_picture_preset($preset, array $context = [])
{
    $presets = apply_filters(
        'soj_dynamic_images_picture_presets',
        [],
        $context
    );

    $config = $presets[$preset] ?? [
        'sources'  => [],
        'fallback' => [],
    ];

    $config['sources']  = is_array($config['sources']) ? $config['sources'] : [];
    $config['fallback'] = is_array($config['fallback']) ? $config['fallback'] : [];

    return $config;
}

/**
 * Build srcset string with explicit descriptors (1x/2x or w descriptors).
 *
 * @param string      $url          Primary image URL.
 * @param string|null $retina_url   Retina image URL (optional).
 * @param bool        $use_width    If true, use 'w' descriptors; if false, use 'x' descriptors.
 * @param int         $width        Image width for 'w' descriptor.
 * @param int         $retina_width Retina image width for 'w' descriptor.
 * @return string Escaped srcset string.
 */
function soj_build_srcset($url, $retina_url = null, $use_width = false, $width = 0, $retina_width = 0)
{
    $url = esc_url($url);
    
    if ($use_width && $width > 0) {
        // Use width descriptors: "url 320w, url 640w"
        $srcset = sprintf('%s %dw', $url, $width);
        if ($retina_url && $retina_width > 0) {
            $srcset .= ', ' . esc_url($retina_url) . ' ' . $retina_width . 'w';
        }
    } else {
        // Use density descriptors: "url 1x, url 2x"
        $srcset = sprintf('%s 1x', $url);
        if ($retina_url) {
            $srcset .= ', ' . esc_url($retina_url) . ' 2x';
        }
    }
    
    return $srcset;
}

/**
 * Generate default sizes attribute based on breakpoints.
 *
 * @param array $sources Array of source definitions with 'media' keys.
 * @param array $fallback Fallback image definition.
 * @return string Sizes attribute value.
 */
function soj_generate_default_sizes($sources, $fallback = [])
{
    $sizes_parts = [];
    
    // Extract breakpoints from sources (media queries like "(min-width: 1280px)")
    foreach ($sources as $source) {
        $media = isset($source['media']) ? (string) $source['media'] : '';
        if (empty($media)) {
            continue;
        }
        
        // Extract min-width value from media query
        if (preg_match('/\(min-width:\s*(\d+)px\)/', $media, $matches)) {
            $breakpoint = (int) $matches[1];
            $image = $source['image'] ?? [];
            $width = isset($image['width']) ? (int) $image['width'] : 0;
            
            if ($width > 0) {
                $sizes_parts[] = sprintf('(min-width: %dpx) %dpx', $breakpoint, $width);
            }
        }
    }
    
    // Add fallback size (smallest/default)
    $fallback_width = isset($fallback['width']) ? (int) $fallback['width'] : 320;
    $sizes_parts[] = $fallback_width . 'px';
    
    return implode(', ', $sizes_parts);
}

/**
 * Validate image dimensions against sizes attribute (debug mode only).
 *
 * @param array  $sources Array of source definitions.
 * @param array  $fallback Fallback image definition.
 * @param string $sizes Sizes attribute value.
 * @return void Logs warnings if dimensions don't match expectations.
 */
function soj_validate_image_dimensions($sources, $fallback, $sizes)
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    // Extract smallest size from sizes attribute
    $smallest_size = 320; // default
    if (preg_match_all('/(\d+)px/', $sizes, $matches)) {
        $sizes_found = array_map('intval', $matches[1]);
        $smallest_size = min($sizes_found);
    }
    
    // Check if fallback is smaller than smallest size
    $fallback_width = isset($fallback['width']) ? (int) $fallback['width'] : 0;
    if ($fallback_width > 0 && $fallback_width < $smallest_size) {
        error_log(sprintf(
            'SOJ Dynamic Images: Fallback image width (%dpx) is smaller than smallest size in sizes attribute (%dpx). Consider generating a smaller derivative.',
            $fallback_width,
            $smallest_size
        ));
    }
    
    // Check all sources
    foreach ($sources as $source) {
        $image = $source['image'] ?? [];
        $width = isset($image['width']) ? (int) $image['width'] : 0;
        
        if ($width > 0 && $width < $smallest_size) {
            $media = isset($source['media']) ? $source['media'] : 'default';
            error_log(sprintf(
                'SOJ Dynamic Images: Source image width (%dpx) for %s is smaller than smallest size in sizes attribute (%dpx).',
                $width,
                $media,
                $smallest_size
            ));
        }
    }
}

/**
 * Debug logging for picture element generation (requires WP_DEBUG and SOJ_DYNAMIC_IMAGES_DEBUG).
 *
 * @param int   $attachment_id Attachment ID.
 * @param array $sources Array of source definitions.
 * @param array $fallback Fallback image definition.
 * @param array $args Render arguments.
 * @return void Logs debug information.
 */
function soj_log_picture_debug($attachment_id, $sources, $fallback, $args)
{
    if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('SOJ_DYNAMIC_IMAGES_DEBUG') || !SOJ_DYNAMIC_IMAGES_DEBUG) {
        return;
    }
    
    $mode = !empty($args['use_width_descriptors']) ? 'width+sizes' : 'density';
    $sizes_attr = !empty($args['sizes']) ? $args['sizes'] : 'none';
    
    error_log(sprintf(
        'SOJ Dynamic Images Debug [Attachment %d]: Mode=%s, Sizes=%s, Sources=%d, Fallback=%s',
        $attachment_id,
        $mode,
        $sizes_attr,
        count($sources),
        !empty($fallback['src']) ? 'yes' : 'no'
    ));
    
    foreach ($sources as $index => $source) {
        $image = $source['image'] ?? [];
        $media = isset($source['media']) ? $source['media'] : 'default';
        $has_webp = !empty($image['src_webp']);
        $has_retina = !empty($image['retina']);
        
        error_log(sprintf(
            '  Source %d [%s]: WebP=%s, Retina=%s, Dimensions=%dx%d',
            $index + 1,
            $media,
            $has_webp ? 'yes' : 'no',
            $has_retina ? 'yes' : 'no',
            isset($image['width']) ? $image['width'] : 0,
            isset($image['height']) ? $image['height'] : 0
        ));
    }
}

/**
 * Render a responsive <picture> element using the dynamic image generator.
 *
 * @param int   $attachment_id Attachment ID.
 * @param array $args {
 *     Optional. Render arguments.
 *
 *     @type string $preset        Preset key for sources/fallback.
 *     @type array  $breakpoints   Array of breakpoint definitions. Keys can be min-width integers
 *                                 (e.g. 1024) or full media query strings. Values accept either
 *                                 ['width' => 123, 'height' => 456, 'crop' => true, 'fallback' => false]
 *                                 or a numerically indexed array [123, 456, true, false].
 *     @type array  $fallback_size Optional fallback dimensions in the same format as breakpoint values.
 *     @type array  $sources       Source definitions (overrides preset).
 *     @type array  $fallback      Fallback definition (overrides preset).
 *     @type string $class         Class attribute for <picture>.
 *     @type string $img_class     Class attribute for <img>.
 *     @type string $alt           Alt text.
 *     @type string $loading       Loading attribute, defaults to 'lazy'.
 *     @type string $decoding      Decoding attribute, defaults to 'async'.
 *     @type string $fetchpriority Fetchpriority attribute.
 *     @type bool   $is_lcp        If true, sets loading="eager" and fetchpriority="high" for LCP image.
 *     @type bool   $priority      Alias for is_lcp.
 *     @type bool   $preload       Whether to add a <link rel="preload"> for first source.
 *     @type bool   $defer_browser_load Store real <source>/<img> URLs in a data attribute and
 *                                 output a placeholder <img> until the picture intersects
 *                                 the viewport (prevents network fetch on initial load).
 *     @type array  $attributes    Additional attributes for <picture> (key => value).
 *     @type array  $img_attributes Additional attributes for <img> (key => value).
 * }
 *
 * @return string HTML markup.
 */
function soj_render_responsive_picture($attachment_id, array $args = [])
{
    // Handle ACF image array
    if (is_array($attachment_id)) {
        $attachment_id = $attachment_id['ID'] ?? 0;
        if (!isset($args['alt']) && isset($attachment_id['alt'])) {
            $args['alt'] = $attachment_id['alt'];
        }
    }
    
    $attachment_id = (int) $attachment_id;
    
    if (!$attachment_id) {
        return '';
    }

    $defaults = [
        'preset'                => '',
        'sources'               => [],
        'fallback'              => [],
        'class'                 => '',
        'img_class'             => '',
        'alt'                   => '',
        'loading'               => 'lazy',
        'decoding'              => 'async',
        'fetchpriority'         => '',
        'is_lcp'                => false,
        'priority'              => false,
        'preload'               => false,
        'defer_browser_load'    => false,
        'use_width_descriptors' => false, // Use 'w' descriptors + sizes instead of 'x' descriptors
        'sizes'                 => '', // Sizes attribute (auto-generated if use_width_descriptors is true and sizes is empty)
        'attributes'            => [],
        'img_attributes'        => [],
    ];

    $args = array_merge($defaults, $args);
    
    // Handle LCP/priority flag
    // Note: Only apply fetchpriority="high" to a single LCP image per page
    if (!empty($args['is_lcp']) || !empty($args['priority'])) {
        $args['loading'] = 'eager';
        $args['fetchpriority'] = 'high';
    }
    
    // WordPress doesn't allow lazy loading with high priority
    // If fetchpriority is set to high, automatically set loading to eager
    if (!empty($args['fetchpriority']) && $args['fetchpriority'] === 'high' && $args['loading'] === 'lazy') {
        $args['loading'] = 'eager';
    }
    
    // If loading is lazy and fetchpriority is high, remove fetchpriority to avoid WordPress warning
    if ($args['loading'] === 'lazy' && !empty($args['fetchpriority']) && $args['fetchpriority'] === 'high') {
        $args['fetchpriority'] = '';
    }

    if (!empty($args['breakpoints'])) {
        $converted_sources = [];

        foreach ($args['breakpoints'] as $media_key => $size_config) {
            if (!is_array($size_config)) {
                continue;
            }

            $width  = isset($size_config['width']) ? (int) $size_config['width'] : (int) ($size_config[0] ?? 0);
            $height = isset($size_config['height']) ? (int) $size_config['height'] : (int) ($size_config[1] ?? 0);
            $crop   = array_key_exists('crop', $size_config) ? (bool) $size_config['crop'] : (bool) ($size_config[2] ?? true);
            $is_fallback = array_key_exists('fallback', $size_config) ? (bool) $size_config['fallback'] : (bool) ($size_config[3] ?? false);

            if (!$width && !$height) {
                continue;
            }

            if (is_numeric($media_key) && (int) $media_key > 0) {
                $media = sprintf('(min-width: %dpx)', (int) $media_key);
            } elseif (is_string($media_key) && trim($media_key) !== '') {
                $media = $media_key;
            } else {
                $media = '';
            }

            $converted_sources[] = [
                'media'    => $media,
                'width'    => $width,
                'height'   => $height,
                'crop'     => $crop,
                'fallback' => $is_fallback,
            ];
        }

        if (!empty($converted_sources)) {
            if (!empty($args['sources'])) {
                $args['sources'] = array_merge($converted_sources, $args['sources']);
            } else {
                $args['sources'] = $converted_sources;
            }
        }
    }

    if (empty($args['fallback']) && !empty($args['fallback_size']) && is_array($args['fallback_size'])) {
        $fallback_size = $args['fallback_size'];
        $fallback_width  = isset($fallback_size['width']) ? (int) $fallback_size['width'] : (int) ($fallback_size[0] ?? 0);
        $fallback_height = isset($fallback_size['height']) ? (int) $fallback_size['height'] : (int) ($fallback_size[1] ?? 0);
        $fallback_crop   = array_key_exists('crop', $fallback_size) ? (bool) $fallback_size['crop'] : (bool) ($fallback_size[2] ?? true);

        if ($fallback_width || $fallback_height) {
            $args['fallback'] = [
                'width'  => $fallback_width,
                'height' => $fallback_height,
                'crop'   => $fallback_crop,
            ];
        }
    }

    unset($args['breakpoints'], $args['fallback_size']);

    if ($args['preset']) {
        $preset = soj_dynamic_images_get_picture_preset($args['preset'], $args);

        if (empty($args['sources'])) {
            $args['sources'] = $preset['sources'] ?? [];
        }

        if (empty($args['fallback'])) {
            $args['fallback'] = $preset['fallback'] ?? [];
        }
    }

    $picture_data = soj_prepare_picture_sources(
        $attachment_id,
        [
            'sources'  => $args['sources'],
            'fallback' => $args['fallback'],
            'retina'   => isset($args['retina']) ? (bool) $args['retina'] : false,
        ]
    );

    $sources  = $picture_data['sources'];
    $fallback = $picture_data['fallback'];

    if (empty($sources) && empty($fallback)) {
        return '';
    }

    // Generate sizes attribute if using width descriptors and not provided
    if ($args['use_width_descriptors'] && empty($args['sizes'])) {
        $args['sizes'] = soj_generate_default_sizes($sources, $fallback);
    }
    
    // Validate dimensions if in debug mode
    if (defined('WP_DEBUG') && WP_DEBUG && !empty($args['sizes'])) {
        soj_validate_image_dimensions($sources, $fallback, $args['sizes']);
    }
    
    // Debug logging (if WP_DEBUG and SOJ_DYNAMIC_IMAGES_DEBUG are enabled)
    if (defined('WP_DEBUG') && WP_DEBUG && defined('SOJ_DYNAMIC_IMAGES_DEBUG') && SOJ_DYNAMIC_IMAGES_DEBUG) {
        soj_log_picture_debug($attachment_id, $sources, $fallback, $args);
    }

    $primary_image = null;
    if (!empty($sources[0]['image']) && is_array($sources[0]['image'])) {
        $primary_image = $sources[0]['image'];
    } elseif (is_array($fallback) && !empty($fallback['src'])) {
        $primary_image = $fallback;
    }

    if ($args['preload'] && $primary_image && !empty($primary_image['src'])) {
        static $preloaded_srcs = [];

        if (!isset($preloaded_srcs[$primary_image['src']])) {
            $preloaded_srcs[$primary_image['src']] = true;

            add_action(
                'wp_head',
                function () use ($primary_image) {
                    printf(
                        '<link rel="preload" as="image" href="%s" />',
                        esc_url($primary_image['src'])
                    );
                },
                5
            );
        }
    }

    $picture_attrs = [];

    $picture_class = trim((string) $args['class']);
    if ($picture_class !== '') {
        $picture_attrs['class'] = $picture_class;
    }

    foreach ($args['attributes'] as $attr_key => $attr_value) {
        if ($attr_value === null || $attr_value === '') {
            continue;
        }

        $picture_attrs[$attr_key] = $attr_value;
    }

    // Get fallback image (JPEG) - this is what the <img> element will use
    $fallback_jpeg = $fallback['src_original'] ?? $fallback['src'] ?? '';
    
    // If no fallback, try to get from primary image
    if (empty($fallback_jpeg)) {
        $fallback_jpeg = $primary_image['src_original'] ?? $primary_image['src'] ?? '';
    }
    
    // If still no image, fall back to WordPress attachment URL
    if (empty($fallback_jpeg)) {
        $fallback_jpeg = wp_get_attachment_image_url($attachment_id, 'full');
        if (empty($fallback_jpeg)) {
            return ''; // No image available
        }
        // Get dimensions from original attachment
        $metadata = wp_get_attachment_metadata($attachment_id);
        $fallback_width = $metadata['width'] ?? 0;
        $fallback_height = $metadata['height'] ?? 0;
    } else {
        // Use dimensions from fallback image (these match the actual JPEG file)
        $fallback_width = isset($fallback['width']) ? (int) $fallback['width'] : (isset($primary_image['width']) ? (int) $primary_image['width'] : 0);
        $fallback_height = isset($fallback['height']) ? (int) $fallback['height'] : (isset($primary_image['height']) ? (int) $primary_image['height'] : 0);
    }

    // Get alt text from attachment meta if not provided
    $alt_text = $args['alt'] ?: get_post_meta($attachment_id, '_wp_attachment_image_alt', true) ?: '';

    $img_attrs = [
        'class'        => trim((string) $args['img_class']),
        'src'          => esc_url($fallback_jpeg),
        'alt'          => esc_attr($alt_text),
        'loading'      => (string) $args['loading'],
        'decoding'     => (string) $args['decoding'],
    ];
    
    // Add fetchpriority only if set (should only be used for single LCP image per page)
    // WordPress doesn't allow lazy loading with high priority - ensure they're not both set
    if (!empty($args['fetchpriority']) && $args['fetchpriority'] === 'high') {
        // If fetchpriority is high, ensure loading is eager (not lazy)
        if ($img_attrs['loading'] === 'lazy') {
            $img_attrs['loading'] = 'eager';
        }
        $img_attrs['fetchpriority'] = 'high';
    } elseif (!empty($args['fetchpriority'])) {
        $fetchpriority = (string) $args['fetchpriority'];
        // "low" is safe with lazy; "high" is handled above and must not pair with lazy.
        if ($fetchpriority === 'low' || $img_attrs['loading'] !== 'lazy') {
            $img_attrs['fetchpriority'] = $fetchpriority;
        }
    }
    
    // Add sizes attribute if using width descriptors
    if ($args['use_width_descriptors'] && !empty($args['sizes'])) {
        $img_attrs['sizes'] = esc_attr($args['sizes']);
    }
    
    // Add width/height attributes matching the actual fallback image dimensions
    if ($fallback_width > 0 && $fallback_height > 0) {
        $img_attrs['width'] = $fallback_width;
        $img_attrs['height'] = $fallback_height;
    }
    
    // NO srcset on <img> when using <picture> - all responsive candidates are in <source> tags

    foreach ($args['img_attributes'] as $attr_key => $attr_value) {
        if ($attr_value === null || $attr_value === '') {
            continue;
        }

        $img_attrs[$attr_key] = $attr_value;
    }

    if (empty($img_attrs['src'])) {
        return '';
    }

    if (empty($img_attrs['width'])) {
        unset($img_attrs['width']);
    }

    if (empty($img_attrs['height'])) {
        unset($img_attrs['height']);
    }

    if (empty($img_attrs['fetchpriority'])) {
        unset($img_attrs['fetchpriority']);
    }

    $use_width_descriptors = !empty($args['use_width_descriptors']);
    $defer_browser_load = !empty($args['defer_browser_load']);

    if ($defer_browser_load) {
        $existing_class = isset($picture_attrs['class']) ? (string) $picture_attrs['class'] : '';
        $picture_attrs['class'] = trim($existing_class . ' soj-picture--deferred');
    }

    $picture_inner = '';

    foreach ($sources as $source) {
        $media = isset($source['media']) ? (string) $source['media'] : '';
        $image = $source['image'] ?? [];

        if (empty($image['src'])) {
            continue;
        }

        $has_retina = !empty($image['retina']) && !empty($image['retina']['src']);
        $image_width = isset($image['width']) ? (int) $image['width'] : 0;
        $retina_width = $has_retina && isset($image['retina']['width']) ? (int) $image['retina']['width'] : ($image_width * 2);

        // AVIF source (listed first so supporting browsers prefer it over WebP/JPEG).
        if (!empty($image['src_avif'])) {
            $avif_srcset = soj_build_srcset(
                $image['src_avif'],
                $has_retina ? ($image['retina']['src_avif'] ?? null) : null,
                $use_width_descriptors,
                $image_width,
                $retina_width
            );

            $picture_inner .= sprintf(
                '<source type="image/avif"%s srcset="%s">',
                $media ? sprintf(' media="%s"', esc_attr($media)) : '',
                $avif_srcset
            );
        }

        // WebP source
        if (!empty($image['src_webp'])) {
            $webp_srcset = soj_build_srcset(
                $image['src_webp'],
                $has_retina ? ($image['retina']['src_webp'] ?? null) : null,
                $use_width_descriptors,
                $image_width,
                $retina_width
            );
            
            $picture_inner .= sprintf(
                '<source type="image/webp"%s srcset="%s">',
                $media ? sprintf(' media="%s"', esc_attr($media)) : '',
                $webp_srcset
            );
        }

        // JPEG source (always provide JPEG fallback)
        $jpeg_src = isset($image['src_original']) ? $image['src_original'] : $image['src'];
        $retina_jpeg = $has_retina ? (isset($image['retina']['src_original']) ? $image['retina']['src_original'] : ($image['retina']['src'] ?? null)) : null;
        
        $jpeg_srcset = soj_build_srcset(
            $jpeg_src,
            $retina_jpeg,
            $use_width_descriptors,
            $image_width,
            $retina_width
        );
        
        $picture_inner .= sprintf(
            '<source type="image/jpeg"%s srcset="%s">',
            $media ? sprintf(' media="%s"', esc_attr($media)) : '',
            $jpeg_srcset
        );
    }

    // Fallback AVIF (before WebP so it takes priority where supported).
    if (!empty($fallback['src_avif'])) {
        $fallback_width = isset($fallback['width']) ? (int) $fallback['width'] : 0;
        $fallback_retina_width = !empty($fallback['retina']) && isset($fallback['retina']['width'])
            ? (int) $fallback['retina']['width']
            : ($fallback_width * 2);

        $fallback_avif_srcset = soj_build_srcset(
            $fallback['src_avif'],
            !empty($fallback['retina']) ? ($fallback['retina']['src_avif'] ?? null) : null,
            $use_width_descriptors,
            $fallback_width,
            $fallback_retina_width
        );

        $picture_inner .= sprintf(
            '<source type="image/avif" srcset="%s">',
            $fallback_avif_srcset
        );
    }

    // Fallback WebP
    if (!empty($fallback['src_webp'])) {
        $fallback_width = isset($fallback['width']) ? (int) $fallback['width'] : 0;
        $fallback_retina_width = !empty($fallback['retina']) && isset($fallback['retina']['width']) 
            ? (int) $fallback['retina']['width'] 
            : ($fallback_width * 2);
        
        $fallback_webp_srcset = soj_build_srcset(
            $fallback['src_webp'],
            !empty($fallback['retina']) ? ($fallback['retina']['src_webp'] ?? null) : null,
            $use_width_descriptors,
            $fallback_width,
            $fallback_retina_width
        );
        
        $picture_inner .= sprintf(
            '<source type="image/webp" srcset="%s">',
            $fallback_webp_srcset
        );
    }

    // Fallback JPEG source
    $fallback_jpeg_src = isset($fallback['src_original']) ? $fallback['src_original'] : ($fallback['src'] ?? '');
    if ($fallback_jpeg_src) {
        $fallback_width = isset($fallback['width']) ? (int) $fallback['width'] : 0;
        $fallback_retina_width = !empty($fallback['retina']) && isset($fallback['retina']['width'])
            ? (int) $fallback['retina']['width']
            : ($fallback_width * 2);
        
        $fallback_retina_jpeg = !empty($fallback['retina']) 
            ? (isset($fallback['retina']['src_original']) ? $fallback['retina']['src_original'] : ($fallback['retina']['src'] ?? null))
            : null;
        
        $fallback_jpeg_srcset = soj_build_srcset(
            $fallback_jpeg_src,
            $fallback_retina_jpeg,
            $use_width_descriptors,
            $fallback_width,
            $fallback_retina_width
        );
        
        $picture_inner .= sprintf(
            '<source type="image/jpeg" srcset="%s">',
            $fallback_jpeg_srcset
        );
    }

    $img_tag = '<img';
    foreach ($img_attrs as $attr_key => $attr_value) {
        $img_tag .= sprintf(' %s="%s"', esc_attr($attr_key), esc_attr($attr_value));
    }
    $img_tag .= '>';

    if ($defer_browser_load) {
        $picture_attrs['data-soj-defer-inner'] = base64_encode($picture_inner . $img_tag);

        $placeholder_src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $placeholder_img_attrs = $img_attrs;
        $placeholder_img_attrs['src'] = $placeholder_src;
        unset($placeholder_img_attrs['srcset']);

        $rendered = '<picture';
        foreach ($picture_attrs as $attr_key => $attr_value) {
            $rendered .= sprintf(' %s="%s"', esc_attr($attr_key), esc_attr($attr_value));
        }
        $rendered .= '><img';
        foreach ($placeholder_img_attrs as $attr_key => $attr_value) {
            $rendered .= sprintf(' %s="%s"', esc_attr($attr_key), esc_attr($attr_value));
        }
        $rendered .= '></picture>';

        return $rendered;
    }

    $rendered = '<picture';
    foreach ($picture_attrs as $attr_key => $attr_value) {
        $rendered .= sprintf(' %s="%s"', esc_attr($attr_key), esc_attr($attr_value));
    }
    $rendered .= '>';
    $rendered .= $picture_inner;
    $rendered .= $img_tag;
    $rendered .= '</picture>';

    return $rendered;
}

/**
 * Prepare responsive image sources and fallback data for a picture element.
 *
 * @param int   $attachment_id Attachment ID for the image.
 * @param array $config {
 *     Optional. Configuration for generated sources.
 *
 *     @type array $sources  Array of source configurations. Each item accepts:
 *                           'media'   string Media query for the source.
 *                           'width'   int    Target width.
 *                           'height'  int    Target height.
 *                           'crop'    bool   Whether to crop. Default true.
 *                           'fallback' bool  Flag current source as the fallback image.
 *     @type array $fallback Optional fallback configuration with keys matching $sources items.
 * }
 *
 * @return array {
 *     @type array $sources
 *     @type array|null $fallback
 * }
 */
function soj_prepare_picture_sources($attachment_id, array $config = [])
{
    $defaults = [
        'sources'  => [],
        'fallback' => null,
    ];

    $config   = array_merge($defaults, $config);
    $response = [
        'sources'  => [],
        'fallback' => null,
    ];

    if (!$attachment_id) {
        return $response;
    }

    // Check if retina is enabled
    $retina = isset($config['retina']) ? (bool) $config['retina'] : false;

    foreach ($config['sources'] as $source_config) {
        $width  = isset($source_config['width']) ? (int) $source_config['width'] : 0;
        $height = isset($source_config['height']) ? (int) $source_config['height'] : 0;
        $crop   = array_key_exists('crop', $source_config) ? (bool) $source_config['crop'] : true;
        $media  = isset($source_config['media']) ? (string) $source_config['media'] : '';

        if (!$width && !$height) {
            continue;
        }

        $image = SOJ_Dynamic_Images::get_image_src($attachment_id, $width, $height, $crop);

        if (!$image || empty($image['src'])) {
            continue;
        }

        // If retina is enabled, generate 2x version
        if ($retina) {
            $retina_width = $width * 2;
            $retina_height = $height * 2;
            $retina_image = SOJ_Dynamic_Images::get_image_src($attachment_id, $retina_width, $retina_height, $crop);
            
            if ($retina_image && !empty($retina_image['src'])) {
                // Store retina version in the image array
                $image['retina'] = $retina_image;
            }
        }

        $response['sources'][] = [
            'media' => $media,
            'image' => $image,
        ];

        if (!$response['fallback'] && !empty($source_config['fallback'])) {
            $response['fallback'] = $image;
        }
    }

    if (!$response['fallback']) {
        $fallback_config = $config['fallback'];

        if (is_array($fallback_config) && (!empty($fallback_config['width']) || !empty($fallback_config['height']))) {
            $width  = isset($fallback_config['width']) ? (int) $fallback_config['width'] : 0;
            $height = isset($fallback_config['height']) ? (int) $fallback_config['height'] : 0;
            $crop   = array_key_exists('crop', $fallback_config) ? (bool) $fallback_config['crop'] : true;

            $fallback_image = SOJ_Dynamic_Images::get_image_src($attachment_id, $width, $height, $crop);

            if ($fallback_image && !empty($fallback_image['src'])) {
                // If retina is enabled, generate 2x version for fallback too
                if ($retina) {
                    $retina_width = $width * 2;
                    $retina_height = $height * 2;
                    $retina_fallback = SOJ_Dynamic_Images::get_image_src($attachment_id, $retina_width, $retina_height, $crop);
                    
                    if ($retina_fallback && !empty($retina_fallback['src'])) {
                        $fallback_image['retina'] = $retina_fallback;
                    }
                }
                
                $response['fallback'] = $fallback_image;
            }
        } elseif (!empty($response['sources'])) {
            $last_source           = end($response['sources']);
            $response['fallback'] = $last_source['image'];
        }
    }

    return $response;
}

/**
 * Simplified responsive picture helper with cleaner syntax
 *
 * @param int|array $image ACF image array or attachment ID
 * @param array $sizes Array of [breakpoint => [width, height, crop]] where breakpoint is min-width in px, or 0 for fallback
 *                     If empty, uses smart defaults based on original image dimensions
 * @param array $args Additional arguments (class, img_class, loading, fetchpriority, preload, retina, etc.)
 * @return string HTML picture element or empty string
 *
 * @example
 * // Simplest usage - auto-generates responsive sizes
 * echo soj_picture($background);
 * 
 * // With retina support
 * echo soj_picture($background, [], ['retina' => true]);
 *
 */
function soj_picture($image, $sizes = [], $args = [])
{
    // Handle ACF image array
    if (is_array($image)) {
        $attachment_id = $image['ID'] ?? 0;
        if (!isset($args['alt'])) {
            $args['alt'] = $image['alt'] ?? '';
        }
    } else {
        $attachment_id = (int) $image;
    }

    if (!$attachment_id) {
        return '';
    }

    // Extract retina parameter
    $retina = isset($args['retina']) ? (bool) $args['retina'] : false;
    unset($args['retina']); // Remove from args so it doesn't get passed to render function

    // If no sizes provided, generate smart defaults based on original image
    if (empty($sizes)) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        $orig_width = $metadata['width'] ?? 1920;
        $orig_height = $metadata['height'] ?? 1080;
        $aspect_ratio = $orig_height / $orig_width;

        // Generate responsive sizes maintaining aspect ratio
        $sizes = [
            1920 => [1920, (int)($aspect_ratio * 1920), false],
            1024 => [1024, (int)($aspect_ratio * 1024), false],
            768  => [768, (int)($aspect_ratio * 768), false],
            0    => [414, (int)($aspect_ratio * 414), false], // mobile fallback
        ];
    }

    // Convert simple sizes array to breakpoints format
    $breakpoints = [];
    $fallback = null;

    foreach ($sizes as $bp => $size) {
        if ($bp === 'fallback' || $bp === 0) {
            $fallback = $size;
        } else {
            $breakpoints[$bp] = $size;
        }
    }

    // If retina is enabled, we'll handle it in soj_prepare_picture_sources
    // by generating 2x versions for each source
    $args['retina'] = $retina;

    // Merge with defaults
    $defaults = [
        'breakpoints'   => $breakpoints,
        'fallback_size' => $fallback,
        'loading'       => 'lazy',
        'decoding'      => 'async',
    ];

    return soj_render_responsive_picture($attachment_id, array_merge($defaults, $args));
}

/**
 * Debug function to check which image variants exist for an attachment.
 * Useful for troubleshooting derivative generation.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $width Target width.
 * @param int $height Target height.
 * @param bool $crop Whether to crop.
 * @return array Debug information about available variants.
 */
function soj_debug_image_variants($attachment_id, $width, $height, $crop = true)
{
    $attachment_id = (int) $attachment_id;
    if (!$attachment_id) {
        return ['error' => 'Invalid attachment ID'];
    }

    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
        return ['error' => 'Upload directory error'];
    }

    $cache_root_dir = trailingslashit($upload_dir['basedir']) . SOJ_Dynamic_Images::CACHE_FOLDER;
    $cache_root_url = trailingslashit($upload_dir['baseurl']) . SOJ_Dynamic_Images::CACHE_FOLDER;

    $source_file = get_attached_file($attachment_id);
    if (!$source_file || !file_exists($source_file)) {
        return ['error' => 'Source file not found'];
    }

    $ext = strtolower((string) pathinfo($source_file, PATHINFO_EXTENSION));
    $original_filename = wp_basename($source_file, '.' . $ext);
    $sanitized_filename = sanitize_file_name($original_filename);
    $sanitized_filename = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $sanitized_filename);
    if (empty($sanitized_filename)) {
        $sanitized_filename = 'image-' . $attachment_id;
    }

    $crop_flag = $crop ? 'c' : 'nc';
    $size_folder = sprintf('%dx%d', (int) $width, (int) $height);
    $attachment_dir = trailingslashit($cache_root_dir) . $attachment_id . '/' . $size_folder;
    $attachment_url = trailingslashit($cache_root_url) . $attachment_id . '/' . $size_folder;

    $target_basename_jpg = sprintf('%s-%s.jpg', $sanitized_filename, $crop_flag);
    $target_file_jpg = trailingslashit($attachment_dir) . $target_basename_jpg;
    $target_url_jpg = trailingslashit($attachment_url) . $target_basename_jpg;

    $target_basename_webp = sprintf('%s-%s.webp', $sanitized_filename, $crop_flag);
    $target_file_webp = trailingslashit($attachment_dir) . $target_basename_webp;
    $target_url_webp = trailingslashit($attachment_url) . $target_basename_webp;

    $result = SOJ_Dynamic_Images::get_image_src($attachment_id, $width, $height, $crop);

    $debug = [
        'attachment_id' => $attachment_id,
        'requested_size' => sprintf('%dx%d', $width, $height),
        'crop' => $crop,
        'source_file' => $source_file,
        'cache_directory' => $attachment_dir,
        'jpeg' => [
            'exists' => file_exists($target_file_jpg),
            'path' => $target_file_jpg,
            'url' => $target_url_jpg,
            'size' => file_exists($target_file_jpg) ? filesize($target_file_jpg) : 0,
        ],
        'webp' => [
            'exists' => file_exists($target_file_webp),
            'path' => $target_file_webp,
            'url' => $target_url_webp,
            'size' => file_exists($target_file_webp) ? filesize($target_file_webp) : 0,
        ],
        'get_image_src_result' => $result,
    ];

    if ($result && isset($result['width']) && isset($result['height'])) {
        $debug['actual_dimensions'] = sprintf('%dx%d', $result['width'], $result['height']);
    }

    return $debug;
}
