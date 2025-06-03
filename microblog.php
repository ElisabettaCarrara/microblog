<?php
/*
Plugin Name: MicroBlog
Plugin URI: https://elica-webservices.it
Description: Adds a minimal front-end blogging form to your site giving it a microblog feel. Inspired by Narwhal Microblog
Version: 2.0
Requires CP: 2.0
Tested up to CP: 2.4.1
Requires PHP: 8.1
Author: Elisabetta Carrara
Author URI: https://elica-webservices.it
License: GPL2
*/

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'MICROBLOG_DIR', plugin_dir_path( __FILE__ ) );

// Require the migration file
require_once MICROBLOG_DIR . 'includes/migration.php';


/**
 * Main plugin class
 */
class Microblog_Plugin {

    /**
     * Plugin version
     *
     * @var string
     */
    const VERSION = '2.0.0';

    /**
     * Instance of this class
     *
     * @var Microblog_Plugin
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return Microblog_Plugin
     */
    public static function get_instance(): Microblog_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_microblog_upload_image', array( $this, 'handle_image_upload' ) );
        add_action( 'wp_ajax_microblog_submit_post', array( $this, 'handle_post_submission' ) );
        add_action( 'admin_init', array( $this, 'add_default_category' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }
    
    /**
     * Initialize plugin
     */
    public function init(): void {
        $this->register_post_type();
        $this->register_taxonomy();
        $this->register_shortcodes();
        load_plugin_textdomain( 'microblog', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Register custom post type
     */
    public function register_post_type(): void {
        $labels = array(
            'name'                  => _x( 'Microblog Posts', 'Post type general name', 'microblog' ),
            'singular_name'         => _x( 'Microblog Post', 'Post type singular name', 'microblog' ),
            'menu_name'             => _x( 'Microblog', 'Admin menu name', 'microblog' ),
            'name_admin_bar'        => _x( 'Microblog Post', 'Add new on admin bar', 'microblog' ),
            'add_new'               => _x( 'Add New', 'microblog post', 'microblog' ),
            'add_new_item'          => __( 'Add New Microblog Post', 'microblog' ),
            'new_item'              => __( 'New Microblog Post', 'microblog' ),
            'edit_item'             => __( 'Edit Microblog Post', 'microblog' ),
            'view_item'             => __( 'View Microblog Post', 'microblog' ),
            'all_items'             => __( 'All Microblog Posts', 'microblog' ),
            'search_items'          => __( 'Search Microblog Posts', 'microblog' ),
            'parent_item_colon'     => __( 'Parent Microblog Posts:', 'microblog' ),
            'not_found'             => __( 'No microblog posts found.', 'microblog' ),
            'not_found_in_trash'    => __( 'No microblog posts found in Trash.', 'microblog' ),
        );

        $args = array(
            'labels'                => $labels,
            'description'           => __( 'Microblog posts for quick sharing', 'microblog' ),
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'microblog' ),
            'capability_type'       => 'post',
            'has_archive'           => true,
            'hierarchical'          => false,
            'menu_position'         => null,
            'menu_icon'             => 'dashicons-format-status',
            'supports'              => array( 'title', 'editor', 'thumbnail', 'author', 'excerpt' ),
            'taxonomies'            => array( 'microblog_category' ),
        );

        register_post_type( 'microblog', $args );
    }

    /**
     * Register custom taxonomy
     */
    public function register_taxonomy(): void {
        $labels = array(
            'name'              => _x( 'Microblog Categories', 'taxonomy general name', 'microblog' ),
            'singular_name'     => _x( 'Microblog Category', 'taxonomy singular name', 'microblog' ),
            'search_items'      => __( 'Search Microblog Categories', 'microblog' ),
            'all_items'         => __( 'All Microblog Categories', 'microblog' ),
            'parent_item'       => __( 'Parent Microblog Category', 'microblog' ),
            'parent_item_colon' => __( 'Parent Microblog Category:', 'microblog' ),
            'edit_item'         => __( 'Edit Microblog Category', 'microblog' ),
            'update_item'       => __( 'Update Microblog Category', 'microblog' ),
            'add_new_item'      => __( 'Add New Microblog Category', 'microblog' ),
            'new_item_name'     => __( 'New Microblog Category Name', 'microblog' ),
            'menu_name'         => __( 'Categories', 'microblog' ),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'microblog-category' ),
            'public'            => true,
        );

        register_taxonomy( 'microblog_category', array( 'microblog' ), $args );
    }

    /**
     * Add default category
     */
    public function add_default_category(): void {
        if ( ! term_exists( 'Status', 'microblog_category' ) ) {
            wp_insert_term(
                'Status',
                'microblog_category',
                array(
                    'description' => __( 'Default microblog category for status updates', 'microblog' ),
                    'slug'        => 'status',
                )
            );
        }
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes(): void {
        add_shortcode( 'microblog_form', array( $this, 'render_form_shortcode' ) );
        add_shortcode( 'microblog_display', array( $this, 'render_display_shortcode' ) );
    }

    public function enqueue_scripts(): void {
    wp_enqueue_script(
        'microblog-js',
        plugin_dir_url(__FILE__) . 'microblog.js',
        array(),
        self::VERSION,
        true
    );
    wp_enqueue_style(
        'microblog-css',
        plugin_dir_url(__FILE__) . 'microblog.css',
        array(),
        self::VERSION
    );

    // Enqueue media scripts for frontend
    if (is_user_logged_in() && (is_singular() || is_page())) {
        global $post;
        if ($post && has_shortcode($post->post_content, 'microblog_form')) {
            wp_enqueue_media();
        }
    }

    // Safely get max file size from options
    $settings = get_option('microblog_settings');
    $max_file_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 5;

    wp_localize_script('microblog-js', 'microblog_ajax', array(
        'ajax_url'      => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('microblog_nonce'),
        'maxFileSizeMB' => $max_file_size,
        'l10n'          => array(
            'selectImageTitle'        => __('Select or Upload Image', 'microblog'),
            'useImageButton'          => __('Use This Image', 'microblog'),
            'invalidFileType'         => __('Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.', 'microblog'),
            'invalidFileTypeFallback' => __('Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.', 'microblog'),
            'fileTooLarge'            => __('File is too large. Maximum size is %s MB.', 'microblog'),
            'uploadingImage'          => __('Uploading image...', 'microblog'),
            'imageUploadedSuccess'    => __('Image uploaded successfully!', 'microblog'),
            'uploadFailed'            => __('Upload failed.', 'microblog'),
            'uploadError'             => __('Upload failed. Please try again.', 'microblog'),
            'changeImageButton'       => __('Change Image', 'microblog'),
            'chooseImageButton'       => __('Choose Image', 'microblog'),
            'imagePreviewAlt'         => __('Selected image preview', 'microblog'),
            'titleRequired'           => __('Title is required.', 'microblog'),
            'submitting'              => __('Submitting...', 'microblog'),
            'submittingPost'          => __('Submitting post...', 'microblog'),
            'postSubmittedSuccess'    => __('Post submitted successfully!', 'microblog'),
            'submissionFailed'        => __('Submission failed.', 'microblog'),
            'submissionError'         => __('Submission failed. Please try again.', 'microblog'),
            'submitButtonDefault'     => __('Submit Post', 'microblog'),
            'uploadHelpText'          => __('Supported formats: JPG, PNG, WebP, GIF. Maximum one image.', 'microblog'),
        ),
    ));
}

    /**
     * Render form shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_form_shortcode( $atts ): string {
        $atts = shortcode_atts( array(
            'redirect_after_submit' => '',
        ), $atts, 'microblog_form' );

        $options = get_option('microblog_settings');
        $allowed_roles = $options['allowed_roles'] ?? array( 'administrator' );
        $user = wp_get_current_user();
        $can_post = array_reduce( $user->roles, function ( $carry, $role ) use ( $allowed_roles ) {
            return $carry || in_array( $role, $allowed_roles, true );
        }, false );

        if ( ! $can_post ) {
            if ( ! is_user_logged_in() ) {
                return $this->render_login_prompt();
            } else {
                return '<p class="microblog-message microblog-error">' . esc_html__( 'You do not have permission to submit posts.', 'microblog' ) . '</p>';
            }
        }

        // Determine redirect URL
if ( empty( $atts['redirect_after_submit'] ) ) {
    $redirect_setting = $options['redirect_after_submit'] ?? 'current';
    if ( 'home' === $redirect_setting ) {
        $atts['redirect_after_submit'] = home_url( '/' );
    } elseif ( 'custom' === $redirect_setting && ! empty( $options['redirect_custom_url'] ) ) {
        $atts['redirect_after_submit'] = esc_url( $options['redirect_custom_url'] );
    } else { // 'current' or fallback
        $atts['redirect_after_submit'] = get_permalink();
    }
}

        return $this->render_microblog_form( $atts );
    }

    /**
     * Render login prompt
     *
     * @return string
     */
    private function render_login_prompt(): string {
        $login_url = wp_login_url( get_permalink() );
        $register_url = wp_registration_url();

        ob_start();
        ?>
        <div class="microblog-login-prompt">
            <p><?php esc_html_e( 'Please log in to share your microblog post.', 'microblog' ); ?></p>
            <p>
                <a href="<?php echo esc_url( $login_url ); ?>" class="microblog-login-link">
                    <?php esc_html_e( 'Log In', 'microblog' ); ?>
                </a>
                <?php if ( get_option( 'users_can_register' ) ) : ?>
                    <?php esc_html_e( ' or ', 'microblog' ); ?>
                    <a href="<?php echo esc_url( $register_url ); ?>" class="microblog-register-link">
                        <?php esc_html_e( 'Register', 'microblog' ); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render microblog form
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    private function render_microblog_form( $atts ): string {
        $categories = get_terms( array(
            'taxonomy'   => 'microblog_category',
            'hide_empty' => false,
        ) );

        $default_category_slug = get_option( 'microblog_settings' )['default_form_category'] ?? 'status';
        $default_category = get_term_by( 'slug', $default_category_slug, 'microblog_category' );

        ob_start();
        ?>
        <div class="microblog-form-container">
            <form id="microblog-form" class="microblog-form" data-redirect="<?php echo esc_attr( $atts['redirect_after_submit'] ); ?>">
                <?php wp_nonce_field( 'microblog_nonce', 'microblog_nonce_field' ); ?>

                <div class="microblog-field">
                    <label for="microblog-title"><?php esc_html_e( 'Title', 'microblog' ); ?></label>
                    <input type="text" id="microblog-title" name="microblog_title" required />
                </div>

                <div class="microblog-field">
                    <label for="microblog-content"><?php esc_html_e( 'Content', 'microblog' ); ?></label>
                    <?php
                    wp_editor( '', 'microblog_content', array(
                        'textarea_name' => 'microblog_content',
                        'textarea_rows' => 5,
                        'media_buttons' => false,
                        'teeny'         => true,
                        'tinymce'       => array(
                            'toolbar1' => 'bold,italic,underline,link,unlink,undo,redo',
                            'toolbar2' => '',
                        ),
                        'quicktags' => false,
                    ) );
                    ?>
                </div>

                <div class="microblog-field">
                    <label for="microblog-category"><?php esc_html_e( 'Category', 'microblog' ); ?></label>
                    <select id="microblog-category" name="microblog_category">
                        <?php foreach ( $categories as $category ) : ?>
                            <option value="<?php echo esc_attr( $category->term_id ); ?>" 
                                <?php selected( $default_category && $default_category->term_id === $category->term_id ); ?>>
                                <?php echo esc_html( $category->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="microblog-field">
                    <label><?php esc_html_e( 'Thumbnail', 'microblog' ); ?></label>
                    <div class="microblog-thumbnail-upload">
                        <button type="button" id="microblog-upload-btn" class="microblog-upload-button button">
                            <?php esc_html_e( 'Choose Image', 'microblog' ); ?>
                        </button>
                        <div id="microblog-image-preview" class="microblog-image-preview" style="display: none; margin-top: 10px;">
                            <img id="microblog-preview-img" src="" alt="<?php esc_attr_e( 'Preview', 'microblog' ); ?>" style="max-width: 150px; height: auto; border: 1px solid #ddd; padding: 5px;"/>
                            <button type="button" id="microblog-remove-image" class="microblog-remove-image button button-small" style="margin-left: 10px;">
                                <?php esc_html_e( 'Remove', 'microblog' ); ?>
                            </button>
                        </div>
                        <input type="hidden" id="microblog-thumbnail-id" name="microblog_thumbnail" />
                    </div>
                </div>

<div class="microblog-field">
                    <input type="text" name="microblog_hp" class="microblog-hp-field" autocomplete="off" tabindex="-1" aria-hidden="true">
                </div>
                
                <div class="microblog-field">
                    <button type="submit" id="microblog-submit-btn" class="microblog-submit-button button button-primary">
                        <?php esc_html_e( 'Submit Post', 'microblog' ); ?>
                    </button>
                </div>

                <div id="microblog-message" class="microblog-message" style="display: none; margin-top: 15px;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
/**
 * Handle image upload via AJAX.
 */
public function handle_image_upload(): void {
    // Unslash and sanitize nonce from $_POST.
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

    // Verify nonce for security.
    if ( ! wp_verify_nonce( $nonce, 'microblog_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'microblog' ) ), 403 );
        return;
    }

    // Ensure the user is logged in.
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'microblog' ) ), 401 );
        return;
    }

    // Fetch settings and validate user role.
    $options = get_option( 'microblog_settings', array() );
    $allowed_roles = isset( $options['allowed_roles'] ) && is_array( $options['allowed_roles'] ) ? $options['allowed_roles'] : array( 'administrator' );
    $user = wp_get_current_user();

    $can_post = array_reduce(
        $user->roles,
        static function ( $carry, $role ) use ( $allowed_roles ) {
            return $carry || in_array( $role, $allowed_roles, true );
        },
        false
    );

    if ( ! $can_post ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to upload images.', 'microblog' ) ), 403 );
        return;
    }

    // Unslash entire $_FILES array first.
    $files = wp_unslash( $_FILES );

    // Check if image file is provided (on unslashed data).
    if ( empty( $files['image'] ) ) {
        wp_send_json_error( array( 'message' => __( 'No image file provided.', 'microblog' ) ), 400 );
        return;
    }

    // Sanitize individual fields.
    $raw_file = $files['image'];

    $file = array(
        'name'     => sanitize_file_name( $raw_file['name'] ?? '' ),
        'type'     => sanitize_text_field( $raw_file['type'] ?? '' ),
        'tmp_name' => sanitize_text_field( $raw_file['tmp_name'] ?? '' ),
        'error'    => isset( $raw_file['error'] ) ? (int) $raw_file['error'] : UPLOAD_ERR_NO_FILE,
        'size'     => isset( $raw_file['size'] ) ? (int) $raw_file['size'] : 0,
    );

    // Check for upload errors before proceeding.
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        $upload_errors = array(
            UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'microblog' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.', 'microblog' ),
            UPLOAD_ERR_PARTIAL    => __( 'The uploaded file was only partially uploaded.', 'microblog' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'microblog' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Missing a temporary folder.', 'microblog' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'microblog' ),
            UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the file upload.', 'microblog' ),
        );

        $error_message = $upload_errors[ $file['error'] ] ?? __( 'Unknown upload error.', 'microblog' );

        wp_send_json_error( array( 'message' => $error_message ), 400 );
        return;
    }

    // Validate file type using wp_check_filetype().
    $allowed_types = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
    $file_type = wp_check_filetype( $file['name'] );

    if ( empty( $file_type['type'] ) || ! in_array( $file_type['type'], $allowed_types, true ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.', 'microblog' ) ), 400 );
        return;
    }

    // Validate file size.
    $max_file_size = isset( $options['max_file_size'] ) && is_numeric( $options['max_file_size'] ) ? (int) $options['max_file_size'] : 5; // Default 5 MB
    if ( $file['size'] > $max_file_size * 1024 * 1024 ) {
        wp_send_json_error( array( 'message' => sprintf( __( 'File is too large. Maximum size is %s MB.', 'microblog' ), esc_html( $max_file_size ) ) ), 400 );
        return;
    }

    // Ensure required WP functions are available.
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    if ( ! function_exists( 'media_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    // Upload the file.
    $upload_overrides = array( 'test_form' => false );
    $upload = wp_handle_upload( $file, $upload_overrides );

    // Check for upload errors.
    if ( isset( $upload['error'] ) ) {
        wp_send_json_error( array( 'message' => $upload['error'] ), 400 );
        return;
    }

    // Prepare the attachment array for the database.
    $attachment = array(
        'post_mime_type' => $upload['type'],
        'post_title'     => $file['name'],
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    // Insert the attachment into the database.
    $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

    // Check for WP error when creating the attachment.
    if ( is_wp_error( $attachment_id ) ) {
        wp_delete_file( $upload['file'] ); // Cleanup the file.
        wp_send_json_error( array( 'message' => __( 'Failed to create attachment: ', 'microblog' ) . $attachment_id->get_error_message() ), 500 );
        return;
    }

    // Generate and update attachment metadata.
    $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
    wp_update_attachment_metadata( $attachment_id, $attachment_data );

    // Retrieve the image URL.
    $image_url = wp_get_attachment_image_url( $attachment_id, 'medium' );

    // Return success response with message (FIXED: Added message for JS)
    wp_send_json_success( array(
        'attachment_id' => $attachment_id,
        'image_url'     => esc_url( $image_url ),
        'message'       => __( 'Image uploaded successfully!', 'microblog' ), // ADDED: This was missing
    ) );
}

    /**
     * Handle post submission via AJAX
     */
    public function handle_post_submission(): void {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'microblog_nonce' ) ) {
    wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'microblog' ) ), 403 );
    return;
}
        // Honeypot anti-spam check
if ( ! empty( $_POST['microblog_hp'] ) ) {
    wp_send_json_error(
        array( 'message' => __( 'Spam detected.', 'microblog' ) ),
        403
    );
    return;
}

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'You must be logged in to submit posts.', 'microblog' ) ), 401 );
        return;
    }

    $options = get_option('microblog_settings');
    $allowed_roles = $options['allowed_roles'] ?? array( 'administrator' );
    $user = wp_get_current_user();
    $can_post = array_reduce( $user->roles, function ( $carry, $role ) use ( $allowed_roles ) {
        return $carry || in_array( $role, $allowed_roles, true );
    }, false );
    
    if ( ! $can_post ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to submit posts.', 'microblog' ) ), 403 );
        return;
    }

    $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    $content      = isset( $_POST['content'] ) ? sanitize_text_field( wp_unslash( $_POST['content'] ) ) : '';
    $category_id  = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
    $thumbnail_id = isset( $_POST['thumbnail'] ) ? absint( $_POST['thumbnail'] ) : 0;

    if ( empty( $title ) ) {
        wp_send_json_error( array( 'message' => __( 'Title is required.', 'microblog' ) ) );
        return;
    }

    $char_limit = $options['character_limit'] ?? 0;
    if ( $char_limit > 0 && mb_strlen( strip_all_tags( $content ) ) > $char_limit ) {
        wp_send_json_error( array( 'message' => sprintf( __( 'Content exceeds character limit of %d.', 'microblog' ), $char_limit ) ) );
        return;
    }

    $post_data = array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_type'    => 'microblog',
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
    );

    $post_id = wp_insert_post( $post_data, true );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Failed to create post: ', 'microblog' ) . $post_id->get_error_message() ) );
        return;
    }

    if ( $category_id > 0 ) {
        $term = term_exists( $category_id, 'microblog_category' );
        if ( $term !== 0 && $term !== null ) {
            wp_set_post_terms( $post_id, array( $category_id ), 'microblog_category' );
        }
    } else {
        $default_category_slug = $options['default_form_category'] ?? 'status';
        $default_term = get_term_by( 'slug', $default_category_slug, 'microblog_category' );
        if ( $default_term ) {
            wp_set_post_terms( $post_id, array( $default_term->term_id ), 'microblog_category' );
        }
    }

    if ( $thumbnail_id > 0 && get_post_type( $thumbnail_id ) === 'attachment' ) {
        set_post_thumbnail( $post_id, $thumbnail_id );
    }

    // Get intended redirect URL from frontend (set by shortcode logic)
    $redirect_url = isset( $_POST['redirect_url'] ) 
    ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) 
    : get_permalink( $post_id );

