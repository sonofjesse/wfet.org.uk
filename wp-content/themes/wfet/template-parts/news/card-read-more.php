<?php
/**
 * News card read-more link.
 *
 * @package SOJ_Core_Modern
 *
 * @var array $args {
 *     @type int  $post_id    Post ID to render.
 *     @type bool $is_preview Whether the card is rendered in the block editor.
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

if ($post_id <= 0) {
    return;
}

$is_preview = !empty($args['is_preview']);
$permalink  = get_permalink($post_id);
$title      = get_the_title($post_id);
$read_more_label = $title !== ''
    ? sprintf(
        /* translators: %s: post title */
        __('Read more about %s', 'soj-core'),
        $title
    )
    : __('Read more', 'soj-core');
?>

<?php if (!$is_preview && $permalink) : ?>
    <a
        class="news-card__read-more"
        href="<?php echo esc_url($permalink); ?>"
        title="<?php echo esc_attr($read_more_label); ?>"
    >
        <span class="news-card__read-more-text"><?php esc_html_e('Read more', 'soj-core'); ?></span>
        <?php if ($title !== '') : ?>
            <span class="sr-only">
                <?php
                printf(
                    ' %s',
                    esc_html(
                        sprintf(
                            /* translators: %s: post title */
                            __('about %s', 'soj-core'),
                            $title
                        )
                    )
                );
                ?>
            </span>
        <?php endif; ?>
        <span class="news-card__read-more-arrow" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 8H14.5M14.5 8L8.5 2M14.5 8L8.5 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </a>
<?php else : ?>
    <span class="news-card__read-more news-card__read-more--static">
        <span class="news-card__read-more-text"><?php esc_html_e('Read more', 'soj-core'); ?></span>
        <span class="news-card__read-more-arrow" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 8H14.5M14.5 8L8.5 2M14.5 8L8.5 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </span>
<?php endif; ?>
