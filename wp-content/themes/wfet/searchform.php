<form action="/" method="get" class="search-form">
    <label for="s">Looking for something?</label>
    <input type="text" name="s" placeholder="Type here to search" value="<?php echo esc_attr(get_search_query()); ?>">
    <div class="search-hint">Press enter to search</div>
    <button type="submit" class="search-icon" aria-label="Search"></button>
</form>