$current_url = get_permalink();

    // If redirect URL is the current page, don't redirect, just show message
    $do_redirect = ($redirect_url !== $current_url);

    wp_send_json_success( array(
        'message' => __( 'Post submitted successfully!', 'microblog' ),
        'post_id' => $post_id,
        'redirect' => $do_redirect ? $redirect_url : '',
        'show_message' => !$do_redirect,
    ) );
}

    /**
 * Render display shortcode
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
public function render_display_shortcode( $atts ): string {
    $atts = shortcode_atts(
        array(
            'posts_per_page'   => get_option( 'microblog_settings' )['posts_per_page_display'] ?? 10,
            'category'         => '',
            'order'            => 'DESC',
            'orderby'          => 'date',
            'show_pagination'  => get_option( 'microblog_settings' )['show_pagination_display'] ?? 'yes',
            'show_markup'      => 'true', // New attribute, default true
        ),
        $atts,
        'microblog_display'
    );

    // Sanitize show_markup to boolean
    $show_markup = filter_var( $atts['show_markup'], FILTER_VALIDATE_BOOLEAN );

    $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

    $args = array(
        'post_type'      => 'microblog',
        'posts_per_page' => absint( $atts['posts_per_page'] ),
        'post_status'    => 'publish',
        'order'          => in_array( strtoupper( $atts['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $atts['order'] ) : 'DESC',
        'orderby'        => sanitize_key( $atts['orderby'] ),
        'paged'          => $paged,
    );

    if ( ! empty( $atts['category'] ) ) {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'microblog_category',
                'field'    => 'slug',
                'terms'    => array_map( 'sanitize_title', explode( ',', $atts['category'] ) ),
            ),
        );
    }

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return '<p class="microblog-no-posts">' . esc_html__( 'No microblog posts found.', 'microblog' ) . '</p>';
    }

    ob_start();
    ?>
    <div class="microblog-display">
        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
            <?php if ( $show_markup ) : ?>
                <!-- Rich markup version -->
                <article id="microblog-post-<?php the_ID(); ?>" <?php post_class( 'microblog-post' ); ?>>
                    <header class="microblog-post-header">
                        <h3 class="microblog-post-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>
                        <div class="microblog-post-meta">
                            <span class="microblog-author">
                                <?php printf( esc_html__( 'by %s', 'microblog' ), '<a href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a>' ); ?>
                            </span>
                            <span class="microblog-date"><?php echo esc_html( get_the_date() ); ?></span>
                            <?php if ( get_the_modified_date() !== get_the_date() ) : ?>
                                <span class="microblog-modified-date">(<?php printf( esc_html__( 'edited %s', 'microblog' ), esc_html( get_the_modified_date() ) ); ?>)</span>
                            <?php endif; ?>
                        </div>
                    </header>

                    <div class="microblog-post-content">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <div class="microblog-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail( 'medium' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="microblog-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                        <a href="<?php the_permalink(); ?>" class="microblog-read-more"><?php esc_html_e( 'Read More &rarr;', 'microblog' ); ?></a>
                    </div>

                    <footer class="microblog-post-footer">
                        <?php
                        $categories_list = get_the_term_list( get_the_ID(), 'microblog_category', '<span class="microblog-categories-label">' . esc_html__( 'Categories:', 'microblog' ) . '</span> ', ', ', '' );
                        if ( $categories_list && ! is_wp_error( $categories_list ) ) :
                        ?>
                        <div class="microblog-categories">
                            <?php echo wp_kses_post( $categories_list ); ?>
                        </div>
                        <?php endif; ?>
                    </footer>
                </article>
            <?php else : ?>
                <!-- Minimal markup version -->
                <div>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <a href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail( 'medium' ); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </div>
            <?php endif; ?>
        <?php endwhile; ?>
    </div>

    <?php if ( 'yes' === $atts['show_pagination'] && $query->max_num_pages > 1 ) : ?>
        <nav class="microblog-pagination">
            <?php
            echo wp_kses_post( paginate_links( array(
                'base'      => str_replace( PHP_INT_MAX, '%#%', esc_url( get_pagenum_link( PHP_INT_MAX ) ) ),
                'format'    => '?paged=%#%',
                'current'   => max( 1, $paged ),
                'total'     => $query->max_num_pages,
                'prev_text' => __( '&laquo; Previous', 'microblog' ),
                'next_text' => __( 'Next &raquo;', 'microblog' ),
            ) ) );
            ?>
        </nav>
    <?php endif; ?>

    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
    
    /**
     * Add admin menu pages
     */
    public function add_admin_pages(): void {
        add_menu_page(
            __( 'MicroBlog Settings', 'microblog' ),
            __( 'MicroBlog', 'microblog' ),
            'manage_options',
            'microblog-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-admin-settings',
            30
        );

        add_submenu_page(
            'microblog-settings',
            __( 'Settings', 'microblog' ),
            __( 'Settings', 'microblog' ),
            'manage_options',
            'microblog-settings'
        );

        add_submenu_page(
            'microblog-settings',
            __( 'How to Use - MicroBlog', 'microblog' ),
            __( 'How to Use', 'microblog' ),
            'manage_options',
            'microblog-docs',
            array( $this, 'render_docs_page' )
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'MicroBlog Settings', 'microblog' ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'microblog_settings_group' );
                do_settings_sections( 'microblog-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
 * Register plugin settings
 */
public function register_settings(): void {
    register_setting( 'microblog_settings_group', 'microblog_settings', array(
        'sanitize_callback' => array( $this, 'sanitize_settings' ),
        'default' => array( 
            'redirect_after_submit' => 'display',
            'redirect_custom_url' => '',
            'allowed_roles' => array( 'administrator' ),
            'default_form_category' => 'status',
            'posts_per_page_display' => 10,
            'show_pagination_display' => 'yes',
            'character_limit' => 0,
            'max_file_size' => 5,
        )
    ) );

    // General Section
    add_settings_section(
        'microblog_general_section',
        __( 'General Settings', 'microblog' ),
        null, 
        'microblog-settings'
    );

    add_settings_field(
        'default_form_category',
        __( 'Default Form Category', 'microblog' ),
        array( $this, 'render_default_category_field' ),
        'microblog-settings',
        'microblog_general_section'
    );

    add_settings_field(
        'character_limit',
        __( 'Post Content Character Limit', 'microblog' ),
        array( $this, 'render_character_limit_field' ),
        'microblog-settings',
        'microblog_general_section'
    );

    add_settings_field(
        'max_file_size',
        __( 'Max Image Upload Size (MB)', 'microblog' ),
        array( $this, 'render_max_file_size_field' ),
        'microblog-settings',
        'microblog_general_section'
    );

    // Redirect After Submission Section
    add_settings_section(
        'microblog_redirect_section',
        __( 'Post Submission Redirect', 'microblog' ),
        null,
        'microblog-settings'
    );

    add_settings_field(
        'redirect_after_submit',
        __( 'Redirect After Submission To', 'microblog' ),
        array( $this, 'render_redirect_field' ),
        'microblog-settings',
        'microblog_redirect_section'
    );

    add_settings_field(
        'redirect_custom_url_field',
        __( 'Custom Redirect URL', 'microblog' ),
        array( $this, 'render_redirect_custom_url_field' ),
        'microblog-settings',
        'microblog_redirect_section'
    );

    // User Roles Section
    add_settings_section(
        'microblog_roles_section',
        __( 'User Permissions', 'microblog' ),
        null,
        'microblog-settings'
    );

    add_settings_field(
        'allowed_roles',
        __( 'Allowed Roles to Post', 'microblog' ),
        array( $this, 'render_roles_field' ),
        'microblog-settings',
        'microblog_roles_section'
    );

    // Display Settings Section
    add_settings_section(
        'microblog_display_section',
        __( 'Posts Display Settings', 'microblog' ),
        null,
        'microblog-settings'
    );

    add_settings_field(
        'posts_per_page_display',
        __( 'Posts Per Page (Display Shortcode)', 'microblog' ),
        array( $this, 'render_posts_per_page_field' ),
        'microblog-settings',
        'microblog_display_section'
    );

    add_settings_field(
        'show_pagination_display',
        __( 'Show Pagination (Display Shortcode)', 'microblog' ),
        array( $this, 'render_show_pagination_field' ),
        'microblog-settings',
        'microblog_display_section'
    );
}

public function render_default_category_field(): void {
    $options = get_option( 'microblog_settings' );
    $current_slug = $options['default_form_category'] ?? 'status';
    $categories = get_terms( array( 'taxonomy' => 'microblog_category', 'hide_empty' => false ) );
    ?>
    <select name="microblog_settings[default_form_category]">
        <?php foreach ( $categories as $category ) : ?>
            <option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $current_slug, $category->slug ); ?>>
                <?php echo esc_html( $category->name ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description"><?php esc_html_e( 'Select the default category for new posts submitted via the form.', 'microblog' ); ?></p>
    <?php
}

public function render_character_limit_field(): void {
    $options = get_option( 'microblog_settings' );
    $limit = $options['character_limit'] ?? 0;
    ?>
    <input type="number" name="microblog_settings[character_limit]" value="<?php echo esc_attr( $limit ); ?>" min="0" step="1" />
    <p class="description"><?php esc_html_e( 'Maximum number of characters allowed for post content. Set to 0 for no limit.', 'microblog' ); ?></p>
    <?php
}

public function render_max_file_size_field(): void {
    $options = get_option( 'microblog_settings' );
    $size = $options['max_file_size'] ?? 5; 
    ?>
    <input type="number" name="microblog_settings[max_file_size]" value="<?php echo esc_attr( $size ); ?>" min="1" step="1" />
    <p class="description"><?php esc_html_e( 'Maximum file size in Megabytes (MB) for image uploads.', 'microblog' ); ?></p>
    <?php
}

public function render_redirect_field(): void {
    $options = get_option( 'microblog_settings' );
    $current = $options['redirect_after_submit'] ?? 'display';
    ?>
    <select id="microblog_redirect_after_submit" name="microblog_settings[redirect_after_submit]">
        <option value="home" <?php selected( $current, 'home' ); ?>>
            <?php esc_html_e( 'Home Page', 'microblog' ); ?>
        </option>
        <option value="custom" <?php selected( $current, 'custom' ); ?>>
            <?php esc_html_e( 'Custom URL (set below)', 'microblog' ); ?>
        </option>
        <option value="current" <?php selected( $current, 'current' ); ?>>
            <?php esc_html_e( 'Current Page (show success message, no redirect)', 'microblog' ); ?>
        </option>
    </select>
    <p class="description">
        <?php esc_html_e(
            'Choose where to redirect the user after successfully submitting a post. For "Custom URL" enter the page address below (e.g., the page with the [microblog_display] shortcode). "Current Page" will reload this page and show a success message.',
            'microblog'
        ); ?>
    </p>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const redirectSelect = document.getElementById('microblog_redirect_after_submit');
            const customUrlWrapper = document.getElementById('microblog_custom_url_field_wrapper');
            
            function toggleCustomUrlField() {
                if (redirectSelect && customUrlWrapper) {
                    if (redirectSelect.value === 'custom') {
                        customUrlWrapper.style.display = 'block';
                    } else {
                        customUrlWrapper.style.display = 'none';
                    }
                }
            }
            
            // Initial check
            toggleCustomUrlField();
            
            // Add event listener for changes
            if (redirectSelect) {
                redirectSelect.addEventListener('change', toggleCustomUrlField);
            }
        });
    </script>
    <?php
}

