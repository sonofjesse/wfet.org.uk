<?php
/**
 * News card — reusable post layout shell.
 *
 * @package SOJ_Core_Modern
 *
 * @var array $args {
 *     @type int  $post_id       Post ID to render.
 *     @type bool $is_preview    Whether the card is rendered in the block editor.
 *     @type bool $show_category Whether to output the category tag. Default true.
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

if ($post_id <= 0) {
    return;
}

$card_args = wp_parse_args(
    $args,
    [
        'post_id'       => $post_id,
        'is_preview'    => false,
        'show_category' => true,
        'eager_images'  => false,
    ]
);
?>

<article class="news-card">
    <?php get_template_part('template-parts/news/card', 'image', $card_args); ?>

    <div class="news-card__content">
        <?php
        if (!empty($card_args['show_category'])) {
            get_template_part('template-parts/news/card', 'category', $card_args);
        }
        get_template_part('template-parts/news/card', 'title', $card_args);
        get_template_part('template-parts/news/card', 'excerpt', $card_args);
        get_template_part('template-parts/news/card', 'read-more', $card_args);
        ?>
    </div>
</article>
