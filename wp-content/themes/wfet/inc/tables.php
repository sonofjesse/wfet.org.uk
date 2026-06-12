<?php
/**
 * Responsive table wrappers — horizontal scroll on narrow viewports.
 *
 * @package SOJ_Core_Modern
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether a table node is already inside a scroll container.
 *
 * @param DOMNode|null $parent
 * @return bool
 */
function soj_table_has_scroll_parent($parent): bool
{
    if (!$parent instanceof DOMElement) {
        return false;
    }

    if ($parent->nodeName === 'figure' && str_contains($parent->getAttribute('class'), 'wp-block-table')) {
        return true;
    }

    if ($parent->nodeName === 'div' && str_contains($parent->getAttribute('class'), 'table-scroll')) {
        return true;
    }

    return false;
}

/**
 * Wrap bare <table> elements in a horizontally scrollable container.
 *
 * @param string $html
 * @return string
 */
function soj_wrap_tables_in_scroll_container(string $html): string
{
    if ($html === '' || stripos($html, '<table') === false) {
        return $html;
    }

    $previous = libxml_use_internal_errors(true);

    $document = new DOMDocument();
    $loaded   = $document->loadHTML(
        '<?xml encoding="utf-8" ?><div id="soj-table-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    if (!$loaded) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $html;
    }

    $root = $document->getElementById('soj-table-root');
    if (!$root) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $html;
    }

    $tables = $root->getElementsByTagName('table');

    for ($i = $tables->length - 1; $i >= 0; $i--) {
        $table  = $tables->item($i);
        $parent = $table->parentNode;

        if (!$parent || soj_table_has_scroll_parent($parent)) {
            continue;
        }

        $wrapper = $document->createElement('div');
        $wrapper->setAttribute('class', 'table-scroll');
        $wrapper->setAttribute('role', 'region');
        $wrapper->setAttribute('tabindex', '0');
        $wrapper->setAttribute('aria-label', __('Scrollable table', 'soj-core'));

        $parent->insertBefore($wrapper, $table);
        $wrapper->appendChild($table);
    }

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $document->saveHTML($child);
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $output;
}

/**
 * Post/page content.
 *
 * @param string $content
 * @return string
 */
function soj_wrap_tables_in_post_content(string $content): string
{
    return soj_wrap_tables_in_scroll_container($content);
}
add_filter('the_content', 'soj_wrap_tables_in_post_content', 20);

/**
 * ACF WYSIWYG fields (block templates, options, etc.).
 *
 * @param mixed $value
 * @param int|string $post_id
 * @param array $field
 * @return mixed
 */
function soj_wrap_tables_in_acf_wysiwyg($value, $post_id, $field)
{
    if (!is_string($value) || $value === '') {
        return $value;
    }

    if (is_admin() && !wp_doing_ajax()) {
        return $value;
    }

    return soj_wrap_tables_in_scroll_container($value);
}
add_filter('acf/format_value/type=wysiwyg', 'soj_wrap_tables_in_acf_wysiwyg', 20, 3);
