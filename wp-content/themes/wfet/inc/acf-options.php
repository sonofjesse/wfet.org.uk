<?php

/**
 * ACF Options Page
 * 
 * Adds a site-wide options page to the WordPress admin for managing
 * global theme settings and site-wide content.
 * 
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register ACF Options Page
 * 
 * Creates a site-wide options page in the WordPress admin
 * for managing global theme settings and content.
 */
function soj_acf_options_page()
{
    // Only register if ACF is active
    if (!function_exists('acf_add_options_page')) {
        return;
    }

    // Register the main options page
    acf_add_options_page(array(
        'page_title'  => 'Site Settings',
        'menu_title'  => 'Site Settings',
        'menu_slug'   => 'site-settings',
        'capability'  => 'edit_pages',
        'position'    => 30,
        'icon_url'    => 'dashicons-admin-generic',
        'redirect'    => false,
        'post_id'     => 'options',
        'autoload'    => false,
    ));

    /* Subpage example
    acf_add_options_sub_page(array(
        'page_title'  => 'Contact Information',
        'menu_title'  => 'Contact',
        'parent_slug' => 'site-settings',
        'menu_slug'   => 'site-settings-contact',
        'capability'  => 'edit_pages',
        'post_id'     => 'options',
    ));*/
}
add_action('acf/init', 'soj_acf_options_page');

/**
 * Note: ACF Field Groups are now managed through the ACF admin interface
 * 
 * To create field groups for the options pages:
 * 1. Go to Custom Fields → Add New
 * 2. Set Location Rules to "Options Page" → "Site Settings" (or specific sub-pages)
 * 3. Add your fields as needed
 * 4. The helper functions below will work with any field names you create
 */

/**
 * Helper function to get ACF options field value
 * 
 * @param string $field_name The ACF field name
 * @param mixed $default Default value if field is empty
 * @return mixed The field value or default
 */
function soj_get_option($field_name, $default = '')
{
    if (!function_exists('get_field')) {
        return $default;
    }

    $value = get_field($field_name, 'option');
    return !empty($value) ? $value : $default;
}

/**
 * Helper function to get ACF options field value with formatting
 * 
 * @param string $field_name The ACF field name
 * @param mixed $default Default value if field is empty
 * @return mixed The formatted field value or default
 */
function soj_get_option_formatted($field_name, $default = '')
{
    if (!function_exists('get_field')) {
        return $default;
    }

    $value = get_field($field_name, 'option');
    return !empty($value) ? $value : $default;
}

/**
 * Helper function to check if ACF options field exists and has value
 * 
 * @param string $field_name The ACF field name
 * @return bool True if field exists and has value
 */
function soj_has_option($field_name)
{
    if (!function_exists('get_field')) {
        return false;
    }

    $value = get_field($field_name, 'option');
    return !empty($value);
}

/**
 * Populate dropdown menu item select with Primary menu parent items.
 */
add_filter('acf/load_field/name=select_menu_item', function ($field) {
    // Reset choices (important when ACF caches field config).
    $field['choices'] = [];

    if (! function_exists('wp_get_nav_menu_items')) {
        return $field;
    }

    $locations = get_nav_menu_locations();
    $menu_id   = $locations['primary'] ?? null;
    if (! $menu_id) {
        return $field;
    }

    $items = wp_get_nav_menu_items($menu_id);
    if (! is_array($items)) {
        return $field;
    }

    foreach ($items as $item) {
        // Top-level items only.
        if (! empty($item->menu_item_parent)) {
            continue;
        }

        $field['choices'][(string) $item->ID] = $item->title;
    }

    return $field;
});

/**
 * Inject ACF-configured dropdown links into the Primary menu.
 *
 * Reads the "Drop Down Menus" repeater on the Site Settings options page and,
 * for rows configured as "single", adds those links as submenu items under the
 * selected parent menu item.
 */
