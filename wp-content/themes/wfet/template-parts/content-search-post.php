<?php
/**
 * Template part for displaying blog post search results (card layout)
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */
?>

<article class="search-result-post-card" itemscope itemtype="https://schema.org/NewsArticle">
    <div class="search-result-post-image-wrapper">
        <?php if (has_post_thumbnail()) : ?>
            <a href="<?php the_permalink(); ?>" itemprop="url">
                <?php
                $thumbnail_id = get_post_thumbnail_id();
                $thumbnail_alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true) ?: get_the_title();
                $search_picture = function_exists('soj_render_responsive_picture')
                    ? soj_render_responsive_picture($thumbnail_id, [
                        'breakpoints'   => [0 => [1024, 683, false]],
                        'fallback_size' => [1024, 683, false],
                        'img_class'     => 'search-result-post-image',
                        'alt'           => $thumbnail_alt,
                        'img_attributes' => ['itemprop' => 'image'],
                    ])
                    : '';
                if ($search_picture): ?>
                    <?php echo $search_picture; ?>
                <?php else: ?>
                    <?php echo wp_get_attachment_image($thumbnail_id, 'large', false, array(
                        'class' => 'search-result-post-image',
                        'itemprop' => 'image',
                        'alt' => $thumbnail_alt
                    )); ?>
                <?php endif; ?>
            </a>
        <?php else : ?>
            <a href="<?php the_permalink(); ?>" itemprop="url">
                <div class="search-result-post-image search-result-post-placeholder" style="background-color: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">
                    No Image
                </div>
            </a>
        <?php endif; ?>
        
        <!-- Category Labels -->
        <?php 
        $post_categories = get_the_category();
        if ($post_categories) : ?>
            <div class="search-result-post-category-label">
                <?php foreach ($post_categories as $category) : ?>
                    <span class="search-result-post-category-badge" itemprop="articleSection"><?php echo esc_html($category->name); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="search-result-post-content">
        <!-- Date -->
        <time class="search-result-post-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>" itemprop="datePublished">
            <?php echo esc_html(get_the_date('m.d.Y')); ?>
        </time>
        
        <!-- Title -->
        <h3 class="search-result-post-title" itemprop="headline">
            <a href="<?php the_permalink(); ?>" itemprop="url">
                <?php 
                $title = get_the_title();
                // Highlight search terms in title
                $search_query = get_search_query();
                if ($search_query) {
                    $title = preg_replace(
                        '/(' . preg_quote($search_query, '/') . ')/i',
                        '<mark class="search-highlight">$1</mark>',
                        $title
                    );
                }
                echo wp_kses_post($title);
                ?>
            </a>
        </h3>
        
        <!-- Excerpt -->
        <div class="search-result-post-excerpt">
            <?php
            // Get excerpt or generate one from content
            if (has_excerpt()) {
                $excerpt = get_the_excerpt();
            } else {
                // Generate excerpt from content, highlighting search terms
                $content = get_the_content();
                $excerpt = wp_trim_words($content, 15, '...');
            }
            
            // Highlight search terms in excerpt
            if ($search_query) {
                $excerpt = preg_replace(
                    '/(' . preg_quote($search_query, '/') . ')/i',
                    '<mark class="search-highlight">$1</mark>',
                    $excerpt
                );
            }
            
            echo wp_kses_post($excerpt);
            ?>
        </div>
        
        <!-- Tags -->
        <?php 
        $post_tags = get_the_tags();
        if ($post_tags) : ?>
            <div class="search-result-post-tags">
                <?php 
                $tag_count = count($post_tags);
                $displayed_tags = array_slice($post_tags, 0, 2);
                $remaining_count = $tag_count - 2;
                
                // Display first 2 tags
                foreach ($displayed_tags as $tag) : ?>
                    <span class="search-result-post-tag" itemprop="keywords"><?php echo esc_html($tag->name); ?></span>
                <?php endforeach; 
                
                // Add "+X" tag if there are more than 2 tags
                if ($remaining_count > 0) : ?>
                    <span class="search-result-post-tag search-result-post-tag--more">+<?php echo $remaining_count; ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</article>
