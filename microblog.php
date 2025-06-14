<?php
/**
 * Plugin Name: MicroBlog
 * Plugin URI: https://elica-webservices.it
 * Description: Adds a minimal front-end blogging form to your site giving it a microblog feel. Inspired by Narwhal Microblog
 * Version: 1.1
 * Requires CP: 1.0
 * Requires PHP: 8.1
 * Author: Elisabetta Carrara
 * Author URI: https://elica-webservices.it
 * License: GPL2
 * Text Domain: microblog
 * Domain Path: /languages
 *
 * @package MicroBlog
 * @author Elisabetta Carrara
 * @license GPL2
 * @since 1.0.0
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue CSS and JavaScript files for the MicroBlog plugin
 *
 * This function is hooked to wp_enqueue_scripts to load the plugin's
 * stylesheet and JavaScript file on the frontend. It also localizes
 * the script with necessary data for AJAX functionality.
 *
 * @since 1.0.0
 * @return void
 */
function microblog_enqueue_scripts() {
    // Enqueue the plugin's CSS file
    wp_enqueue_style(
        'microblog-styles',
        plugin_dir_url(__FILE__) . 'microblog.css',
        array(),
        '3.3.1',
        'all'
    );

    // Enqueue the plugin's JavaScript file with jQuery dependency
    wp_enqueue_script(
        'microblog',
        plugins_url('microblog.js', __FILE__),
        array('jquery'),
        '3.3.1',
        true
    );

    // Localize script with data needed for AJAX requests
    wp_localize_script('microblog', 'microblogData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('microblog'),
        'defaultCategory' => 'status',
        'siteUrl' => get_site_url(),
        'messages' => array(
            'success' => __('Microblog post created successfully!', 'microblog'),
            'error' => __('An error occurred while creating the post. Please try again.', 'microblog'),
            'emptyContent' => __('Please enter some content for your microblog post.', 'microblog'),
        ),
    ));
}
add_action('wp_enqueue_scripts', 'microblog_enqueue_scripts');

/**
 * Register the Microblog Custom Post Type and its associated taxonomy
 *
 * This function creates a custom post type called 'microblog' for storing
 * microblog posts and a hierarchical taxonomy 'microblog_category' for
 * categorizing these posts. It also ensures a default 'status' category exists.
 *
 * @since 1.0.0
 * @return void
 */
function microblog_register_cpt_taxonomy() {
    // Define labels for the Microblog Custom Post Type
    $cpt_labels = array(
        'name'               => _x('Microblogs', 'post type general name', 'microblog'),
        'singular_name'      => _x('Microblog', 'post type singular name', 'microblog'),
        'add_new'            => _x('Add New', 'microblog', 'microblog'),
        'add_new_item'       => __('Add New Microblog', 'microblog'),
        'edit_item'          => __('Edit Microblog', 'microblog'),
        'new_item'           => __('New Microblog', 'microblog'),
        'all_items'          => __('All Microblogs', 'microblog'),
        'view_item'          => __('View Microblog', 'microblog'),
        'search_items'       => __('Search Microblogs', 'microblog'),
        'not_found'          => __('No microblogs found', 'microblog'),
        'not_found_in_trash' => __('No microblogs found in Trash', 'microblog'),
        'menu_name'          => __('Microblogs', 'microblog')
    );

    // Register the Microblog Custom Post Type
    register_post_type('microblog', array(
        'labels'        => $cpt_labels,
        'public'        => true,
        'has_archive'   => true,
        'supports'      => array('title', 'editor', 'author'),
        'show_in_rest'  => true,
        'menu_icon'     => 'dashicons-format-status',
        'rewrite'       => array('slug' => 'microblog'),
    ));

    // Define labels for the Microblog Category taxonomy
    $taxonomy_labels = array(
        'name'              => _x('Microblog Categories', 'taxonomy general name', 'microblog'),
        'singular_name'     => _x('Microblog Category', 'taxonomy singular name', 'microblog'),
        'search_items'      => __('Search Categories', 'microblog'),
        'all_items'         => __('All Categories', 'microblog'),
        'parent_item'       => __('Parent Category', 'microblog'),
        'parent_item_colon' => __('Parent Category:', 'microblog'),
        'edit_item'         => __('Edit Category', 'microblog'),
        'update_item'       => __('Update Category', 'microblog'),
        'add_new_item'      => __('Add New Category', 'microblog'),
        'new_item_name'     => __('New Category Name', 'microblog'),
        'menu_name'         => __('Categories', 'microblog'),
    );

    // Register the hierarchical taxonomy for microblog categories
    register_taxonomy('microblog_category', 'microblog', array(
        'hierarchical'      => true,
        'labels'            => $taxonomy_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'microblog-category'),
        'show_in_rest'      => true,
    ));

    // Ensure the default 'status' category exists
    microblog_ensure_default_category();
}
add_action('init', 'microblog_register_cpt_taxonomy');