add_filter('wp_nav_menu_objects', function ($sorted_menu_items, $args) {
    if (!is_array($sorted_menu_items)) {
        return $sorted_menu_items;
    }

    if (empty($args->theme_location) || $args->theme_location !== 'primary') {
        return $sorted_menu_items;
    }

    // Normalise URLs to a comparable path (ignore domain/query, ignore trailing slash).
    $normalise_path = static function (string $url): string {
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? (string) $parts['path'] : '/';
        $path = urldecode($path);
        $path = '/' . ltrim($path, '/');
        $path = untrailingslashit($path);

        return $path === '' ? '/' : $path;
    };

    $current_paths = [];
    $queried_id = (int) get_queried_object_id();
    if ($queried_id > 0) {
        $permalink = (string) get_permalink($queried_id);
        if ($permalink !== '') {
            $current_paths[] = $normalise_path($permalink);
        }
    }
    if (isset($GLOBALS['wp']) && is_object($GLOBALS['wp']) && isset($GLOBALS['wp']->request)) {
        $request_url = (string) home_url(add_query_arg([], $GLOBALS['wp']->request));
        $current_paths[] = $normalise_path($request_url);
    }
    $current_paths = array_values(array_unique(array_filter($current_paths)));

    if (! function_exists('get_field')) {
        return $sorted_menu_items;
    }

    $rows = get_field('drop_down_menus', 'option');
    if (!is_array($rows) || empty($rows)) {
        return $sorted_menu_items;
    }

    // Map: parent_menu_item_id => [links...]
    $dropdowns = [];
    // Map: parent_menu_item_id => mega config
    $megas = [];
    $normal_parent_ids = [];
    $mega_parent_ids = [];
    foreach ($rows as $row) {
        $parent_id = isset($row['select_menu_item']) ? (int) $row['select_menu_item'] : 0;
        if ($parent_id <= 0) {
            continue;
        }

        $type = isset($row['select_drop_down_type']) ? (string) $row['select_drop_down_type'] : '';
        if ($type === 'single') {
            $normal_parent_ids[$parent_id] = true;
            $single_links = $row['single_links'] ?? [];
            if (!is_array($single_links) || empty($single_links)) {
                continue;
            }

            foreach ($single_links as $link_row) {
                $link = $link_row['link'] ?? null;
                if (!is_array($link) || empty($link['url']) || empty($link['title'])) {
                    continue;
                }

                $dropdowns[$parent_id][] = [
                    'title'  => (string) $link['title'],
                    'url'    => (string) $link['url'],
                    'target' => isset($link['target']) ? (string) $link['target'] : '',
                ];
            }
        } elseif ($type === 'mega') {
            $mega_parent_ids[$parent_id] = true;
            $mega = $row['mega_menu'] ?? null;
            if (!is_array($mega) || empty($mega)) {
                continue;
            }

            $left = $mega['leftside_link'] ?? null;
            $right = $mega['rightside_link'] ?? null;
            $services = $mega['select_services'] ?? [];
            if (!is_array($services)) {
                $services = [];
            }

            $megas[$parent_id] = [
                'left' => is_array($left) ? $left : null,
                'right' => is_array($right) ? $right : null,
                'services' => $services,
            ];
        }
    }

    if (empty($dropdowns) && empty($megas)) {
        return $sorted_menu_items;
    }

    // Find the current highest IDs so we can create unique pseudo-items.
    $max_id         = 0;
    $max_db_id      = 0;
    $max_menu_order = 0;
    foreach ($sorted_menu_items as $item) {
        $max_id = max($max_id, (int) ($item->ID ?? 0));
        $max_db_id = max($max_db_id, (int) ($item->db_id ?? 0));
        $max_menu_order = max($max_menu_order, (int) ($item->menu_order ?? 0));
    }

    $next_id         = max($max_id, $max_db_id) + 1;
    $next_menu_order = $max_menu_order + 1;

    // Build a quick lookup for parent presence to avoid injecting into wrong menus.
    $existing_ids = [];
    foreach ($sorted_menu_items as $item) {
        $existing_ids[(int) ($item->ID ?? 0)] = true;
    }

    // Tag the existing parent menu items with has-normal / has-mega.
    if (!empty($normal_parent_ids) || !empty($mega_parent_ids)) {
        foreach ($sorted_menu_items as $item) {
            $id = (int) ($item->ID ?? 0);
            if ($id <= 0) {
                continue;
            }

            if (!isset($item->classes) || !is_array($item->classes)) {
                $item->classes = [];
            }

            if (isset($normal_parent_ids[$id]) && !in_array('has-normal', $item->classes, true)) {
                $item->classes[] = 'has-normal';
            }

            if (isset($mega_parent_ids[$id]) && !in_array('has-mega', $item->classes, true)) {
                $item->classes[] = 'has-mega';
            }
        }
    }

    foreach ($megas as $parent_id => $mega) {
        if (!isset($existing_ids[$parent_id])) {
            continue;
        }

        $container_item = new stdClass();
        $container_item->ID = $next_id;
        $container_item->db_id = $next_id;
        $container_item->menu_item_parent = (string) $parent_id;
        $container_item->object_id = 0;
        $container_item->object = 'custom';
        $container_item->type = 'soj_mega';
        $container_item->type_label = 'Mega Menu';
        $container_item->title = '';
        $container_item->url = '';
        $container_item->target = '';
        $container_item->attr_title = '';
        $container_item->description = '';
        $container_item->classes = ['menu-item', 'menu-item-acf-mega'];
        $container_item->xfn = '';
        $container_item->status = 'publish';
        $container_item->menu_order = $next_menu_order;
        $container_item->current = false;
        $container_item->current_item_parent = false;
        $container_item->current_item_ancestor = false;
        $container_item->is_parent = false;
        $container_item->has_children = false;
        $container_item->post_parent = 0;
        $container_item->post_title = '';
        $container_item->post_name = 'acf-mega';
        $container_item->post_type = 'nav_menu_item';
        $container_item->post_status = 'publish';
        $container_item->guid = '';

        // Attach mega payload for the walker.
        $container_item->soj_mega = $mega;

        $sorted_menu_items[] = $container_item;
        $next_id++;
        $next_menu_order++;
    }

    foreach ($dropdowns as $parent_id => $links) {
        if (!isset($existing_ids[$parent_id])) {
            continue;
        }

        foreach ($links as $link) {
            $new_item = new stdClass();
            $new_item->ID = $next_id;
            $new_item->db_id = $next_id;
            $new_item->menu_item_parent = (string) $parent_id;
            $new_item->object_id = 0;
            $new_item->object = 'custom';
            $new_item->type = 'custom';
            $new_item->type_label = 'Custom Link';
            $new_item->title = $link['title'];
            $new_item->url = $link['url'];
            $new_item->target = $link['target'];
            $new_item->attr_title = '';
            $new_item->description = '';
            $new_item->classes = ['menu-item', 'menu-item-acf-dropdown'];
            $new_item->xfn = '';
            $new_item->status = 'publish';
            $new_item->menu_order = $next_menu_order;
            // Properties expected by core walkers / nav menu filters.
            $new_item->current = false;
            $new_item->current_item_parent = false;
            $new_item->current_item_ancestor = false;
            $new_item->is_parent = false;
            $new_item->has_children = false;
            $new_item->post_parent = 0;
            $new_item->post_title = $new_item->title;
            $new_item->post_name = sanitize_title($new_item->title);
            $new_item->post_type = 'nav_menu_item';
            $new_item->post_status = 'publish';
            $new_item->guid = $new_item->url;

            // Add an active class for ACF-injected items that match the current page.
            if (!empty($current_paths)) {
                $item_path = $normalise_path((string) $new_item->url);
                if ($item_path !== '' && in_array($item_path, $current_paths, true)) {
                    $new_item->current = true;
                    if (!in_array('active', $new_item->classes, true)) {
                        $new_item->classes[] = 'active';
                    }
                }
            }

            $sorted_menu_items[] = $new_item;
            $next_id++;
            $next_menu_order++;
        }
    }

    // When an ACF dropdown child is current, ancestors need core menu classes (Walker merges $item->classes).
    $items_by_id = [];
    foreach ($sorted_menu_items as $it) {
        $items_by_id[(int) ($it->ID ?? 0)] = $it;
    }

    $mark_menu_ancestors = static function (int $child_id) use (&$items_by_id): void {
        if ($child_id <= 0 || !isset($items_by_id[$child_id])) {
            return;
        }

        $parent_id = (int) ($items_by_id[$child_id]->menu_item_parent ?? 0);
        $depth = 0;
        while ($parent_id > 0 && isset($items_by_id[$parent_id])) {
            $p = $items_by_id[$parent_id];
            if (!isset($p->classes) || !is_array($p->classes)) {
                $p->classes = [];
            }

            if (!in_array('current-menu-ancestor', $p->classes, true)) {
                $p->classes[] = 'current-menu-ancestor';
            }
            $p->current_item_ancestor = true;

            if ($depth === 0) {
                if (!in_array('current-menu-parent', $p->classes, true)) {
                    $p->classes[] = 'current-menu-parent';
                }
                $p->current_item_parent = true;
            }

            $parent_id = (int) ($p->menu_item_parent ?? 0);
            $depth++;
        }
    };

    foreach ($sorted_menu_items as $item) {
        $classes = $item->classes ?? [];
        if (!is_array($classes) || !in_array('menu-item-acf-dropdown', $classes, true)) {
            continue;
        }
        $is_active_child = (!empty($item->current) || in_array('active', $classes, true));
        if (!$is_active_child) {
            continue;
        }
        $mark_menu_ancestors((int) ($item->ID ?? 0));
    }

    // Mega menu: current singular is one of the selected services — mark top-level ancestors.
    $queried_object_id = (int) get_queried_object_id();
    if ($queried_object_id > 0 && !empty($megas)) {
        foreach ($megas as $parent_id => $mega) {
            $services = $mega['services'] ?? [];
            if (!is_array($services) || empty($services)) {
                continue;
            }

            $matches = false;
            foreach ($services as $service) {
                $sid = 0;
                if (is_object($service) && isset($service->ID)) {
                    $sid = (int) $service->ID;
                } elseif (is_numeric($service)) {
                    $sid = (int) $service;
                }
                if ($sid > 0 && $sid === $queried_object_id) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) {
                continue;
            }

            $mega_container_id = 0;
            foreach ($sorted_menu_items as $it) {
                if (($it->type ?? '') === 'soj_mega' && (int) ($it->menu_item_parent ?? 0) === (int) $parent_id) {
                    $mega_container_id = (int) ($it->ID ?? 0);
                    break;
                }
            }

            if ($mega_container_id > 0) {
                $mark_menu_ancestors($mega_container_id);
            } elseif (isset($items_by_id[(int) $parent_id])) {
                $mark_menu_ancestors((int) $parent_id);
            }
        }
    }

    // Category archives are part of the Resources section — mark the hub item as current.
    if (is_category()) {
        $resources_hub_path = $normalise_path(home_url('/resources'));
        foreach ($sorted_menu_items as $item) {
            $is_resources_hub = false;
            $item_url = isset($item->url) ? (string) $item->url : '';
            if ($item_url !== '' && $normalise_path($item_url) === $resources_hub_path) {
                $is_resources_hub = true;
            } elseif (($item->object ?? '') === 'page' && (int) ($item->object_id ?? 0) > 0) {
                $page_slug = get_post_field('post_name', (int) $item->object_id);
                if ($page_slug === 'resources') {
                    $is_resources_hub = true;
                }
            } elseif (isset($item->title) && strcasecmp(trim(wp_strip_all_tags((string) $item->title)), 'Resources') === 0) {
                $is_resources_hub = true;
            }

            if (!$is_resources_hub) {
                continue;
            }

            if (!isset($item->classes) || !is_array($item->classes)) {
                $item->classes = [];
            }
            $item->current = true;
            if (!in_array('current-menu-item', $item->classes, true)) {
                $item->classes[] = 'current-menu-item';
            }
        }
    }

    return $sorted_menu_items;
}, 10, 2);

