<?php

/**
 * Block Manager - Auto-discovery ACF Blocks with Smart Loading
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Disable WordPress core block-library CSS on the frontend.
 *
 * The theme ships its own compiled styles, so WordPress's default block CSS is
 * dequeued. theme.json is output via global-styles (presets, group spacing/blockGap,
 * per-block editor values). See soj_enqueue_block_layout_styles().
 */
function soj_disable_block_editor_styles()
{
    // Remove WordPress core block library CSS from the frontend.
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wp-format-library');
    // Per-block image styles force table-caption layout and override theme overlays.
    wp_dequeue_style('wp-block-image');
}
add_action('wp_enqueue_scripts', 'soj_disable_block_editor_styles', 100);

/**
 * Ensure theme.json global styles are queued (presets, group defaults).
 * Per-block layout CSS (block gap margins) is output via wp_enqueue_stored_styles in wp_footer.
 */
function soj_enqueue_block_layout_styles()
{
    if (is_admin()) {
        return;
    }

    wp_enqueue_global_styles();
}
add_action('wp_enqueue_scripts', 'soj_enqueue_block_layout_styles', 9);

/**
 * Resolve attachment caption HTML from an ACF image field or attachment ID.
 *
 * @param array|int|null $image ACF image array or attachment ID.
 * @return string Sanitised caption HTML, or empty string when none.
 */
function soj_get_attachment_caption_html($image)
{
    $image_id     = 0;
    $caption_html = '';

    if (is_array($image)) {
        if (!empty($image['ID'])) {
            $image_id = (int) $image['ID'];
        }

        if (!empty($image['caption']) && is_string($image['caption'])) {
            if (trim(wp_strip_all_tags($image['caption'])) !== '') {
                $caption_html = $image['caption'];
            }
        }
    } elseif (is_numeric($image)) {
        $image_id = (int) $image;
    }

    if ($caption_html === '' && $image_id > 0) {
        $attachment_caption = get_post_field('post_excerpt', $image_id);
        if (is_string($attachment_caption) && trim(wp_strip_all_tags($attachment_caption)) !== '') {
            $caption_html = $attachment_caption;
        }
    }

    if ($caption_html === '') {
        return '';
    }

    return wp_kses_post($caption_html);
}

/**
 * Render a native-style figcaption for an attachment caption.
 *
 * @param array|int|null $image ACF image array or attachment ID.
 * @return string
 */
function soj_render_attachment_figcaption($image)
{
    $caption_html = soj_get_attachment_caption_html($image);
    if ($caption_html === '') {
        return '';
    }

    return sprintf(
        '<figcaption class="wp-element-caption">%s</figcaption>',
        $caption_html
    );
}

/**
 * Output a caption on core/image blocks when markup has none.
 *
 * Gutenberg stores captions on the block itself; media-library captions on the
 * attachment are not synced automatically. WordPress 6.9 also strips empty
 * figcaption elements on render, so a block with no block caption outputs no
 * figcaption at all. This falls back to the attachment caption (post_excerpt).
 *
 * @param string   $block_content Rendered block HTML.
 * @param array    $block         Parsed block data.
 * @param WP_Block $instance      Block instance.
 * @return string
 */
function soj_render_core_image_attachment_caption($block_content, $block, $instance)
{
    if (is_admin() || $block_content === '' || stripos($block_content, '<img') === false) {
        return $block_content;
    }

    if (preg_match('/<figcaption\b[^>]*>[\s\S]*?\S[\s\S]*?<\/figcaption>/i', $block_content)) {
        return $block_content;
    }

    $caption_html = '';
    if (!empty($block['attrs']['caption']) && is_string($block['attrs']['caption'])) {
        if (trim(wp_strip_all_tags($block['attrs']['caption'])) !== '') {
            $caption_html = $block['attrs']['caption'];
        }
    }

    if ($caption_html === '') {
        $attachment_id = 0;
        if (!empty($block['attrs']['id'])) {
            $attachment_id = (int) $block['attrs']['id'];
        } elseif (preg_match('/\bwp-image-(\d+)\b/', $block_content, $matches)) {
            $attachment_id = (int) $matches[1];
        }

        if ($attachment_id <= 0) {
            return $block_content;
        }

        $caption_html = soj_get_attachment_caption_html($attachment_id);
        if ($caption_html === '') {
            return $block_content;
        }
    }

    $figcaption = sprintf(
        '<figcaption class="wp-element-caption">%s</figcaption>',
        wp_kses_post($caption_html)
    );

    if (preg_match('/<\/figure>\s*$/i', $block_content)) {
        return preg_replace('/<\/figure>\s*$/i', $figcaption . '</figure>', $block_content, 1);
    }

    return $block_content . $figcaption;
}
add_filter('render_block_core/image', 'soj_render_core_image_attachment_caption', 20, 3);

