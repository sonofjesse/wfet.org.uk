<?php

/**
 * Custom Post Types
 *
 * @package SOJ_Core_Modern
 * @since 2.0.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register Services Custom Post Type
 */
function soj_register_services_post_type()
{
    $labels = array(
        'name'                  => _x('Services', 'Post Type General Name', 'soj-core'),
        'singular_name'         => _x('Service', 'Post Type Singular Name', 'soj-core'),
        'menu_name'             => __('Services', 'soj-core'),
        'name_admin_bar'        => __('Service', 'soj-core'),
        'archives'              => __('Service Archives', 'soj-core'),
        'attributes'            => __('Service Attributes', 'soj-core'),
        'parent_item_colon'     => __('Parent Service:', 'soj-core'),
        'all_items'             => __('All Services', 'soj-core'),
        'add_new_item'          => __('Add New Service', 'soj-core'),
        'add_new'               => __('Add New', 'soj-core'),
        'new_item'              => __('New Service', 'soj-core'),
        'edit_item'             => __('Edit Service', 'soj-core'),
        'update_item'           => __('Update Service', 'soj-core'),
        'view_item'             => __('View Service', 'soj-core'),
        'view_items'            => __('View Services', 'soj-core'),
        'search_items'          => __('Search Services', 'soj-core'),
        'not_found'             => __('Not found', 'soj-core'),
        'not_found_in_trash'    => __('Not found in Trash', 'soj-core'),
        'featured_image'        => __('Featured Image', 'soj-core'),
        'set_featured_image'    => __('Set featured image', 'soj-core'),
        'remove_featured_image' => __('Remove featured image', 'soj-core'),
        'use_featured_image'    => __('Use as featured image', 'soj-core'),
        'insert_into_item'      => __('Insert into service', 'soj-core'),
        'uploaded_to_this_item' => __('Uploaded to this service', 'soj-core'),
        'items_list'            => __('Services list', 'soj-core'),
        'items_list_navigation' => __('Services list navigation', 'soj-core'),
        'filter_items_list'     => __('Filter services list', 'soj-core'),
    );

    $args = array(
        'label'               => __('Services', 'soj-core'),
        'description'         => __('Services', 'soj-core'),
        'labels'              => $labels,
        'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 6,
        'menu_icon'           => 'dashicons-clipboard',
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
        'show_in_rest'        => true,
        'rewrite'             => array(
            'slug'       => 'services',
            'with_front' => false,
        ),
    );

    register_post_type('service', $args);
}
add_action('init', 'soj_register_services_post_type');

/**
 * Register Insights Custom Post Type
 */
function soj_register_insights_post_type()
{
    $labels = array(
        'name'                  => _x('Insights', 'Post Type General Name', 'soj-core'),
        'singular_name'         => _x('Insight', 'Post Type Singular Name', 'soj-core'),
        'menu_name'             => __('Insights', 'soj-core'),
        'name_admin_bar'        => __('Insight', 'soj-core'),
        'archives'              => __('Insight Archives', 'soj-core'),
        'attributes'            => __('Insight Attributes', 'soj-core'),
        'parent_item_colon'     => __('Parent Insight:', 'soj-core'),
        'all_items'             => __('All Insights', 'soj-core'),
        'add_new_item'          => __('Add New Insight', 'soj-core'),
        'add_new'               => __('Add New', 'soj-core'),
        'new_item'              => __('New Insight', 'soj-core'),
        'edit_item'             => __('Edit Insight', 'soj-core'),
        'update_item'           => __('Update Insight', 'soj-core'),
        'view_item'             => __('View Insight', 'soj-core'),
        'view_items'            => __('View Insights', 'soj-core'),
        'search_items'          => __('Search Insights', 'soj-core'),
        'not_found'             => __('Not found', 'soj-core'),
        'not_found_in_trash'    => __('Not found in Trash', 'soj-core'),
        'featured_image'        => __('Featured Image', 'soj-core'),
        'set_featured_image'    => __('Set featured image', 'soj-core'),
        'remove_featured_image' => __('Remove featured image', 'soj-core'),
        'use_featured_image'    => __('Use as featured image', 'soj-core'),
        'insert_into_item'      => __('Insert into insight', 'soj-core'),
        'uploaded_to_this_item' => __('Uploaded to this insight', 'soj-core'),
        'items_list'            => __('Insights list', 'soj-core'),
        'items_list_navigation' => __('Insights list navigation', 'soj-core'),
        'filter_items_list'     => __('Filter insights list', 'soj-core'),
    );

    $args = array(
        'label'               => __('Insights', 'soj-core'),
        'description'         => __('Insights', 'soj-core'),
        'labels'              => $labels,
        'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 7,
        'menu_icon'           => 'dashicons-lightbulb',
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
        'show_in_rest'        => true,
        'rewrite'             => array(
            'slug'       => 'insight',
            'with_front' => false,
        ),
    );

    register_post_type('insight', $args);
}
add_action('init', 'soj_register_insights_post_type');

/**
 * Register Insights Category taxonomy
 */
function soj_register_insights_category_taxonomy()
{
    register_taxonomy(
        'insights-category',
        array('insight'),
        array(
            'hierarchical'      => true,
            'labels'            => array(
                'name'          => _x('Insights Categories', 'taxonomy general name', 'soj-core'),
                'singular_name' => _x('Insights Category', 'taxonomy singular name', 'soj-core'),
                'menu_name'     => __('Categories', 'soj-core'),
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'insights-category'),
            'show_in_rest'      => true,
        )
    );
}
add_action('init', 'soj_register_insights_category_taxonomy');

/**
 * Register custom taxonomies
 */
/*
function soj_register_taxonomies()
{
    // Team Categories
    register_taxonomy(
        'team_category',
        array( 'team' ),
        array(
            'hierarchical'      => true,
            'labels'            => array(
                'name'          => _x('Team Categories', 'taxonomy general name', 'soj-core'),
                'singular_name' => _x('Team Category', 'taxonomy singular name', 'soj-core'),
                'menu_name'     => __('Categories', 'soj-core'),
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'team-category' ),
            'show_in_rest'      => true,
        )
    );

    // Event Categories
    register_taxonomy(
        'event_category',
        array( 'events' ),
        array(
            'hierarchical'      => true,
            'labels'            => array(
                'name'          => _x('Event Categories', 'taxonomy general name', 'soj-core'),
                'singular_name' => _x('Event Category', 'taxonomy singular name', 'soj-core'),
                'menu_name'     => __('Categories', 'soj-core'),
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'event-category' ),
            'show_in_rest'      => true,
        )
    );
}
add_action('init', 'soj_register_taxonomies');
*/

/**
 * Flush rewrite rules on theme activation
 */
function soj_flush_rewrite_rules()
{
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'soj_flush_rewrite_rules');
