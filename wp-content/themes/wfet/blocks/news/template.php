<?php
/**
 * News Block Template.
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
$id = 'news-' . $block['id'];
if (!empty($block['anchor'])) {
    $id = $block['anchor'];
}

$className = 'news';
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
    $preview_image_url = get_template_directory_uri() . '/blocks/news/preview.png';
    echo '<img src="' . esc_url($preview_image_url) . '" alt="News preview" style="width:100%;height:auto;" />';
    return;
}

$is_block_preview = !empty($is_preview);
$title            = get_field('title');
$content          = get_field('content');
$link             = get_field('link');
$type             = get_field('type') ?: 'all';
$category         = get_field('category');
$allowed_types    = ['all', 'category'];

if (!in_array($type, $allowed_types, true)) {
    $type = 'all';
}

$className .= ' news--type-' . sanitize_html_class($type);

$query_args = [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 3,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'ignore_sticky_posts' => true,
];

if ($type === 'category') {
    $category_term_id = 0;

    if (is_object($category) && !empty($category->term_id)) {
        $category_term_id = (int) $category->term_id;
    } elseif (is_numeric($category)) {
        $category_term_id = (int) $category;
    }

    if ($category_term_id > 0) {
        $query_args['cat'] = $category_term_id;
    }
}

if (is_singular('post')) {
    $query_args['post__not_in'] = [get_the_ID()];
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
    $className .= ' news--editor-preview';
}

$context_post_id = !empty($post_id) ? (int) $post_id : (int) get_the_ID();
$show_category   = get_post_type($context_post_id) !== 'service';

if ($type === 'category') {
    $category_term = null;

    if ($category instanceof WP_Term) {
        $category_term = $category;
    } elseif (is_object($category) && !empty($category->term_id)) {
        $category_term = get_term((int) $category->term_id, 'category');
    } elseif (is_numeric($category) && (int) $category > 0) {
        $category_term = get_term((int) $category, 'category');
    }

    if ($category_term instanceof WP_Term && !is_wp_error($category_term) && function_exists('soj_get_news_category_colour')) {
        $background_colour = soj_get_news_category_colour($category_term);
        $allowed_colours   = ['rose-dark', 'moss-dark', 'sky-dark'];

        if (in_array($background_colour, $allowed_colours, true)) {
            $className .= ' news--bg-' . sanitize_html_class($background_colour);
        }
    }
}
?>

<section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($className); ?>">
    <div class="container">
        <?php if ($title || $content || $link_url) : ?>
            <header class="news__header" data-gsap-animate="slide-up">
                <?php if ($title) : ?>
                    <h2 class="news__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>

                <?php if ($content && in_array($type, $allowed_types, true)) : ?>
                    <p class="news__content"><?php echo esc_html($content); ?></p>
                <?php endif; ?>

                <?php if ($link_url) : ?>
                    <a
                        class="news__view-all"
                        href="<?php echo esc_url($link_url); ?>"
                        <?php if ($link_target === '_blank') : ?>
                            target="_blank"
                            rel="noopener noreferrer"
                        <?php endif; ?>
                    >
                        <span class="news__view-all-text"><?php echo esc_html($link_title); ?></span>
                        <span class="news__view-all-arrow" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 8H14.5M14.5 8L8.5 2M14.5 8L8.5 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </a>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if ($news_query->have_posts()) : ?>
            <div class="news__grid" data-gsap-animate="stagger">
                <?php
                while ($news_query->have_posts()) :
                    $news_query->the_post();
                    get_template_part(
                        'template-parts/news/card',
                        null,
                        [
                            'post_id'       => get_the_ID(),
                            'is_preview'    => $is_block_preview,
                            'show_category' => $show_category,
                        ]
                    );
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php else : ?>
            <p class="news__empty"><?php esc_html_e('No posts available yet.', 'soj-core'); ?></p>
        <?php endif; ?>
    </div>
</section>
