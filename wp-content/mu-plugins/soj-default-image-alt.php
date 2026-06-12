<?php
/**
 * Plugin Name: SOJ - Default Image Alt on Upload
 * Description: Sets a sensible default alt text for images when admins upload them, based on a de-slugified title.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

function soj_deslugify_for_alt($text) {
    $text = wp_strip_all_tags($text);
    // Remove common file extensions if the title came from filename
    $text = preg_replace('/\.(jpe?g|png|gif|webp|svg|avif)$/i', '', $text);
    
    // First: Remove number patterns before converting hyphens to spaces
    // Remove long numbers (4+ digits) as whole words
    $text = preg_replace('/\b\d{4,}\b/', '', $text);
    // Remove number-hyphen-number patterns as whole words (e.g., "2-1", "10-5", "1-1")
    $text = preg_replace('/\b\d+-\d+\b/', '', $text);
    
    // Then: Turn hyphens/underscores into spaces and normalise whitespace
    $text = preg_replace('/[-_]+/', ' ', $text);
    // Add spaces between words and numbers (e.g., "Vector1" -> "Vector 1")
    $text = preg_replace('/([a-zA-Z])(\d)/', '$1 $2', $text);
    $text = preg_replace('/(\d)([a-zA-Z])/', '$1 $2', $text);
    $text = trim(preg_replace('/\s+/', ' ', $text));
    
    // Title case, but leave all-caps strings alone (e.g., IMG 1234)
    if ($text && !preg_match('/^[A-Z0-9\s]+$/', $text)) {
        if (function_exists('mb_convert_case')) {
            $text = mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
        } else {
            $text = ucwords(strtolower($text));
        }
    }
    return $text;
}

/**
 * Set default alt when an attachment is created.
 */
add_action('add_attachment', function ($attachment_id) {
    // Only in admin and only for users with admin capability
    if (!is_admin() || !current_user_can('manage_options')) return;

    $post = get_post($attachment_id);
    if (!$post || $post->post_type !== 'attachment') return;

    $mime = get_post_mime_type($attachment_id);
    if (strpos($mime, 'image/') !== 0) return; // images only

    // Don't overwrite an existing alt
    $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    if (!empty($existing_alt)) return;

    // Prefer the attachment title; fall back to filename if needed
    $title = $post->post_title;
    if ($title === '' || $title === null) {
        $filepath = get_attached_file($attachment_id);
        if ($filepath) {
            $title = pathinfo($filepath, PATHINFO_FILENAME);
        }
    }

    $alt = apply_filters('soj_default_image_alt', soj_deslugify_for_alt($title), $attachment_id);
    if ($alt) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
    }
});

/**
 * Add bulk action to media library for updating empty alt tags
 */
add_filter('bulk_actions-upload', 'soj_add_bulk_alt_update_action');
function soj_add_bulk_alt_update_action($bulk_actions) {
    $bulk_actions['soj_update_empty_alt'] = __('Update Empty Alt Tags', 'soj-default-image-alt');
    return $bulk_actions;
}

/**
 * Handle the bulk action for updating empty alt tags
 */
add_filter('handle_bulk_actions-upload', 'soj_handle_bulk_alt_update', 10, 3);
function soj_handle_bulk_alt_update($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'soj_update_empty_alt') {
        return $redirect_to;
    }

    // Only allow admins
    if (!current_user_can('manage_options')) {
        return $redirect_to;
    }

    $updated_count = 0;
    $skipped_count = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        
        // Only process attachments
        if (!$post || $post->post_type !== 'attachment') {
            continue;
        }

        // Only process images
        $mime = get_post_mime_type($post_id);
        if (strpos($mime, 'image/') !== 0) {
            continue;
        }

        // Only update if alt tag is empty
        $existing_alt = get_post_meta($post_id, '_wp_attachment_image_alt', true);
        if (!empty($existing_alt)) {
            $skipped_count++;
            continue;
        }

        // Generate alt text
        $title = $post->post_title;
        if ($title === '' || $title === null) {
            $filepath = get_attached_file($post_id);
            if ($filepath) {
                $title = pathinfo($filepath, PATHINFO_FILENAME);
            }
        }

        $alt = apply_filters('soj_default_image_alt', soj_deslugify_for_alt($title), $post_id);
        if ($alt) {
            update_post_meta($post_id, '_wp_attachment_image_alt', $alt);
            $updated_count++;
        }
    }

    // Add result message to redirect URL
    $redirect_to = add_query_arg('soj_alt_updated', $updated_count, $redirect_to);
    $redirect_to = add_query_arg('soj_alt_skipped', $skipped_count, $redirect_to);
    
    return $redirect_to;
}