public function render_redirect_custom_url_field(): void {
    $options = get_option( 'microblog_settings' );
    $url = $options['redirect_custom_url'] ?? '';
    ?>
    <div id="microblog_custom_url_field_wrapper">
        <input type="url" name="microblog_settings[redirect_custom_url]" value="<?php echo esc_attr( $url ); ?>" class="regular-text" placeholder="https://example.com/microblog-page" />
        <p class="description"><?php esc_html_e( 'Enter the full URL where users should be redirected after successfully submitting a post.', 'microblog' ); ?></p>
    </div>
    <?php
}

public function render_roles_field(): void {
    $options = get_option( 'microblog_settings' );
    $selected_roles = $options['allowed_roles'] ?? array( 'administrator' ); 
    $roles = get_editable_roles(); 
    
    foreach ( $roles as $role_slug => $details ) : ?>
        <label>
            <input type="checkbox" name="microblog_settings[allowed_roles][]" 
                   value="<?php echo esc_attr( $role_slug ); ?>"
                   <?php checked( in_array( $role_slug, $selected_roles, true ) ); ?>>
            <?php echo esc_html( $details['name'] ); ?>
        </label><br>
    <?php endforeach;
    echo '<p class="description">' . esc_html__( 'Select user roles that are allowed to submit microblog posts using the frontend form.', 'microblog' ) . '</p>';
}