/**
 * Disable WordPress style inlining for block styles.
 * By default WordPress inlines small stylesheets (< 40KB) into <style> tags for performance.
 * This forces block styles to load as external <link> stylesheets instead.
 */
add_filter('styles_inline_size_limit', '__return_zero');

/**
 * Advanced block management system
 * Automatically manages ACF blocks and allows fine-grained control
 */
function soj_hide_all_default_blocks()
{
    // Remove block patterns completely
    remove_theme_support('core-block-patterns');

    // Remove block directory
    remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');

    // Restrict to ACF blocks, selected core blocks, and approved plugin blocks.
    add_filter('allowed_block_types_all', function ($allowed_blocks, $block_editor_context) {
        $allowed_block_names = [];

        if (function_exists('acf_get_block_types')) {
            $acf_blocks = acf_get_block_types();
            foreach ($acf_blocks as $block_name => $block) {
                $allowed_block_names[] = $block_name;
            }
        }

        $core_blocks = soj_get_allowed_core_blocks($block_editor_context);
        $plugin_blocks = soj_get_allowed_plugin_blocks();
        $allowed_block_names = array_merge($allowed_block_names, $core_blocks, $plugin_blocks);

        if (empty($allowed_block_names)) {
            return true;
        }

        return $allowed_block_names;
    }, 10, 2);
}

/**
 * Get allowed core blocks based on context
 * This allows you to show different blocks in different contexts
 */
function soj_get_allowed_core_blocks($context)
{
    // Default core blocks for all contexts
    $default_blocks = [
        'core/paragraph',
        'core/heading',
        'core/list',
        'core/list-item',
        'core/quote',
        'core/pullquote',
        'core/image',
        'core/spacer',
        'core/group',
        'core/buttons',
        'core/button',
    ];

    // Add context-specific blocks (post_type from post when editing post/page)
    $post_type = isset($context->post) ? $context->post->post_type : ($context->post_type ?? null);
    if ($post_type) {
        switch ($post_type) {
            case 'page':
                $default_blocks = array_merge($default_blocks, [
                    'core/spacer',
                ]);
                break;
            case 'post':
                $default_blocks = array_merge($default_blocks, [
                    'core/quote',
                    'core/pullquote',
                ]);
                break;
            case 'insight':
                $default_blocks = array_merge($default_blocks, [
                    'core/quote',
                    'core/pullquote',
                ]);
                break;
        }
    }

    return $default_blocks;
}

/**
 * Get allowed third-party plugin blocks when the plugin is active.
 *
 * Yoast FAQ (`yoast/faq-block`) — the Gutenberg block inserter block, not custom ACF/schema.
 *
 * @return array<int, string>
 */
function soj_get_allowed_plugin_blocks()
{
    $blocks = [];
    $registry = WP_Block_Type_Registry::get_instance();

    if ($registry->is_registered('yoast/faq-block')) {
        $blocks[] = 'yoast/faq-block';
    }

    return $blocks;
}

add_action('init', 'soj_hide_all_default_blocks', 40);

/**
 * Strip design-tool supports (typography, color, spacing, border, etc.) from core
 * blocks. theme.json settings hide most controls, but some panels — e.g. the
 * Typography "fit text" control — are driven by block supports and persist unless
 * the support itself is removed here.
 *
 * @param array  $args     Block type registration args.
 * @param string $block_name Registered block name.
 * @return array
 */
