<?php
/*
Plugin Name: MicroBlog
Plugin URI: https://elica-webservices.it
Description: Adds a minimal front-end blogging form to your site giving it a microblog feel. Inspired by Narwhal Microblog
Version: 2.0
Requires CP: 1.0
Requires PHP: 8.1
Author: Elisabetta Carrara
Author URI: https://elica-webservices.it
License: GPL2
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Microblog custom post type.
 *
 * @since 2.0
 * @return void
 */
function microblog_post_type(): void {
    $labels = array(
        'name'                  => esc_html_x('Microblogs', 'Post Type General Name', 'microblog'),
        'singular_name'         => esc_html_x('Microblog', 'Post Type Singular Name', 'microblog'),
        'menu_name'             => esc_html__('Microblog', 'microblog'),
        'name_admin_bar'        => esc_html__('Microblog', 'microblog'),
        'archives'              => esc_html__('Item Archives', 'microblog'),
        'attributes'            => esc_html__('Item Attributes', 'microblog'),
        'parent_item_colon'     => esc_html__('Parent Item:', 'microblog'),
        'all_items'             => esc_html__('All Items', 'microblog'),
        'add_new_item'          => esc_html__('Add New Item', 'microblog'),
        'add_new'               => esc_html__('Add New', 'microblog'),
        'new_item'              => esc_html__('New Item', 'microblog'),
        'edit_item'             => esc_html__('Edit Item', 'microblog'),
        'update_item'           => esc_html__('Update Item', 'microblog'),
        'view_item'             => esc_html__('View Item', 'microblog'),
        'view_items'            => esc_html__('View Items', 'microblog'),
        'search_items'          => esc_html__('Search Item', 'microblog'),
        'not_found'             => esc_html__('Not found', 'microblog'),
        'not_found_in_trash'    => esc_html__('Not found in Trash', 'microblog'),
        'featured_image'        => esc_html__('Featured Image', 'microblog'),
        'set_featured_image'    => esc_html__('Set featured image', 'microblog'),
        'remove_featured_image' => esc_html__('Remove featured image', 'microblog'),
        'use_featured_image'    => esc_html__('Use as featured image', 'microblog'),
        'insert_into_item'      => esc_html__('Insert into item', 'microblog'),
        'uploaded_to_this_item' => esc_html__('Uploaded to this item', 'microblog'),
        'items_list'            => esc_html__('Items list', 'microblog'),
        'items_list_navigation' => esc_html__('Items list navigation', 'microblog'),
        'filter_items_list'     => esc_html__('Filter items list', 'microblog'),
    );

    $args = array(
        'label'                 => esc_html__('Microblog', 'microblog'),
        'description'           => esc_html__('Microblogging posts.', 'microblog'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'comments', 'thumbnail', 'custom-fields'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-sticky',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
    );

    register_post_type('microblog', $args);
}
add_action('init', 'microblog_post_type', 0);

/**
 * Register the Status taxonomy for Microblogs.
 *
 * @since 2.0
 * @return void
 */
function microblog_register_status_taxonomy(): void {
    $labels = array(
        'name'                       => esc_html_x('Statuses', 'Taxonomy General Name', 'microblog'),
        'singular_name'              => esc_html_x('Status', 'Taxonomy Singular Name', 'microblog'),
        'menu_name'                  => esc_html__('Statuses', 'microblog'),
        'all_items'                  => esc_html__('All Statuses', 'microblog'),
        'edit_item'                  => esc_html__('Edit Status', 'microblog'),
        'view_item'                  => esc_html__('View Status', 'microblog'),
        'update_item'                => esc_html__('Update Status', 'microblog'),
        'add_new_item'               => esc_html__('Add New Status', 'microblog'),
        'new_item_name'              => esc_html__('New Status Name', 'microblog'),
        'search_items'               => esc_html__('Search Statuses', 'microblog'),
        'not_found'                  => esc_html__('No statuses found', 'microblog'),
    );

    $args = array(
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => true,
    );

    register_taxonomy('status', array('microblog'), $args);
}
add_action('init', 'microblog_register_status_taxonomy', 0);

function microblog_add_meta_box() {
    add_meta_box(
        'microblog_files',
        __( 'Upload Files', 'microblog' ),
        'microblog_files_callback',
        'microblog'
    );
}
add_action( 'add_meta_boxes', 'microblog_add_meta_box' );

function microblog_files_callback( $post ) {
    wp_nonce_field( 'microblog_save_files_data', 'microblog_files_nonce' );
    $files = get_post_meta( $post->ID, '_microblog_uploaded_files', true );
    echo '<input type="file" name="microblog_files[]" multiple>';
    if ( ! empty( $files ) ) {
        echo '<ul>';
        foreach ( (array) $files as $file ) {
            echo '<li>' . esc_html( basename( $file ) ) . '</li>';
        }
        echo '</ul>';
    }
}

function microblog_save_files_data( $post_id ) {
    if ( ! isset( $_POST['microblog_files_nonce'] ) || ! wp_verify_nonce( $_POST['microblog_files_nonce'], 'microblog_save_files_data' ) ) {
        return;
    }

    if ( isset( $_FILES['microblog_files'] ) && ! empty( $_FILES['microblog_files']['name'][0]) ) {
        $uploaded_files = [];
        foreach ($_FILES['microblog_files']['name'] as $key => $value) {
            if ($_FILES['microblog_files']['name'][$key]) {
                $file = array(
                    'name'     => $_FILES['microblog_files']['name'][$key],
                    'type'     => $_FILES['microblog_files']['type'][$key],
                    'tmp_name' => $_FILES['microblog_files']['tmp_name'][$key],
                    'error'    => $_FILES['microblog_files']['error'][$key],
                    'size'     => $_FILES['microblog_files']['size'][$key]
                );

                // Upload file and get its URL
                $attachment_id = media_handle_upload($key, $post_id);
                if (!is_wp_error($attachment_id)) {
                    $uploaded_files[] = wp_get_attachment_url($attachment_id);
                }
            }
        }
        update_post_meta($post_id, '_microblog_uploaded_files', $uploaded_files);
    }
}
add_action('save_post', 'microblog_save_files_data');

function microblog_submission_form() {
    ob_start();
    ?>
    <form id="microblog-form" enctype="multipart/form-data">
        <label for="microblog-title"><?php _e('Title', 'microblog'); ?></label>
        <input type="text" id="microblog-title" name="microblog_title" required>

        <label for="microblog-content"><?php _e('Content', 'microblog'); ?></label>
        <?php wp_editor('', 'microblog-content', array('textarea_name' => 'microblog_content')); ?>

        <label for="microblog-category"><?php _e('Category', 'microblog'); ?></label>
        <select id="microblog-category" name="microblog_category">
            <?php
            $categories = get_terms(array('taxonomy' => 'status', 'hide_empty' => false));
            foreach ($categories as $category) {
                echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
            }
            ?>
        </select>

        <label for="microblog-files"><?php _e('Upload Files', 'microblog'); ?></label>
        <input type="file" id="microblog-files" name="microblog_files[]" multiple>

        <input type="hidden" name="author" value="<?php echo esc_attr(get_current_user_id()); ?>">
        <input type="hidden" name="date" value="<?php echo esc_attr(current_time('mysql')); ?>">

        <button type="submit"><?php _e('Submit Microblog', 'microblog'); ?></button>
    </form>
    <div id="upload-response"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('microblog_form', 'microblog_submission_form');

function microblog_handle_submission() {
    if (!isset($_POST['author']) || !isset($_POST['date'])) {
        wp_send_json_error(__('Invalid submission.', 'microblog'));
    }

    $post_data = array(
        'post_title'   => sanitize_text_field($_POST['microblog_title']),
        'post_content' => wp_kses_post($_POST['microblog_content']),
        'post_status'  => 'publish',
        'post_author'  => intval($_POST['author']),
        'post_date'    => sanitize_text_field($_POST['date']),
        'post_type'    => 'microblog',
    );

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        wp_send_json_error($post_id->get_error_message());
    }

    // Handle file uploads
    if (!empty($_FILES['microblog_files'])) {
        foreach ($_FILES['microblog_files']['name'] as $key => $value) {
            if ($_FILES['microblog_files']['error'][$key] == UPLOAD_ERR_OK) {
                $file = array(
                    'name'     => $_FILES['microblog_files']['name'][$key],
                    'type'     => $_FILES['microblog_files']['type'][$key],
                    'tmp_name' => $_FILES['microblog_files']['tmp_name'][$key],
                    'error'    => $_FILES['microblog_files']['error'][$key],
                    'size'     => $_FILES['microblog_files']['size'][$key]
                );

                // Upload file and associate with post
                $attachment_id = media_handle_upload($key, $post_id);
                if (is_wp_error($attachment_id)) {
                    wp_send_json_error($attachment_id->get_error_message());
                }
            }
        }
    }

    wp_send_json_success(__('Microblog submitted successfully.', 'microblog'));
}
add_action('wp_ajax_microblog_submit', 'microblog_handle_submission');

function microblog_loop_shortcode($atts) {
    // Query arguments
    $args = array(
        'post_type'      => 'microblog',
        'posts_per_page' => 10, // Adjust the number of posts displayed
        'orderby'        => 'date',
        'order'          => 'DESC'
    );

    $query = new WP_Query($args);

    ob_start(); // Start output buffering

    if ($query->have_posts()) {
        echo '<div class="microblog-posts">'; // Container for microblog posts

        while ($query->have_posts()) {
            $query->the_post();
            ?>
            <div class="microblog-post">
                <h2 class="microblog-title"><?php the_title(); ?></h2>
                <div class="microblog-content"><?php the_content(); ?></div>
                <div class="microblog-meta">
                    <span class="microblog-author"><?php echo get_the_author(); ?></span>
                    <span class="microblog-date"><?php echo get_the_date(); ?></span>
                </div>
                <?php if (has_post_thumbnail()) : ?>
                    <div class="microblog-thumbnail">
                        <?php the_post_thumbnail('medium'); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        echo '</div>'; // Close container
    } else {
        echo '<p>' . __('No microblogs found.', 'microblog') . '</p>';
    }

    wp_reset_postdata(); // Reset post data
    return ob_get_clean(); // Return buffered content
}
add_shortcode('microblog_loop', 'microblog_loop_shortcode');

/**
 * Add the settings page.
 */
function microblog_add_admin_menu(): void {
    add_options_page(
        esc_html__('MicroBlog Settings', 'microblog'),
        esc_html__('MicroBlog', 'microblog'),
        'manage_options',
        'microblog',
        'microblog_settings_page'
    );
}
add_action('admin_menu', 'microblog_add_admin_menu');

function microblog_register_settings(): void {
    register_setting('microblog_options_group', 'microblog_redirect_option', 'sanitize_text_field');
    register_setting('microblog_options_group', 'microblog_custom_redirect_url', 'esc_url_raw');
    register_setting('microblog_options_group', 'microblog_allowed_roles', function ($value) {
        return array_map('sanitize_text_field', (array) $value);
    });
}
add_action('admin_init', 'microblog_register_settings');

/**
 * Display the settings page.
 */
function microblog_settings_page(): void {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('MicroBlog Settings', 'microblog'); ?></h1>
        <form action="options.php" method="post">
            <?php settings_fields('microblog_options_group'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Redirect After Submission', 'microblog'); ?></th>
                    <td>
                        <select name="microblog_redirect_option">
                            <option value="homepage" <?php selected(get_option('microblog_redirect_option'), 'homepage'); ?>><?php esc_html_e('Homepage', 'microblog'); ?></option>
                            <option value="same_page" <?php selected(get_option('microblog_redirect_option'), 'same_page'); ?>><?php esc_html_e('Same Page with Thank You Message', 'microblog'); ?></option>
                            <option value="custom_url" <?php selected(get_option('microblog_redirect_option'), 'custom_url'); ?>><?php esc_html_e('Custom URL', 'microblog'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="custom-url-row" <?php echo get_option('microblog_redirect_option') === 'custom_url' ? '' : 'style="display:none;"'; ?>>
                    <th scope="row"><?php esc_html_e('Custom URL', 'microblog'); ?></th>
                    <td><input type="text" name="microblog_custom_redirect_url" value="<?php echo esc_attr(get_option('microblog_custom_redirect_url')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Allowed User Roles', 'microblog'); ?></th>
                    <td>
                        <?php $roles = wp_roles()->get_names(); ?>
                        <?php $allowed_roles = (array) get_option('microblog_allowed_roles'); ?>
                        <?php foreach ($roles as $role => $name): ?>
                            <label><input type="checkbox" name="microblog_allowed_roles[]" value="<?php echo esc_attr($role); ?>" <?php checked(in_array($role, $allowed_roles)); ?>> <?php echo esc_html($name); ?></label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