/**
 * Add an "active" class to nav links for current items.
 * This complements the ACF-injected dropdown items (which don't get core "current-menu-item" classes automatically).
 */
add_filter('nav_menu_link_attributes', function ($atts, $item, $args) {
    if (empty($args->theme_location) || $args->theme_location !== 'primary') {
        return $atts;
    }

    $is_active = false;
    if (isset($item->current) && $item->current) {
        $is_active = true;
    }
    if (isset($item->classes) && is_array($item->classes) && in_array('active', $item->classes, true)) {
        $is_active = true;
    }

    if ($is_active) {
        $existing = isset($atts['class']) ? (string) $atts['class'] : '';
        $classes = preg_split('/\s+/', trim($existing)) ?: [];
        if (!in_array('active', $classes, true)) {
            $classes[] = 'active';
        }
        $atts['class'] = trim(implode(' ', array_filter($classes)));
    }

    return $atts;
}, 10, 3);

/**
 * Primary menu walker that adds a "mega" class and renders the mega container.
 */
class SOJ_Primary_Menu_Walker extends Walker_Nav_Menu
{
    private int $soj_parent_id_for_submenu = 0;
    private int $soj_submenu_item_count = 0;
    private array $soj_mega_parent_ids = [];

    public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output): void
    {
        if ($depth === 0 && $element) {
            if (isset($children_elements[$element->ID])) {
                $this->soj_parent_id_for_submenu = (int) $element->ID;
                $this->soj_submenu_item_count = $this->soj_count_submenu_items(
                    (int) $element->ID,
                    $children_elements[$element->ID]
                );
            } else {
                $this->soj_submenu_item_count = 0;
            }
        }

        parent::display_element($element, $children_elements, $max_depth, $depth, $args, $output);
    }

    /**
     * Count items in the first-level submenu: mega = rendered service tiles; normal = child links.
     */
    private function soj_count_submenu_items(int $parent_id, array $children): int
    {
        if (in_array($parent_id, $this->soj_mega_parent_ids, true)) {
            foreach ($children as $child) {
                if (($child->type ?? '') === 'soj_mega' && isset($child->soj_mega['services']) && is_array($child->soj_mega['services'])) {
                    return $this->soj_count_valid_mega_services($child->soj_mega['services']);
                }
            }

            return 0;
        }

        return count($children);
    }

    /**
     * Same validity rules as mega markup in start_el (published post with title + permalink).
     */
    private function soj_count_valid_mega_services(array $services): int
    {
        $n = 0;
        foreach ($services as $service) {
            $service_id = 0;
            if (is_object($service) && isset($service->ID)) {
                $service_id = (int) $service->ID;
            } elseif (is_numeric($service)) {
                $service_id = (int) $service;
            }
            if ($service_id <= 0) {
                continue;
            }

            $title = get_the_title($service_id);
            $url = get_permalink($service_id);
            if (!$title || !$url) {
                continue;
            }
            $n++;
        }

        return $n;
    }

    /**
     * Normalise a URL to a comparable path (ignore domain/query, ignore trailing slash).
     */
    private function soj_normalise_nav_path(string $url): string
    {
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? (string) $parts['path'] : '/';
        $path = urldecode($path);
        $path = '/' . ltrim($path, '/');
        $path = untrailingslashit($path);

        return $path === '' ? '/' : $path;
    }

    /**
     * True when this mega service matches the current page (same as ACF dropdown "active").
     */
    private function soj_mega_service_is_active(int $service_id, string $url): bool
    {
        if ($service_id > 0 && (int) get_queried_object_id() === $service_id) {
            return true;
        }

        $current_paths = [];
        $queried_id = (int) get_queried_object_id();
        if ($queried_id > 0) {
            $permalink = (string) get_permalink($queried_id);
            if ($permalink !== '') {
                $current_paths[] = $this->soj_normalise_nav_path($permalink);
            }
        }
        if (isset($GLOBALS['wp']) && is_object($GLOBALS['wp']) && isset($GLOBALS['wp']->request)) {
            $request_url = (string) home_url(add_query_arg([], $GLOBALS['wp']->request));
            $current_paths[] = $this->soj_normalise_nav_path($request_url);
        }
        $current_paths = array_values(array_unique(array_filter($current_paths)));

        $item_path = $this->soj_normalise_nav_path($url);

        return $item_path !== '' && in_array($item_path, $current_paths, true);
    }

    public function start_lvl(&$output, $depth = 0, $args = null): void
    {
        $indent = str_repeat("\t", $depth);
        $classes = ['sub-menu'];

        $is_top_level_submenu = ($depth === 0);
        if ($is_top_level_submenu && $this->soj_submenu_item_count > 0) {
            $classes[] = 'count-' . $this->soj_submenu_item_count;
        }

        $class_names = implode(' ', $classes);
        if ($is_top_level_submenu) {
            $wrapper_class = in_array($this->soj_parent_id_for_submenu, $this->soj_mega_parent_ids, true) ? 'mega' : 'normal';
            $output .= "\n{$indent}<div class=\"sub-menu-inner " . esc_attr($wrapper_class) . "\">\n";
        }
        $output .= "{$indent}<ul class=\"" . esc_attr($class_names) . "\">\n";
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void
    {
        $indent = str_repeat("\t", $depth);
        $output .= "{$indent}</ul>\n";
        if ($depth === 0) {
            $output .= "{$indent}</div>\n";
        }
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void
    {
        if (($item->type ?? '') === 'soj_mega' && isset($item->soj_mega) && is_array($item->soj_mega)) {
            $indent = str_repeat("\t", $depth);
            $output .= "{$indent}<li class=\"menu-item menu-item-acf-mega-container\">";

            $left = $item->soj_mega['left'] ?? null;
            $right = $item->soj_mega['right'] ?? null;
            $services = $item->soj_mega['services'] ?? [];

            $output .= '<div class="top">';
            if (is_array($left) && !empty($left['url']) && !empty($left['title'])) {
                $output .= '<a class="mega-link mega-link-left" href="' . esc_url($left['url']) . '"' . (!empty($left['target']) ? ' target="' . esc_attr($left['target']) . '"' : '') . '>' . esc_html($left['title']) . '</a>';
            }
            if (is_array($right) && !empty($right['url']) && !empty($right['title'])) {
                $output .= '<a class="mega-link mega-link-right" href="' . esc_url($right['url']) . '"' . (!empty($right['target']) ? ' target="' . esc_attr($right['target']) . '"' : '') . '>' . esc_html($right['title']) . '</a>';
            }
            $output .= '</div>';

            $output .= '<div class="bottom"><ul class="mega-services">';
            foreach ($services as $service) {
                $service_id = 0;
                if (is_object($service) && isset($service->ID)) {
                    $service_id = (int) $service->ID;
                } elseif (is_numeric($service)) {
                    $service_id = (int) $service;
                }
                if ($service_id <= 0) {
                    continue;
                }

                $title = get_the_title($service_id);
                $url = get_permalink($service_id);
                if (!$title || !$url) {
                    continue;
                }

                $img_url = get_the_post_thumbnail_url($service_id, 'mega-menu-service');

                $is_active = $this->soj_mega_service_is_active($service_id, $url);
                $li_class = $is_active ? 'mega-service active' : 'mega-service';
                $a_class = $is_active ? 'mega-service-link active' : 'mega-service-link';

                $output .= '<li class="' . esc_attr($li_class) . '">';
                $output .= '<a class="' . esc_attr($a_class) . '" href="' . esc_url($url) . '"' . ($is_active ? ' aria-current="page"' : '') . '>';
                if ($img_url) {
                    $output .= '<img class="mega-service-image" src="' . esc_url($img_url) . '" alt="' . esc_attr($title) . '" loading="lazy">';
                }
                $output .= '<span class="mega-service-title">' . esc_html($title) . '</span>';
                $output .= '</a>';
                $output .= '</li>';
            }
            $output .= '</ul></div>';

            $output .= "</li>\n";
            return;
        }

        parent::start_el($output, $item, $depth, $args, $id);
    }

    public function set_mega_parent_ids(array $parent_ids): void
    {
        $this->soj_mega_parent_ids = array_values(array_map('intval', $parent_ids));
    }
}

add_filter('wp_nav_menu_args', function ($args) {
    if (empty($args['theme_location']) || $args['theme_location'] !== 'primary') {
        return $args;
    }

    $mega_parent_ids = [];

    if (function_exists('get_field')) {
        $rows = get_field('drop_down_menus', 'option');
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $parent_id = isset($row['select_menu_item']) ? (int) $row['select_menu_item'] : 0;
                $type = isset($row['select_drop_down_type']) ? (string) $row['select_drop_down_type'] : '';
                if ($parent_id > 0 && $type === 'mega') {
                    $mega_parent_ids[] = $parent_id;
                }
            }
        }
    }

    $walker = new SOJ_Primary_Menu_Walker();
    $walker->set_mega_parent_ids($mega_parent_ids);
    $args['walker'] = $walker;

    return $args;
});