function soj_strip_core_block_design_supports($args, $block_name)
{
    if (strpos($block_name, 'core/') !== 0) {
        return $args;
    }

    if (empty($args['supports']) || ! is_array($args['supports'])) {
        $args['supports'] = [];
    }

    // Group: full-width sections with constrained inner content (backgrounds, padding).
    if ($block_name === 'core/group') {
        $args['supports']['align']      = ['wide', 'full'];
        $args['supports']['layout']     = [
            'allowEditing'   => true,
            'allowSwitching' => true,
            'default'        => ['type' => 'constrained'],
        ];
        $args['supports']['color']      = [
            'background' => true,
            'text'       => false,
            'link'       => false,
        ];
        $args['supports']['background'] = [
            'backgroundImage' => true,
            'backgroundSize'  => true,
        ];
        $args['supports']['spacing']    = [
            'padding'  => true,
            'blockGap' => true,
            'margin'   => false,
        ];
        $args['supports']['anchor']          = false;
        $args['supports']['customClassName'] = false;
        $args['supports']['typography']      = [
            'textAlign' => true,
        ];

        return $args;
    }

    $alignable_blocks = ['core/paragraph', 'core/heading', 'core/image'];
    if (in_array($block_name, $alignable_blocks, true)) {
        $args['supports']['align'] = ['wide', 'full'];
    }

    $design_supports = [
        'typography',
        'color',
        'spacing',
        'dimensions',
        'border',
        'shadow',
        'background',
        'filter',
        'position',
    ];

    foreach ($design_supports as $support) {
        unset($args['supports'][$support]);
    }

    // Remove the "Advanced" panel attributes (HTML anchor + additional CSS class).
    $args['supports']['anchor']          = false;
    $args['supports']['customClassName']  = false;

    $text_align_blocks = ['core/paragraph', 'core/heading'];
    if (in_array($block_name, $text_align_blocks, true)) {
        $args['supports']['typography'] = [
            'textAlign' => true,
        ];
    }

    return $args;
}
add_filter('register_block_type_args', 'soj_strip_core_block_design_supports', 20, 2);

/**
 * Block Manager Class
 *
 * Handles automatic discovery and loading of ACF blocks.
 * Block assets are bundled into main.min.css and main.min.js via webpack.
 * Implements smart conditional loading based on blocks used on page.
 */
class BlockManager
{
    /**
     * Array of discovered blocks
     *
     * @var array
     */
    private $blocks = array();

    /**
     * Array of blocks used on current page
     *
     * @var array
     */
    private $blocks_on_page = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add block category
        add_action('block_categories_all', array( $this, 'add_block_category' ), 10, 2);

        // Add block validation notices for admins
        add_action('admin_notices', array( $this, 'display_block_validation_notices' ));

        // Add body classes for blocks
        add_filter('body_class', array( $this, 'add_block_body_classes' ));