/**
 * Ensure the default 'status' category exists
 *
 * Creates the default 'status' category if it doesn't already exist.
 * This category is used as a fallback when no category is specified.
 *
 * @since 1.0.0
 * @return void
 */
function microblog_ensure_default_category() {
    if (!term_exists('status', 'microblog_category')) {
        wp_insert_term(
            __('Status', 'microblog'), // Term name
            'microblog_category',       // Taxonomy
            array(
                'description' => __('Default category for microblog posts', 'microblog'),
                'slug'        => 'status',
            )
        );
    }
}

/**
 * Shortcode to display the microblog submission form
 *
 * This shortcode renders a form that allows users to submit new microblog posts.
 * The form includes a textarea for content and a dropdown for category selection.
 *
 * @since 1.0.0
 * @param array  $atts    Shortcode attributes (currently unused)
 * @param string $content Shortcode content (currently unused)
 * @return string HTML output for the microblog form
 */
function microblog_form_shortcode($atts, $content = null) {
    // Get all available microblog categories
    $categories = get_terms(array(
        'taxonomy'   => 'microblog_category',
        'orderby'    => 'name',
        'order'      => 'ASC',
        'hide_empty' => false
    ));

    // Get the default 'status' category
    $default_cat = get_term_by('slug', 'status', 'microblog_category');

    // Build the form HTML
    $html = '<div class="microblog-form-container">';
    
    // Add container for messages
    $html .= '<div id="microblog-messages" class="microblog-messages"></div>';
    
    $html .= '<form id="microblog-form" method="post">';
    
    // Textarea for microblog content with helpful placeholder
    $html .= '<textarea id="microblog-content" name="microblog_content" placeholder="' . 
             esc_attr__('(Write the Title into parenthesis)' . "\n" . 'Your #content. #hashtags become tags', 'microblog') . 
             '"></textarea>';
    
    // Category selection dropdown
    $html .= '<select name="microblog_category" id="microblog-category">';
    if (!is_wp_error($categories) && !empty($categories)) {
        foreach ($categories as $category) {
            $selected = ($default_cat && $category->term_id == $default_cat->term_id) ? 'selected' : '';
            $html .= '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . 
                     esc_html($category->name) . '</option>';
        }
    }
    $html .= '</select>';
    
    // Submit button
    $html .= '<input type="submit" value="' . esc_attr__('Submit', 'microblog') . '" />';
    
    // Security nonce field
    $html .= wp_nonce_field('microblog', 'microblog_nonce', true, false);
    $html .= '</form>';
    $html .= '</div>';
    
    return $html;
}
add_shortcode('microblog_form', 'microblog_form_shortcode');

/**
 * Shortcode to display a list of microblog posts
 *
 * This shortcode displays a list of microblog posts with optional filtering
 * by category and limiting the number of posts shown.
 *
 * @since 1.0.0
 * @param array $atts {
 *     Shortcode attributes.
 *     @type int    $number   Number of posts to display. Default 10.
 *     @type string $category Category slug to filter by. Default empty (all categories).
 * }
 * @return string HTML output for the microblog list
 */