public function render_posts_per_page_field(): void {
    $options = get_option( 'microblog_settings' );
    $value = $options['posts_per_page_display'] ?? 10;
    ?>
    <input type="number" name="microblog_settings[posts_per_page_display]" value="<?php echo esc_attr( $value ); ?>" min="1" class="small-text" />
    <p class="description"><?php esc_html_e( 'Default number of posts to show per page for the [microblog_display] shortcode.', 'microblog' ); ?></p>
    <?php
}

public function render_show_pagination_field(): void {
    $options = get_option( 'microblog_settings' );
    $value = $options['show_pagination_display'] ?? 'yes';
    ?>
    <select name="microblog_settings[show_pagination_display]">
        <option value="yes" <?php selected( $value, 'yes' ); ?>><?php esc_html_e( 'Yes', 'microblog' ); ?></option>
        <option value="no" <?php selected( $value, 'no' ); ?>><?php esc_html_e( 'No', 'microblog' ); ?></option>
    </select>
    <p class="description"><?php esc_html_e( 'Whether to show pagination for the [microblog_display] shortcode if there are multiple pages of posts.', 'microblog' ); ?></p>
    <?php
}

/**
 * Sanitize settings
 *
 * @param array $input Raw input from settings form.
 * @return array Sanitized settings.
 */
