<?php
/**
 * Template part for displaying search results
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('search-result-item'); ?>>
    <div class="search-result-content">
        <header class="search-result-header">
            <?php
            $post_type = get_post_type();
            $post_type_obj = get_post_type_object($post_type);
            ?>
            <div class="search-result-meta">
                <span class="post-type-badge post-type-<?php echo esc_attr($post_type); ?>">
                    <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                </span>
                <time class="search-result-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                    <?php echo esc_html(get_the_date()); ?>
                </time>
            </div>
            
            <h2 class="search-result-title">
                <a href="<?php the_permalink(); ?>" rel="bookmark">
                    <?php the_title(); ?>
                </a>
            </h2>
        </header>

        <div class="search-result-excerpt">
            <?php
            // Get excerpt or generate one from content
            if (has_excerpt()) {
                the_excerpt();
            } else {
                // Generate excerpt from content, highlighting search terms
                $content = get_the_content();
                $excerpt = wp_trim_words($content, 30, '...');
                
                // Highlight search terms in excerpt
                $search_query = get_search_query();
                if ($search_query) {
                    $excerpt = preg_replace(
                        '/(' . preg_quote($search_query, '/') . ')/i',
                        '<mark class="search-highlight">$1</mark>',
                        $excerpt
                    );
                }
                
                echo wp_kses_post($excerpt);
            }
            ?>
        </div>

        <footer class="search-result-footer">
            <?php
            soj_the_button([
                'url' => get_permalink(),
                'title' => __('Read More', 'soj-core'),
            ]);
            ?>
        </footer>
    </div>
</article>
