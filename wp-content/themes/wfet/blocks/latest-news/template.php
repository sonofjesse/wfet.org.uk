<?php
/**
 * Latest News Block Template.
 *
 * @param array $block The block settings and attributes.
 * @param string $content The block inner HTML (empty).
 * @param bool $is_preview True during AJAX preview.
 * @param int $post_id The post ID this block is saved to.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $block ACF block settings and attributes (injected by ACF at render). */
$id = 'latest-news-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'latest-news';
if (!empty($block['className'])) {
    $className .= ' ' . $block['className'];
}
if (!empty($block['align'])) {
    $className .= ' align' . $block['align'];
}

$margin_top    = get_field('margin_top') ?: 'mt-0';
$margin_bottom = get_field('margin_bottom') ?: 'mb-0';

if ($margin_top) {
    $className .= ' ' . $margin_top;
}
if ($margin_bottom) {
    $className .= ' ' . $margin_bottom;
}

if (!empty($is_preview) && isset($block['data']['preview_image_help'])) {
    $preview_image_url = get_template_directory_uri() . '/blocks/latest-news/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="Latest News preview" style="width:100%;height:auto;" />';
    return;
}

$is_block_preview = !empty($is_preview);
$title            = get_field('title');
$link             = get_field('link');
$posts_display    = get_field('posts_display') ?: 'latest';
$selected_posts   = get_field('selected_posts');

$query_args = [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 3,
    'ignore_sticky_posts' => true,
];

if ($posts_display === 'manual' && !empty($selected_posts)) {
    $post_ids = array_values(array_filter(array_map(static function ($post) {
        if (is_object($post) && isset($post->ID)) {
            return (int) $post->ID;
        }

        return (int) $post;
    }, (array) $selected_posts)));

    $query_args['post__in'] = array_slice($post_ids, 0, 3);
    $query_args['orderby']  = 'post__in';
} else {
    $query_args['orderby'] = 'date';
    $query_args['order']   = 'DESC';

    if (is_singular('post')) {
        $query_args['post__not_in'] = [get_the_ID()];
    }
}

$news_query = new WP_Query($query_args);

$link_url    = '';
$link_title  = '';
$link_target = '_self';

if (is_array($link) && !empty($link['url'])) {
    $link_url    = (string) $link['url'];
    $link_title  = !empty($link['title']) ? (string) $link['title'] : __('View all our work in action', 'soj-core');
    $link_target = !empty($link['target']) ? (string) $link['target'] : '_self';
}

if ($is_block_preview) {
    $className .= ' latest-news--editor-preview';
}
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($className); ?>">
    <div class="container">
        <?php if ($title || $link_url) : ?>
            <header class="latest-news__header" data-gsap-animate="slide-up">
                <?php if ($title) : ?>
                    <h2 class="latest-news__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>

                <?php if ($link_url) : ?>
                    <a
                        class="latest-news__view-all"
                        href="<?php echo esc_url($link_url); ?>"
                        title="<?php echo esc_attr($link_title); ?>"
                        <?php if ($link_target === '_blank') : ?>
                            target="_blank"
                            rel="noopener noreferrer"
                        <?php endif; ?>
                    >
                        <span class="latest-news__view-all-text"><?php echo esc_html($link_title); ?></span>
                        <span class="latest-news__view-all-arrow" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 8H14.5M14.5 8L8.5 2M14.5 8L8.5 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </a>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if ($news_query->have_posts()) : ?>
            <div class="latest-news__grid" data-gsap-animate="stagger">
                <?php
                while ($news_query->have_posts()) :
                    $news_query->the_post();
                    get_template_part(
                        'template-parts/news/card',
                        null,
                        [
                            'post_id'    => get_the_ID(),
                            'is_preview' => $is_block_preview,
                        ]
                    );
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php else : ?>
            <p class="latest-news__empty"><?php esc_html_e('No posts available yet.', 'soj-core'); ?></p>
        <?php endif; ?>
    </div>
</section>