public function sanitize_settings( $input ): array {
    $clean_input = array();
    $default_settings = $this->get_default_settings();

    // Sanitize redirect_after_submit
    $redirect_options = array( 'display', 'home', 'custom', 'current' );
    $clean_input['redirect_after_submit'] = in_array( $input['redirect_after_submit'] ?? 'display', $redirect_options, true ) 
        ? $input['redirect_after_submit'] 
        : $default_settings['redirect_after_submit'];

    // Sanitize redirect_custom_url (only if 'custom' is chosen)
    if ( 'custom' === $clean_input['redirect_after_submit'] ) {
        $clean_input['redirect_custom_url'] = ! empty( $input['redirect_custom_url'] ) ? esc_url_raw( $input['redirect_custom_url'] ) : '';
    } else {
        $clean_input['redirect_custom_url'] = ''; 
    }

    // Sanitize allowed_roles
    $clean_input['allowed_roles'] = array();
    $editable_roles = array_keys( get_editable_roles() );
    if ( ! empty( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
        foreach ( $input['allowed_roles'] as $role ) {
            if ( in_array( $role, $editable_roles, true ) ) {
                $clean_input['allowed_roles'][] = $role;
            }
        }
    }
    // Ensure at least one role is selected, default to administrator if empty
    if ( empty( $clean_input['allowed_roles'] ) ) {
        $clean_input['allowed_roles'] = $default_settings['allowed_roles'];
    }

    // Sanitize default_form_category
    $clean_input['default_form_category'] = isset($input['default_form_category']) ? sanitize_key($input['default_form_category']) : $default_settings['default_form_category'];
    if ( ! term_exists( $clean_input['default_form_category'], 'microblog_category' ) ) {
        $clean_input['default_form_category'] = $default_settings['default_form_category']; 
    }

    // Sanitize posts_per_page_display
    $clean_input['posts_per_page_display'] = isset( $input['posts_per_page_display'] ) ? absint( $input['posts_per_page_display'] ) : $default_settings['posts_per_page_display'];
    if ( $clean_input['posts_per_page_display'] < 1 ) { 
        $clean_input['posts_per_page_display'] = $default_settings['posts_per_page_display'];
    }

    // Sanitize show_pagination_display
    $clean_input['show_pagination_display'] = isset( $input['show_pagination_display'] ) && in_array( $input['show_pagination_display'], array( 'yes', 'no' ) ) ? $input['show_pagination_display'] : $default_settings['show_pagination_display'];

    // Sanitize character_limit
    $clean_input['character_limit'] = isset( $input['character_limit'] ) ? absint( $input['character_limit'] ) : $default_settings['character_limit'];

    // Sanitize max_file_size
    $clean_input['max_file_size'] = isset( $input['max_file_size'] ) ? absint( $input['max_file_size'] ) : $default_settings['max_file_size'];
    if ( $clean_input['max_file_size'] < 1 ) {
        $clean_input['max_file_size'] = $default_settings['max_file_size'];
    }

    return $clean_input;
}

private function get_default_settings(): array {
    return array(
        'redirect_after_submit' => 'display',
        'redirect_custom_url' => '',
        'allowed_roles' => array( 'administrator' ),
        'default_form_category' => 'status',
        'posts_per_page_display' => 10,
        'show_pagination_display' => 'yes',
        'character_limit' => 0,
        'max_file_size' => 5,
    );
}

    /**
     * Render documentation page
     */
    public function render_docs_page(): void {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Microblog Plugin Documentation', 'microblog' ); ?></h1>

        <div class="card">
            <h2 class="title"><?php esc_html_e( 'Overview', 'microblog' ); ?></h2>
            <p><?php esc_html_e( 'Microblog enables users to quickly share short posts, status updates, or thoughts directly from the front-end.', 'microblog' ); ?></p>
        </div>

        <div class="card">
            <h2 class="title"><?php esc_html_e( 'Shortcodes', 'microblog' ); ?></h2>

            <h3><?php esc_html_e( 'Submission Form', 'microblog' ); ?>: <code>[microblog_form]</code></h3>
            <p><?php esc_html_e( 'Displays a front-end form allowing users to submit new microblog posts.', 'microblog' ); ?></p>
            <p><strong><?php esc_html_e( 'Parameters:', 'microblog' ); ?></strong></p>
            <ul>
                <li>
                    <code>redirect_after_submit</code>: <?php esc_html_e( 'Overrides the global redirect setting. Options:', 'microblog' ); ?>
                    <ul>
                        <li><code>home</code>: <?php esc_html_e( 'Redirects to the site\'s homepage after submission.', 'microblog' ); ?></li>
                        <li><code>current</code>: <?php esc_html_e( 'Redirects to the current page containing the form.', 'microblog' ); ?></li>
                        <li><em>custom_url</em>: <?php esc_html_e( 'Redirects to a custom URL. Provide a full URL like "https://example.com/thank-you".', 'microblog' ); ?></li>
                    </ul>
                    <?php esc_html_e( 'Example:', 'microblog' ); ?> <code>[microblog_form redirect_after_submit="home"]</code>
                </li>
            </ul>

            <h3><?php esc_html_e( 'Posts Display', 'microblog' ); ?>: <code>[microblog_display]</code></h3>
            <p><?php esc_html_e( 'Displays a list of microblog posts.', 'microblog' ); ?></p>
            <p><strong><?php esc_html_e( 'Parameters:', 'microblog' ); ?></strong></p>
            <ul>
                <li><code>posts_per_page</code>: <?php esc_html_e( 'Number of posts to display per page.', 'microblog' ); ?></li>
                <li><code>category</code>: <?php esc_html_e( 'Filter posts by one or more category slugs (comma-separated).', 'microblog' ); ?></li>
                <li><code>order</code>: <?php esc_html_e( 'Sort order of posts. Accepts "ASC" or "DESC". Defaults to "DESC".', 'microblog' ); ?></li>
                <li><code>orderby</code>: <?php esc_html_e( 'Field to order posts by (e.g., date, title). Defaults to "date".', 'microblog' ); ?></li>
                <li><code>show_pagination</code>: <?php esc_html_e( 'Whether to show pagination links ("yes" or "no"). Defaults to plugin settings.', 'microblog' ); ?></li>
                <li><code>show_markup</code>: <?php esc_html_e( 'Display full HTML markup ("true") or minimal output ("false"). Defaults to "true".', 'microblog' ); ?></li>
            </ul>
            <?php esc_html_e( 'Example:', 'microblog' ); ?> <code>[microblog_display posts_per_page="5" category="news" orderby="title" order="ASC" show_markup="false"]</code>
        </div>

        <div class="card">
            <h2 class="title"><?php esc_html_e( 'Settings', 'microblog' ); ?></h2>
            <p><?php esc_html_e( 'Configure Microblog options under the "Microblog" > "Settings" menu in the WordPress admin.', 'microblog' ); ?></p>
            <ul>
                <li><?php esc_html_e( 'Default category assigned to new posts.', 'microblog' ); ?></li>
                <li><?php esc_html_e( 'Character limit for post content.', 'microblog' ); ?></li>
                <li><?php esc_html_e( 'Maximum allowed image upload size.', 'microblog' ); ?></li>
                <li><?php esc_html_e( 'Redirect behavior after post submission.', 'microblog' ); ?></li>
                <li><?php esc_html_e( 'User roles allowed to submit posts.', 'microblog' ); ?></li>
                <li><?php esc_html_e( 'Default number of posts per page for display.', 'microblog' ); ?></li>
                <li><?php esc_html_e( 'Default pagination visibility.', 'microblog' ); ?></li>
            </ul>
        </div>

        <div class="card">
            <h2 class="title"><?php esc_html_e( 'Image Uploads', 'microblog' ); ?></h2>
            <p><?php esc_html_e( 'Users can upload images with their posts. Uploaded images are set as featured images.', 'microblog' ); ?></p>
        </div>

        <div class="card">
            <h2 class="title"><?php esc_html_e( 'Anti-Spam Protection', 'microblog' ); ?></h2>
            <p><?php esc_html_e( 'The submission form includes a hidden honeypot field to help prevent spam bots.', 'microblog' ); ?></p>
        </div>

        <div class="card">
            <h2 class="title"><?php esc_html_e( 'Styling', 'microblog' ); ?></h2>
            <p><?php esc_html_e( 'The plugin includes a basic stylesheet (microblog.css). You can override these styles in your theme.', 'microblog' ); ?></p>
            <ul>
                <li><code>.microblog-form-container</code>, <code>.microblog-form</code>, <code>.microblog-field</code></li>
                <li><code>.microblog-display</code>, <code>.microblog-post</code>, <code>.microblog-post-title</code></li>
                <li><code>.microblog-login-prompt</code>, <code>.microblog-message</code></li>
            </ul>
        </div>

        <div class="card">
            <h2 class="title"><?php esc_html_e( 'Troubleshooting', 'microblog' ); ?></h2>
            <ul>
                <li><?php esc_html_e( 'Image upload issues: Check user permissions and file size/type restrictions.', 'microblog' ); ?></li>
                <li><?php esc_html_e( 'Shortcodes not working: Ensure the plugin is active and shortcodes are used correctly.', 'microblog' ); ?></li>
                <li><?php esc_html_e( 'Categories displaying raw HTML: Make sure your theme uses proper escaping like wp_kses_post() when outputting categories.', 'microblog' ); ?></li>
            </ul>
        </div>

        <div class="card">
            <h2 class="title"><?php esc_html_e( 'Support & Resources', 'microblog' ); ?></h2>
            <p>
                <?php
                /* translators: %s: GitHub repository URL */
                printf(
                    wp_kses(
                        /* translators: Link to GitHub repo */
                        __( 'For updates, bug reports, and feature requests, visit the <a href="%s" target="_blank" rel="noopener noreferrer">GitHub repository</a>.', 'microblog' ),
                        array(
                            'a' => array(
                                'href'   => array(),
                                'target' => array(),
                                'rel'    => array(),
                            ),
                        )
                    ),
                    esc_url( 'https://github.com/ElisabettaCarrara/microblog' )
                );
                ?>
            </p>
        </div>

        <style>
            .wrap .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 1em 1.5em;
                margin-bottom: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                border-radius: 4px;
            }
            .wrap .card h2.title {
                font-size: 1.5em;
                margin-bottom: 0.5em;
                border-bottom: 1px solid #eee;
                padding-bottom: 0.3em;
            }
            .wrap .card h3 {
                font-size: 1.2em;
                margin-top: 1.5em;
            }
            .wrap .card code {
                background: #f5f5f5;
                padding: 2px 5px;
                border-radius: 3px;
                font-family: monospace, monospace;
            }
            .wrap .card ul {
                list-style: disc;
                margin-left: 20px;
            }
            .wrap .card ul ul {
                list-style: circle;
                margin-left: 20px;
            }
        </style>
    </div>
    <?php
}
} // End of Microblog_Plugin class

// Initialize the plugin.
Microblog_Plugin::get_instance();
