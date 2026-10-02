<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* Register Clinic Listings Custom Post Type */
function tg_register_clinic_listings_cpt() {
    $labels = array(
        'name'                  => __('Clinic Listings', 'tg'),
        'singular_name'         => __('Clinic Listing', 'tg'),
        'menu_name'             => __('Clinic Listings', 'tg'),
        'add_new'               => __('Add New Clinic', 'tg'),
        'add_new_item'          => __('Add New Clinic', 'tg'),
        'edit_item'             => __('Edit Clinic', 'tg'),
        'new_item'              => __('New Clinic', 'tg'),
        'view_item'             => __('View Clinic', 'tg'),
        'search_items'          => __('Search Clinics', 'tg'),
    );
    $args = array(
        'labels'                => $labels,
        'public'                => true,
        'publicly_queryable'    => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-heart',
        'show_in_rest'          => true,
        'rest_base'             => 'clinics', // API endpoint: /wp-json/wp/v2/clinics
        'has_archive'           => true,
        'rewrite'               => array(
            'slug'       => 'clinic-listings',
            'with_front' => false
        ),
        'supports' => array(
            'title',
            'editor',
            'thumbnail',
            'custom-fields',
            'revisions',
        ),
        'taxonomies' => array('clinic_category'),
    );
    register_post_type('clinic_listing', $args);
}
add_action('init', 'tg_register_clinic_listings_cpt');

/* Register Clinic Categories Taxonomy */
function tg_register_clinic_categories() {
    $args = array(
        'label'                 => __('Clinic Categories', 'tg'),
        'public'                => true,
        'hierarchical'          => true,
        'show_ui'               => true,
        'show_in_rest'          => true,
        'rewrite'               => array('slug' => 'clinic-category'),
    );
    register_taxonomy('clinic_category', 'clinic_listing', $args);
}
add_action('init', 'tg_register_clinic_categories');