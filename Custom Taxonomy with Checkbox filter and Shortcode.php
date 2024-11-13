<?php
/*
Plugin Name: WooCommerce Custom Taxonomy Filter with Page Reload
Description: Adds a custom taxonomy for WooCommerce products and enables filtering with page reload using checkboxes on archive pages with a shortcode.
Version: 1.7
Author: We Next Coder
Author URI: https://wenextcoder.com
*/

// 1. Register the Custom Taxonomy
function wc_register_custom_taxonomy() {
    $labels = array(
        'name'              => 'Product Versions',
        'singular_name'     => 'Product Version',
        'search_items'      => 'Search Product Versions',
        'all_items'         => 'All Product Versions',
        'parent_item'       => 'Parent Product Version',
        'parent_item_colon' => 'Parent Product Version:',
        'edit_item'         => 'Edit Product Version',
        'update_item'       => 'Update Product Version',
        'add_new_item'      => 'Add New Product Version',
        'new_item_name'     => 'New Product Version Name',
        'menu_name'         => 'Product Versions',
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'product-version' ),
    );

    register_taxonomy( 'product_version', 'product', $args );
}
add_action( 'init', 'wc_register_custom_taxonomy', 0 );

// 2. Enqueue JavaScript for Page Reload Filtering
function wc_enqueue_ajax_filter_scripts() {
    if (is_shop() || is_product_taxonomy()) {
        wp_enqueue_script('wc-ajax-filter', plugin_dir_url(__FILE__) . 'js/wc-ajax-filter.js', array('jquery'), '1.0', true);
    }
}
add_action('wp_enqueue_scripts', 'wc_enqueue_ajax_filter_scripts');

// 3. Display the Appropriate Filter Section Based on the Current Category or Product Version Archive
function wc_custom_taxonomy_filter_shortcode() {
    ob_start();

    // Get the current category or taxonomy term
    $current_category = get_queried_object();

    echo '<div id="product-version-filter">';

    // Display specific filter sections based on predefined categories
    if ($current_category && $current_category->taxonomy == 'product_cat') {
        // Predefined categories
        if ($current_category->name == 'Office') {
            $parent_name = 'Office Anwendungen';
            display_filter_section($parent_name);
        } elseif ($current_category->name == 'Server') {
            $parent_name = 'Server';
            display_filter_section($parent_name);
        } elseif ($current_category->name == 'Operation System') {
            $parent_name = 'Operation System';
            display_filter_section($parent_name);
        } elseif ($current_category->name == 'CALs') {
            $parent_name = 'CALs';
            display_filter_section($parent_name);
        }
    }
    // Add a general filter display for any product-version archive page not matching specific categories
    elseif (is_tax('product_version')) {
        // Check if it's a product-version archive and display the filter for the current product version parent
        $parent_term = get_top_level_parent_term($current_category);
        if ($parent_term) {
            display_filter_section($parent_term->name); // Show terms based on the parent product version
        }
    }

    echo '</div>';

    return ob_get_clean();
}
add_shortcode('product_version_filter', 'wc_custom_taxonomy_filter_shortcode');

// Helper function to display a filter section based on the parent category
function display_filter_section($parent_name) {
    // Get the parent term object by name
    $parent_term = get_term_by('name', $parent_name, 'product_version');
    
    if ($parent_term) {
        // Display the parent term as a section heading
        echo '<h3>' . esc_html($parent_term->name) . ':</h3>';
        
        // Get child terms under this parent term
        $child_terms = get_terms(array(
            'taxonomy' => 'product_version',
            'hide_empty' => true,
            'parent' => $parent_term->term_id,
        ));
        
        // If no child terms are found, fetch all terms under the 'product_version' taxonomy
        if (empty($child_terms)) {
            $child_terms = get_terms(array(
                'taxonomy' => 'product_version',
                'hide_empty' => true,
            ));
        }

        // Display checkboxes for each term
        if (!empty($child_terms)) {
            foreach ($child_terms as $term) {
                // Check if the term should be pre-selected
                $checked = (is_tax('product_version', $term->slug) || (isset($_GET['product_version']) && in_array($term->slug, $_GET['product_version']))) ? 'checked' : '';
                
                echo '<label><input type="checkbox" name="product_version[]" value="' . esc_attr($term->slug) . '" ' . $checked . '> ' . esc_html($term->name) . '</label><br>';
            }
        }
    }
}

// Helper function to get the top-level parent term of the current term
function get_top_level_parent_term($term) {
    while ($term->parent != 0) {
        $term = get_term($term->parent, 'product_version');
        if (is_wp_error($term)) {
            return false;
        }
    }
    return $term;
}

// 4. Modify the Query to Filter Products Based on Selected Taxonomy
function wc_modify_product_query($query) {
    if (is_shop() || is_product_taxonomy() || is_product_category()) {
        if (isset($_GET['product_version']) && !empty($_GET['product_version'])) {
            $query->set('tax_query', array(
                array(
                    'taxonomy' => 'product_version',
                    'field'    => 'slug',
                    'terms'    => $_GET['product_version'],
                    'operator' => 'IN',
                ),
            ));
        }
    }
}
add_action('pre_get_posts', 'wc_modify_product_query');