function microblog_display_shortcode($atts) {
    // Parse shortcode attributes with defaults
    $atts = shortcode_atts(array(
        'number'   => 10,
        'category' => '',
    ), $atts, 'microblog_display');

    // Build query arguments
    $args = array(
        'post_type'      => 'microblog',
        'posts_per_page' => intval($atts['number']),
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish',
    );

    // Add taxonomy query if category is specified
    if (!empty($atts['category'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'microblog_category',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($atts['category']),
            ),
        );
    }

    // Execute the query
    $query = new WP_Query($args);

    // Build the output HTML
    if ($query->have_posts()) {
        $html = '<div class="microblog-list">';
        while ($query->have_posts()) {
            $query->the_post();
            $html .= '<div class="microblog-entry">';
            $html .= '<h4>' . esc_html(get_the_title()) . '</h4>';
            $html .= '<div class="microblog-content">' . wpautop(get_the_content()) . '</div>';
            
            // Display post metadata
            $html .= '<div class="microblog-meta">';
            $html .= '<small>' . 
                     sprintf(
                         /* translators: 1: date, 2: author name */
                         __('%1$s by %2$s', 'microblog'),
                         get_the_date(),
                         get_the_author()
                     ) . 
                     '</small>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        wp_reset_postdata();
    } else {
        $html = '<p>' . __('No microblogs found.', 'microblog') . '</p>';
    }

    return $html;
}
add_shortcode('microblog_display', 'microblog_display_shortcode');

/**
 * Handle AJAX submission for the Microblog form
 *
 * This function processes the AJAX request from the frontend form submission.
 * It validates the nonce, ensures the user is logged in, sanitizes the input,
 * extracts hashtags, and creates a new microblog post with the appropriate category.
 *
 * Note: The title extraction is now handled by JavaScript on the frontend,
 * so this function expects to receive the title and content separately.
 *
 * @since 1.0.0
 * @return void Outputs JSON response and terminates script execution
 */
function microblog_submit() {
    // Verify nonce for security
    if (!isset($_POST['microblog_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['microblog_nonce'])), 'microblog')) {
        wp_send_json_error(__('Security check failed. Please refresh the page and try again.', 'microblog'));
    }

    // Ensure user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(__('You must be logged in to submit a microblog post.', 'microblog'));
    }

    // Get and sanitize the content (title has already been processed by JavaScript)
    $content = isset($_POST['microblog_content']) ? wp_unslash($_POST['microblog_content']) : '';
    $content = trim($content);
    
    // Get the title (processed by JavaScript)
    $title = isset($_POST['microblog_title']) ? sanitize_text_field(wp_unslash($_POST['microblog_title'])) : '';

    // If no title provided, generate one from the content
    if (empty($title)) {
        $title = microblog_generate_title_from_content($content);
    }

    // Sanitize content while preserving basic formatting
    $content = wp_kses_post($content);

    // Validate that we have some content
    if (empty($content) && empty($title)) {
        wp_send_json_error(__('Please enter some content for your microblog post.', 'microblog'));
    }

    // Get category ID (taxonomy term ID)
    $category_id = isset($_POST['microblog_category']) ? intval($_POST['microblog_category']) : 0;

    // Extract hashtags from content for potential future use
    $hashtags = microblog_extract_hashtags($content);

    // Prepare post data
    $post_data = array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'microblog',
        'post_author'  => get_current_user_id(),
    );

    // Insert the microblog post
    $post_id = wp_insert_post($post_data);

    if ($post_id && !is_wp_error($post_id)) {
        // Assign category if valid
        if ($category_id && term_exists($category_id, 'microblog_category')) {
            wp_set_object_terms($post_id, array($category_id), 'microblog_category');
        } else {
            // Assign default 'status' category if no valid category provided
            $default_cat = get_term_by('slug', 'status', 'microblog_category');
            if ($default_cat && !is_wp_error($default_cat)) {
                wp_set_object_terms($post_id, array($default_cat->term_id), 'microblog_category');
            }
        }

        // Store hashtags as post meta for potential future use
        if (!empty($hashtags)) {
            update_post_meta($post_id, '_microblog_hashtags', $hashtags);
        }

        // Send success response
        wp_send_json_success(array(
            'post_id' => $post_id,
            'title'   => $title,
            'hashtags' => $hashtags,
            'message' => __('Microblog post created successfully!', 'microblog')
        ));
    } else {
        wp_send_json_error(__('An error occurred while creating the microblog post. Please try again.', 'microblog'));
    }
}
add_action('wp_ajax_microblog_submit', 'microblog_submit');
add_action('wp_ajax_nopriv_microblog_submit', 'microblog_submit');

/**
 * Generate a title from content if no title is provided
 *
 * This function creates a title by taking the first few words of the content,
 * stripping HTML tags, and limiting the length.
 *
 * @since 1.0.0
 * @param string $content The post content
 * @return string Generated title
 */
function microblog_generate_title_from_content($content) {
    // Remove HTML tags and get plain text
    $plain_text = wp_strip_all_tags($content);
    
    // Generate title from first 5 words
    $title = wp_trim_words($plain_text, 5, '...');
    
    // If still empty, provide a default
    if (empty($title)) {
        $title = __('Microblog Post', 'microblog') . ' - ' . current_time('H:i');
    }
    
    return $title;
}

/**
 * Extract hashtags from content
 *
 * This function finds all hashtags (words starting with #) in the content
 * and returns them as an array for potential future use.
 *
 * @since 1.0.0
 * @param string $content The content to search for hashtags
 * @return array Array of hashtags (without the # symbol)
 */
function microblog_extract_hashtags($content) {
    $hashtags = array();
    
    // Match hashtags with word characters (letters, digits, underscore)
    if (preg_match_all('/(?:^|\s)(#\w+)/u', $content, $matches)) {
        foreach ($matches[1] as $hashtag) {
            // Remove the # symbol and convert to lowercase
            $clean_hashtag = strtolower(substr($hashtag, 1));
            if (!in_array($clean_hashtag, $hashtags)) {
                $hashtags[] = $clean_hashtag;
            }
        }
    }
    
    return $hashtags;
}

/**
 * Plugin activation hook
 *
 * Runs when the plugin is activated to set up initial data and flush rewrite rules.
 *
 * @since 1.0.0
 * @return void
 */
function microblog_activate() {
    // Register post type and taxonomy
    microblog_register_cpt_taxonomy();
    
    // Flush rewrite rules to make sure custom post type URLs work
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'microblog_activate');

/**
 * Plugin deactivation hook
 *
 * Runs when the plugin is deactivated to clean up rewrite rules.
 *
 * @since 1.0.0
 * @return void
 */
function microblog_deactivate() {
    // Flush rewrite rules to remove custom post type URLs
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'microblog_deactivate');
