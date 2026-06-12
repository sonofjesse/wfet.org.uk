<?php

/**
 * FAQPage JSON-LD for FAQ blocks (Google rich results).
 *
 * @package SOJ_Core_Modern
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register a question/answer pair for the page-level FAQPage schema.
 * Skips empty questions or answers. Answer HTML is normalised to plain text.
 *
 * @param string $question    Plain question text.
 * @param string $answer_html Answer HTML from the CMS.
 */
function soj_faq_schema_collect_pair($question, $answer_html)
{
    if (! is_string($question)) {
        return;
    }
    $question = trim($question);
    if ($question === '') {
        return;
    }
    $answer_text = soj_faq_schema_answer_to_plain_text($answer_html ?? '');
    if ($answer_text === '') {
        return;
    }
    if (! isset($GLOBALS['soj_faq_schema_items']) || ! is_array($GLOBALS['soj_faq_schema_items'])) {
        $GLOBALS['soj_faq_schema_items'] = [];
    }
    $GLOBALS['soj_faq_schema_items'][] = [
        'name' => $question,
        'text' => $answer_text,
    ];
}

/**
 * @param mixed $answer_html
 */
function soj_faq_schema_answer_to_plain_text($answer_html)
{
    if (! is_string($answer_html) || $answer_html === '') {
        return '';
    }
    $text = wp_strip_all_tags($answer_html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text));
}

/**
 * Output a single FAQPage script for the current page (all blocks combined).
 */
function soj_faq_schema_print_json_ld()
{
    if (empty($GLOBALS['soj_faq_schema_items']) || ! is_array($GLOBALS['soj_faq_schema_items'])) {
        return;
    }

    $main_entity = [];
    foreach ($GLOBALS['soj_faq_schema_items'] as $row) {
        if (empty($row['name']) || empty($row['text'])) {
            continue;
        }
        $main_entity[] = [
            '@type' => 'Question',
            'name' => $row['name'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $row['text'],
            ],
        ];
    }

    if ($main_entity === []) {
        return;
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $main_entity,
    ];

    echo '<script type="application/ld+json">';
    echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "</script>\n";
}
add_action('wp_footer', 'soj_faq_schema_print_json_ld', 20);
