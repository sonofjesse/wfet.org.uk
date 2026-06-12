<?php

/**
 * The template for displaying search results pages
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

get_header();
?>

<main id="primary" class="site-main search-results-page">
    <div class="topbox">
        <div class="container">

        </div>
    </div>
    <div class="container">
        <div class="inner">


            <!-- Search Form -->
            <div class="search_container_search_page">
                <div class="inner-search">
                    <form class="search-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                        <label class="screen-reader-text" for="header-search-input">
                            <?php echo esc_html__('Search for:', 'soj-core'); ?>
                        </label>
                        <input
                            id="header-search-input"
                            class="search-input"
                            type="search"
                            name="s"
                            placeholder="<?php echo esc_attr__('What do you need help with?', 'soj-core'); ?>"
                            value="<?php echo esc_attr(get_search_query()); ?>" />
                        <button class="search-submit" type="submit" aria-label="<?php echo esc_attr__('Search', 'soj-core'); ?>">
                            <svg width="19" height="19" viewBox="0 0 19 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14.625 7.3125C14.625 8.92969 14.0977 10.4414 13.2188 11.6367L17.6484 16.1016C18.1055 16.5234 18.1055 17.2617 17.6484 17.6836C17.2266 18.1406 16.4883 18.1406 16.0664 17.6836L11.6016 13.2188C10.4062 14.1328 8.89453 14.625 7.3125 14.625C3.26953 14.625 0 11.3555 0 7.3125C0 3.30469 3.26953 0 7.3125 0C11.3203 0 14.625 3.30469 14.625 7.3125ZM7.3125 12.375C9.10547 12.375 10.7578 11.4258 11.6719 9.84375C12.5859 8.29688 12.5859 6.36328 11.6719 4.78125C10.7578 3.23438 9.10547 2.25 7.3125 2.25C5.48438 2.25 3.83203 3.23438 2.91797 4.78125C2.00391 6.36328 2.00391 8.29688 2.91797 9.84375C3.83203 11.4258 5.48438 12.375 7.3125 12.375Z" fill="white" />
                            </svg>

                        </button>

                    </form>
                </div>
            </div>

            <?php
            $search_term = trim(get_search_query());
            global $wp_query;
            ?>

            <?php if ($search_term !== '' && $wp_query->have_posts()) : ?>
                <div class="search-results-header">
                    <p class="search-results-count">
                        <span class="results-qty">
                            <?php
                            printf(
                                /* translators: %d: number of results. */
                                esc_html(_n('%d result', '%d results', $wp_query->found_posts, 'soj-core')),
                                $wp_query->found_posts
                            );
                            ?>
                        </span>
                        <span class="results-term">
                            <?php
                            printf(
                                /* translators: %s: search query. */
                                esc_html__('for “%s”', 'soj-core'),
                                esc_html($search_term)
                            );
                            ?>
                        </span>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Search Results Content -->
            <?php
            if ($search_term === '') : ?>
                <div class="no-search-results">
                    <div class="no-results-content">
                        <p><?php esc_html_e('No results found. Please enter a keyword or phrase to search for content.', 'soj-core'); ?></p>
                    </div>
                </div>
                <?php else :
                // Use the main WordPress query (limited to 20 posts per page)
                if (have_posts()) : ?>
                    <div class="search-results-content">

                        <!-- All results in one unified list -->
                        <ul class="search-results-list">
                            <?php while (have_posts()) : the_post(); ?>
                                <li <?php post_class('search-result-item'); ?> id="post-<?php the_ID(); ?>">
                                    <a class="search-result-link" href="<?php echo esc_url(get_permalink()); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        </ul>

                        <!-- Pagination -->
                        <?php
                        $total_pages = $wp_query->max_num_pages;
                        if ($total_pages > 1) : ?>
                            <nav class="search-pagination" aria-label="<?php esc_attr_e('Search results pages', 'soj-core'); ?>">
                                <?php
                                echo paginate_links(array(
                                    'total' => $total_pages,
                                    'current' => max(1, get_query_var('paged')),
                                    'format' => '?paged=%#%',
                                    'prev_next' => false,
                                    'mid_size' => 2,
                                    'type' => 'list',
                                ));
                                ?>
                            </nav>
                        <?php endif; ?>

                    </div>
                <?php else : ?>
                    <div class="no-search-results pt-4 pb-4">
                        <div class="no-results-content">
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: search query. */
                                    esc_html__('Sorry, no results found for “%s”.', 'soj-core'),
                                    esc_html($search_term)
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div><!-- .container -->
</main><!-- #primary -->

<?php get_footer();