        // Add JavaScript data for conditional initialization
        add_action('wp_footer', array( $this, 'add_block_js_data' ));
    }

    /**
     * Discover blocks from the blocks directory
     *
     * @return array
     */
    public function discover_blocks()
    {
        // Return cached result — filesystem glob only runs once per request.
        if (! empty($this->blocks)) {
            return $this->blocks;
        }

        $blocks_dir = SOJ_THEME_DIR . '/blocks';

        if (! is_dir($blocks_dir)) {
            return array();
        }

        $block_folders = glob($blocks_dir . '/*', GLOB_ONLYDIR);

        foreach ($block_folders as $block_folder) {
            $block_name  = basename($block_folder);
            $config_file = $block_folder . '/block.json';

            if (file_exists($config_file)) {
                $config = json_decode(file_get_contents($config_file), true);
                if ($config) {
                    $this->blocks[ $block_name ] = $config;
                }
            } else {
                // Fallback for blocks without block.json
                $this->blocks[ $block_name ] = $this->get_default_block_config($block_name);
            }
        }

        return $this->blocks;
    }

    /**
     * Get default block configuration
     *
     * @param string $block_name
     * @return array
     */
    private function get_default_block_config($block_name)
    {
        return array(
            'title'        => ucwords(str_replace(array( '-', '_' ), ' ', $block_name)),
            'description'  => sprintf('A custom %s block', $block_name),
            'icon'         => 'block-default',
            'keywords'     => array( $block_name ),
            'supports'     => array(
                'align' => false,
                'mode'  => true,
            ),
            'css'          => file_exists(SOJ_THEME_DIR . "/blocks/{$block_name}/css/style.scss"),
            'js'           => file_exists(SOJ_THEME_DIR . "/blocks/{$block_name}/js/script.js"),
            'dependencies' => array(),
        );
    }

    /**
     * Get blocks used on current page
     *
     * @return array
     */
    public function get_blocks_on_page()
    {
        if (! empty($this->blocks_on_page)) {
            return $this->blocks_on_page;
        }

        global $post;

        if (! $post) {
            return array();
        }

        $content        = $post->post_content;
        $blocks         = $this->discover_blocks();
        $blocks_on_page = array();

        foreach ($blocks as $block_name => $config) {
            // Check for ACF blocks
            if (strpos($content, '<!-- wp:acf/' . $block_name) !== false) {
                $blocks_on_page[] = $block_name;
            }

            // Check for core blocks that might need our styles
            if (strpos($content, '<!-- wp:' . $block_name) !== false) {
                $blocks_on_page[] = $block_name;
            }
        }

        $this->blocks_on_page = $blocks_on_page;
        return $blocks_on_page;
    }

    /**
     * Add body classes for blocks
     *
     * @param array $classes
     * @return array
     */
    public function add_block_body_classes($classes)
    {
        $blocks_on_page = $this->get_blocks_on_page();

        foreach ($blocks_on_page as $block) {
            $classes[] = 'has-block-' . sanitize_html_class($block);
        }

        return $classes;
    }

    /**
     * Add JavaScript data for conditional initialization
     */
    public function add_block_js_data()
    {
        $blocks_on_page = $this->get_blocks_on_page();

        if (! empty($blocks_on_page)) {
            echo '<script>window.SOJ_BLOCKS_ON_PAGE = ' . json_encode($blocks_on_page) . ';</script>';
        }
    }

    /**
     * Add custom block category
     *
     * @param array  $categories
     * @param object $post
     * @return array
     */
    public function add_block_category($categories, $post)
    {
        return array_merge(
            $categories,
            array(
                array(
                    'slug'  => 'soj-blocks',
                    'title' => __('SOJ Blocks', 'soj-core'),
                    'icon'  => 'admin-customizer',
                ),
            )
        );
    }

    /**
     * Validate block structure
     *
     * @param string $block_name
     * @return bool
     */
    public function validate_block($block_name)
    {
        $required_files = array( 'template.php' );
        $block_dir      = SOJ_THEME_DIR . "/blocks/{$block_name}";

        foreach ($required_files as $file) {
            $file_path = $block_dir . '/' . $file;
            if (! file_exists($file_path)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Display block validation notices
     */
    public function display_block_validation_notices()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $blocks         = $this->discover_blocks();
        $invalid_blocks = array();

        foreach ($blocks as $block_name => $config) {
            if (! $this->validate_block($block_name)) {
                $invalid_blocks[] = $block_name;
            }
        }

        if (! empty($invalid_blocks)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>SOJ Blocks Warning:</strong> The following blocks are missing required files: ' . implode(', ', $invalid_blocks) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Get all registered blocks
     *
     * @return array
     */
    public function get_blocks()
    {
        return $this->blocks;
    }

    /**
     * Check if a block exists
     *
     * @param string $block_name
     * @return bool
     */
    public function block_exists($block_name)
    {
        return isset($this->blocks[ $block_name ]);
    }

    /**
     * Get block configuration
     *
     * @param string $block_name
     * @return array|null
     */
    public function get_block_config($block_name)
    {
        return $this->blocks[ $block_name ] ?? null;
    }

    /**
     * Get block statistics
     *
     * @return array
     */
    public function get_block_statistics()
    {
        $blocks = $this->discover_blocks();
        $stats  = array(
            'total'           => count($blocks),
            'with_css'        => 0,
            'with_js'         => 0,
            'valid'           => 0,
            'invalid'         => 0,
            'on_current_page' => count($this->get_blocks_on_page()),
        );

        foreach ($blocks as $block_name => $config) {
            if (isset($config['css']) && $config['css']) {
                ++$stats['with_css'];
            }
            if (isset($config['js']) && $config['js']) {
                ++$stats['with_js'];
            }
            if ($this->validate_block($block_name)) {
                ++$stats['valid'];
            } else {
                ++$stats['invalid'];
            }
        }

        return $stats;
    }
}

// Initialize block manager
new BlockManager();
