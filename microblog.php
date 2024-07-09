<?php
/*
Plugin Name: MicroBlog
Plugin URI: https://elica-webservices.it
Description: Adds a minimal front-end blogging form to your site giving it a microblog feel. Inspired by Narwhal Microblog
Version: 1.0
Requires CP: 1.0
Requires PHP: 8.1
Author: Elisabetta Carrara
Author URI: https://elica-webservices.it
License: GPL2
*/
if (!function_exists('microblog_enqueue_scripts')) {
  // Enqueue the CSS and JavaScript files
  function microblog_enqueue_scripts() {
    wp_enqueue_style('microblog-styles', plugin_dir_url(__FILE__) . 'microblog.css', array(), '3.3.1', 'all');
    wp_enqueue_script('microblog', plugins_url('microblog.js', __FILE__), array('jquery'), '3.3.1', true);
    wp_localize_script('microblog', 'microblogData', array(
  'ajaxurl' => admin_url('/admin-ajax.php'),
  'nonce' => wp_create_nonce('microblog'),
  'defaultCategory' => get_option('default_category'),
  'siteUrl' => get_site_url(),
  ));
  }
  add_action('wp_enqueue_scripts', 'microblog_enqueue_scripts');
}

if (!function_exists('microblog_shortcode')) {
  // Create the shortcode
  function microblog_shortcode($atts, $content = null) {
    $taxonomy = get_option('microblog_post_type_taxonomy', 'category');  // Default to 'category' if not set

    // Get all categories for the specified taxonomy
    $args = array(
      'taxonomy' => $taxonomy,
      'orderby' => 'name',
      'order' => 'ASC',
      'hide_empty' => false
    );
    $categories = get_terms($args);

	$html = '<div class="microblog-form-container">';
    $html = '<form id="microblog-form" method="post">';
    $html .= '<textarea id="microblog-content" name="microblog_content" placeholder="(Write the Title into parenthesis)
Your #content. #hastags become tags"></textarea>';
    $html .= '<select name="microblog_category" id="microblog-category">';
    
    if (!is_wp_error($categories) && !empty($categories)) {
      foreach ($categories as $category) {
        if (is_object($category) && property_exists($category, 'term_id') && property_exists($category, 'name')) {
          $html .= '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
        }
      }
    } else {
      // Fallback to get default category
      $default_category = get_term(get_option('default_category'), 'category');
      if (!is_wp_error($default_category) && $default_category) {
        $html .= '<option value="' . esc_attr($default_category->term_id) . '">' . esc_html($default_category->name) . '</option>';
      }
    }
    
    $html .= '</select>';
    $html .= '<input type="submit" value="Submit" />';
    $html .= '</form>';
    $html .= '</div>';
	
    return $html;
  }
  add_shortcode('microblog', 'microblog_shortcode');
}

if (!function_exists('microblog_submit')) {
  // Handle the AJAX request
  function microblog_submit() {
    // Verify the nonce
    if (!wp_verify_nonce($_POST['nonce'], 'microblog')) {
      wp_send_json_error('Invalid nonce');
    }

    // Check if the user is logged in
    if (!is_user_logged_in()) {
      wp_send_json_error('Hello guest! Have a great day!');
    }

    // Get the content, title, tags, and category from the AJAX request
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    $title = isset($_POST['title']) ? sanitize_textarea_field($_POST['title']) : '';
    $tags = isset($_POST['tags']) && is_array($_POST['tags']) ? array_map('sanitize_textarea_field', $_POST['tags']) : [];
    $category_id = isset($_POST['microblog_category']) ? intval($_POST['microblog_category']) : get_option('default_category');
    $post_type = get_option('microblog_post_type_setting');

    // Create the post
    $post_id = wp_insert_post(array(
      'post_title' => $title,
      'post_content' => $content,
      'post_status' => 'publish',
      'post_category' => array($category_id),
      'tags_input' => $tags,
      'post_type' => $post_type,
    ));

    // Check if the post was created successfully
    if ($post_id) {
      // Redirect to the created post
      wp_send_json_success(array('post_id' => $post_id));
    } else {
      wp_send_json_error('An error occurred while creating the post. Please try again later.');
    }
  }
  add_action('wp_ajax_microblog_submit', 'microblog_submit');
  add_action('wp_ajax_nopriv_microblog_submit', 'microblog_submit');
}

if (!function_exists('microblog_post_type_settings_init')) {
  // Register the settings
  function microblog_post_type_settings_init() {
    // Register a new section on the Writing Settings page
    add_settings_section(
      'microblog_post_type_section', // Section ID
      'microblog Post Type', // Section title
      'microblog_post_type_section_callback', // Callback function to display section description
      'writing' // Page where the section should be displayed
    );

    // Register the field to choose the MicroBlog Post Type
    add_settings_field(
      'microblog_post_type_field', // Field ID
      'Choose Post Type', // Field label
      'microblog_post_type_field_callback', // Callback function to display the field
      'writing', // Page where the field should be displayed
      'microblog_post_type_section' // Section ID to which the field belongs
    );

    // Register the setting to store the chosen post type
    register_setting(
      'writing', // Page where the setting should be saved
      'microblog_post_type_setting' // Setting name
    );

    // Register the field to choose the MicroBlog Post Type Taxonomy
    add_settings_field(
      'microblog_post_type_taxonomy_field', // Field ID
      'Choose Category Name', // Field label
      'microblog_post_type_taxonomy_field_callback', // Callback function to display the field
      'writing', // Page where the field should be displayed
      'microblog_post_type_section' // Section ID to which the field belongs
    );

    // Register the setting to store the chosen taxonomy
    register_setting(
      'writing', // Page where the setting should be saved
      'microblog_post_type_taxonomy' // Setting name
    );
  }
  add_action('admin_init', 'microblog_post_type_settings_init');
}

if (!function_exists('microblog_post_type_section_callback')) {
  // Callback function to display section description
  function microblog_post_type_section_callback() {
    echo '<p>Choose the post type and category name to be used for the MicroBlog front-end form. Make sure they match. The category name must exist for the post type selected.</p>';
  }
}

if (!function_exists('microblog_post_type_field_callback')) {
  // Callback function to display the post type field
  function microblog_post_type_field_callback() {
    $current_post_type = get_option('microblog_post_type_setting');
    $post_types = get_post_types(array('public' => true), 'objects');

    echo '<select name="microblog_post_type_setting">';
    foreach ($post_types as $post_type) {
      $selected = ($current_post_type == $post_type->name) ? 'selected' : '';
      echo '<option value="' . $post_type->name . '" ' . $selected . '>' . $post_type->label . '</option>';
    }
    echo '</select>';
  }
}

if (!function_exists('microblog_post_type_taxonomy_field_callback')) {
  // Callback function to display the post type taxonomy field
  function microblog_post_type_taxonomy_field_callback() {
    $current_taxonomy = get_option('microblog_post_type_taxonomy');
    $taxonomies = get_taxonomies(array('public' => true), 'objects');

    echo '<select name="microblog_post_type_taxonomy">';
    foreach ($taxonomies as $taxonomy) {
      $selected = ($current_taxonomy == $taxonomy->name) ? 'selected' : '';
      echo '<option value="' . $taxonomy->name . '" ' . $selected . '>' . $taxonomy->label . '</option>';
    }
    echo '</select>';
  }
}