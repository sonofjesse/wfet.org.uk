<?php

namespace FlyingPress\Integrations;

use FlyingPress\{AutoPurge, Purge, Preload};

class WooCommerce
{
  public static function init()
  {
    // Exclude cart, checkout, and account pages from cache
    add_filter('flying_press_is_cacheable', [__CLASS__, 'is_cacheable']);

    // Stock updated
    add_action('woocommerce_product_set_stock', [__CLASS__, 'purge_product']);
    add_action('woocommerce_variation_set_stock', [__CLASS__, 'purge_product']);

    // Product updated via batch rest API
    add_action('woocommerce_rest_insert_product_object', [__CLASS__, 'purge_product']);

    // Include Queries
    add_filter('flying_press_cache_include_queries', [__CLASS__, 'cache_include_queries']);
  }

  public static function is_cacheable($is_cacheable)
  {
    if (!class_exists('woocommerce')) {
      return $is_cacheable;
    }

    // If the current page is a WooCommerce cart, checkout, or account page, return false
    if (is_cart() || is_checkout() || is_account_page()) {
      return false;
    }

    return $is_cacheable;
  }

  public static function purge_product($product)
  {
    $urls = [];

    $product_id = $product->get_id();

    // Add product URL
    $urls[] = get_permalink($product_id);

    // Add shop page URL
    $urls[] = get_permalink(wc_get_page_id('shop'));

    // Taxonomy URLs
    $urls = [...$urls, ...AutoPurge::get_post_taxonomy_urls($product_id)];

    // Add URLs from filter
    $urls = apply_filters('flying_press_auto_purge_urls', $urls, $product_id);

    // Get unique URLs
    $urls = array_filter(array_unique($urls));

    Purge::purge_urls($urls);
    Preload::preload_urls($urls, 11);
  }

  public static function cache_include_queries($queries)
  {
    if (!class_exists('woocommerce')) {
      return $queries;
    }

    $attribute_filters = [];

    // Get all product attributes
    $product_attributes = wc_get_attribute_taxonomies();

    // Build the available query parameters
    foreach ($product_attributes as $product_attribute) {
      $attribute_filters[] = 'filter_' . $product_attribute->attribute_name;
    }

    // Append to existing queries
    $queries = [...$queries, ...$attribute_filters];

    return $queries;
  }
}