/**
 * Display admin notice for bulk action results
 */
add_action('admin_notices', 'soj_bulk_alt_update_notice');
function soj_bulk_alt_update_notice() {
    if (!empty($_REQUEST['soj_alt_updated']) || !empty($_REQUEST['soj_alt_skipped'])) {
        $updated = intval($_REQUEST['soj_alt_updated']);
        $skipped = intval($_REQUEST['soj_alt_skipped']);
        
        $message = '';
        if ($updated > 0) {
            $message .= sprintf(_n('%d image alt tag updated.', '%d image alt tags updated.', $updated, 'soj-default-image-alt'), $updated);
        }
        if ($skipped > 0) {
            $message .= ' ' . sprintf(_n('%d image skipped (already has alt text).', '%d images skipped (already have alt text).', $skipped, 'soj-default-image-alt'), $skipped);
        }
        
        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}

/**
 * Add alt tag column to media library
 */
add_filter('manage_media_columns', 'soj_add_alt_column');
function soj_add_alt_column($columns) {
    // Insert alt column after the title column
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['alt_text'] = __('Alt Text', 'soj-default-image-alt');
        }
    }
    return $new_columns;
}

/**
 * Display alt tag content in the media library column
 */
add_action('manage_media_custom_column', 'soj_display_alt_column', 10, 2);
function soj_display_alt_column($column_name, $post_id) {
    if ($column_name === 'alt_text') {
        // Only show alt text information for images
        $mime = get_post_mime_type($post_id);
        if (strpos($mime, 'image/') !== 0) {
            // Not an image, leave field empty
            return;
        }
        
        $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
        
        if (empty($alt_text)) {
            echo '<span style="color: #d63638; font-style: italic;">' . __('No alt text', 'soj-default-image-alt') . '</span>';
        } else {
            // Truncate long alt text for display
            $display_text = strlen($alt_text) > 50 ? substr($alt_text, 0, 50) . '...' : $alt_text;
            echo '<span title="' . esc_attr($alt_text) . '">' . esc_html($display_text) . '</span>';
        }
    }
}

/**
 * Make alt text column sortable
 */
add_filter('manage_upload_sortable_columns', 'soj_make_alt_column_sortable');
function soj_make_alt_column_sortable($columns) {
    $columns['alt_text'] = 'alt_text';
    return $columns;
}

/**
 * Handle sorting by alt text
 */
add_action('pre_get_posts', 'soj_handle_alt_column_sorting');
function soj_handle_alt_column_sorting($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->get('post_type') !== 'attachment') {
        return;
    }

    $orderby = $query->get('orderby');
    if ($orderby === 'alt_text') {
        $query->set('meta_key', '_wp_attachment_image_alt');
        $query->set('orderby', 'meta_value');
    }
}

/**
 * Count images with missing alt tags
 */
function soj_count_images_missing_alt() {
    global $wpdb;
    
    $count = $wpdb->get_var("
        SELECT COUNT(*)
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
        WHERE p.post_type = 'attachment'
        AND p.post_mime_type LIKE 'image/%'
        AND (pm.meta_value IS NULL OR pm.meta_value = '')
    ");
    
    return intval($count);
}

/**
 * Display admin warning for images with missing alt tags
 */
add_action('admin_notices', 'soj_missing_alt_warning');
function soj_missing_alt_warning() {
    // Only show on media library page and to users who can manage options
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'upload' || !current_user_can('manage_options')) {
        return;
    }
    
    $missing_count = soj_count_images_missing_alt();
    
    if ($missing_count > 0) {
        $message = sprintf(
            _n(
                'Warning: %d image in your media library is missing alt text. Use the "Update Empty Alt Tags" bulk action to automatically generate alt text for these images.',
                'Warning: %d images in your media library are missing alt text. Use the "Update Empty Alt Tags" bulk action to automatically generate alt text for these images.',
                $missing_count,
                'soj-default-image-alt'
            ),
            $missing_count
        );
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html($message) . '</strong></p>';
        echo '</div>';
    }
}