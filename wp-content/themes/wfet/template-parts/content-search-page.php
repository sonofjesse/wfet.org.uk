<?php
/**
 * Template part for displaying page search results in list format
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('search-result-page-item'); ?>>
    <div class="search-result-page-content">
        <div class="search-result-page-info">
            <h3 class="search-result-page-title">
                <a href="<?php the_permalink(); ?>" rel="bookmark">
                    <?php the_title(); ?>
                </a>
            </h3>
        </div>
    </div>
</article>