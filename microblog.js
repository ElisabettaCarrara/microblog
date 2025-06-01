/**
 * Microblog Frontend JavaScript
 * Handles form submission, image upload, and media library integration (Vanilla JS)
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // --- DOM Element References ---
    const microblogForm = document.getElementById('microblog-form');
    const uploadBtn = document.getElementById('microblog-upload-btn');
    const imagePreviewDiv = document.getElementById('microblog-image-preview'); // Renamed for clarity
    const previewImg = document.getElementById('microblog-preview-img');
    const removeImageBtn = document.getElementById('microblog-remove-image');
    const thumbnailIdInput = document.getElementById('microblog-thumbnail-id');
    const messageDiv = document.getElementById('microblog-message');
    const titleInput = document.getElementById('microblog-title');
    const categorySelect = document.getElementById('microblog-category');
    const contentTextarea = document.getElementById('microblog_content'); // For fallback if TinyMCE not loaded
    
    let mediaFrame = null; // For WordPress media uploader

    // --- Initialization ---

    // Initialize media uploader (WP Media Library or fallback)
    if (uploadBtn) { // Only initialize if the upload button exists
        if (typeof wp !== 'undefined' && wp.media && typeof wp.media.featuredImage !== 'undefined') { // Check for featuredImage too for robustness
            initWPMediaUploader();
        } else {
            initFallbackUploader();
        }
    }
    
    // Initialize form submission handler
    if (microblogForm) {
        microblogForm.addEventListener('submit', handleFormSubmission);
    }

    // Initialize image removal functionality
    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', handleRemoveImage);
        // Set proper accessibility attributes
        removeImageBtn.setAttribute('aria-label', 'Remove selected image');
        removeImageBtn.setAttribute('title', 'Remove image');
    }

    // Initialize oEmbed-like support (primarily relies on TinyMCE/WordPress server-side)
    initOEmbedSupport();
    
    // Initialize dynamic form validation hints
    initFormValidation();
    
    // Initialize responsive image preview adjustments
    initResponsivePreview();
    
    // Initialize accessibility enhancements
    initAccessibility();

    // --- WordPress Media Uploader Functions ---
    
    /**
     * Initialize WordPress media uploader
     */
    function initWPMediaUploader() {
        uploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Create media frame if it doesn't exist
            if (mediaFrame) {
                mediaFrame.open();
                return;
            }
            
            mediaFrame = wp.media({
    title: (microblog_ajax.l10n && typeof microblog_ajax.l10n.selectImageTitle !== 'undefined') ? microblog_ajax.l10n.selectImageTitle : 'Select Image',
    button: {
        text: (microblog_ajax.l10n && typeof microblog_ajax.l10n.useImageButton !== 'undefined') ? microblog_ajax.l10n.useImageButton : 'Use this image'
    },
    multiple: false,
    library: {
        type: ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
    }
});
            
            // Handle image selection
            mediaFrame.on('select', function() {
                const attachment = mediaFrame.state().get('selection').first().toJSON();
                
                if (isValidImageType(attachment.mime)) {
                    const imageUrl = attachment.sizes?.medium?.url || attachment.sizes?.thumbnail?.url || attachment.url;
                    setSelectedImage(attachment.id, imageUrl);
                } else {
                    showMessage(microblog_ajax.l10n?.invalidFileType || 'Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.', 'error');
                }
            });
            
            mediaFrame.open();
        });
    }
    
    // --- Fallback Uploader Functions ---

    /**
     * Initialize fallback uploader for environments without wp.media
     */
    function initFallbackUploader() {
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = '.jpg,.jpeg,.png,.webp,.gif'; // Added .gif
        fileInput.style.display = 'none';
        // Check if document.body exists before appending, though it should in DOMContentLoaded
        if (document.body) {
            document.body.appendChild(fileInput);
        } else {
            console.error("MicroBlog JS: document.body not found for fallback uploader.");
            return;
        }
        
        uploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fileInput.click();
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                if (!isValidImageFile(file)) {
                    showMessage(microblog_ajax.l10n?.invalidFileTypeFallback || 'Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.', 'error');
                    this.value = ''; // Reset file input
                    return;
                }
                // Check file size (client-side hint, server validates)
                const maxFileSizeMB = microblog_ajax.maxFileSizeMB || 5; // Get from localized data if set
                if (file.size > maxFileSizeMB * 1024 * 1024) {
                    showMessage(
                        (microblog_ajax.l10n?.fileTooLarge || 'File is too large. Maximum size is %s MB.').replace('%s', maxFileSizeMB),
                        'error'
                    );
                    this.value = ''; // Reset file input
                    return;
                }
                
                uploadImageFileViaAjax(file);
            }
        });
    }
    
    /**
     * Upload image file via AJAX (for fallback uploader)
     */
    function uploadImageFileViaAjax(file) {
        const formData = new FormData();
        formData.append('action', 'microblog_upload_image');
        formData.append('nonce', microblog_ajax.nonce); // Localized nonce
        formData.append('image', file);
        
        showMessage(microblog_ajax.l10n?.uploadingImage || 'Uploading image...', 'info');
        setSubmitButtonState(true, microblog_ajax.l10n?.uploadingImage || 'Uploading...'); // Disable form submit during image upload

        fetch(microblog_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                setSelectedImage(data.data.attachment_id, data.data.image_url);
                showMessage(data.data.message || microblog_ajax.l10n?.imageUploadedSuccess || 'Image uploaded successfully!', 'success');
            } else {
                showMessage(data.data.message || microblog_ajax.l10n?.uploadFailed || 'Upload failed', 'error');
            }
        })
        .catch(error => {
            console.error('MicroBlog Upload error:', error);
            showMessage(microblog_ajax.l10n?.uploadError || 'Upload failed. Please try again.', 'error');
        })
        .finally(() => {
             setSubmitButtonState(false); // Re-enable form submit
        });
    }

    // --- Image Handling Functions ---

    /**
     * Set selected image in preview and update hidden input
     */
    function setSelectedImage(attachmentId, imageUrl) {
        if (thumbnailIdInput) {
            thumbnailIdInput.value = attachmentId;
        }
        
        if (previewImg) {
            previewImg.src = imageUrl;
            previewImg.alt = microblog_ajax.l10n?.imagePreviewAlt || 'Selected image preview';
        }
        
        if (imagePreviewDiv) {
            imagePreviewDiv.style.display = 'block';
        }
        
        if (uploadBtn) {
            uploadBtn.textContent = microblog_ajax.l10n?.changeImageButton || 'Change Image';
        }
    }

    /**
     * Handle removal of the selected image
     */
    function handleRemoveImage(e) {
        e.preventDefault();
        
        if (thumbnailIdInput) {
            thumbnailIdInput.value = '';
        }
        
        if (imagePreviewDiv) {
            imagePreviewDiv.style.display = 'none';
        }
        if (previewImg) {
            previewImg.src = '';
            previewImg.alt = '';
        }
        
        if (uploadBtn) {
            uploadBtn.textContent = microblog_ajax.l10n?.chooseImageButton || 'Choose Image';
        }
        // If using fallback uploader, clear its value
        const fallbackInput = document.querySelector('input[type="file"][accept=".jpg,.jpeg,.png,.webp,.gif"]');
        if (fallbackInput) {
            fallbackInput.value = '';
        }
    }
    
    // --- Form Submission ---

    /**
     * Handle form submission via AJAX
     */
    function handleFormSubmission(e) {
        e.preventDefault();
        
        // Get TinyMCE content if available, otherwise textarea
        let content = '';
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('microblog_content')) {
            content = tinyMCE.get('microblog_content').getContent();
        } else if (contentTextarea) {
            content = contentTextarea.value;
        }
        
        // Validate required fields
        if (!titleInput || !titleInput.value.trim()) {
            showMessage(microblog_ajax.l10n?.titleRequired || 'Title is required.', 'error');
            titleInput?.focus();
            return;
        }
        
        // Content validation removed to align with PHP (server handles character limit)
        // if (!content.trim()) {
        //     showMessage(microblog_ajax.l10n?.contentRequired || 'Content is required.', 'error');
        //     if (typeof tinyMCE !== 'undefined' && tinyMCE.get('microblog_content')) {
        //         tinyMCE.get('microblog_content').focus();
        //     } else {
        //         contentTextarea?.focus();
        //     }
        //     return;
        // }
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'microblog_submit_post');
        formData.append('nonce', microblog_ajax.nonce);
        formData.append('title', titleInput.value.trim());
        formData.append('content', content);
        
        if (categorySelect) {
            formData.append('category', categorySelect.value);
        }
        
        if (thumbnailIdInput && thumbnailIdInput.value) {
            formData.append('thumbnail', thumbnailIdInput.value);
        }

        // Add redirect_url to FormData to be used by PHP
        if (microblogForm.dataset.redirect) {
            formData.append('redirect_url', microblogForm.dataset.redirect);
        }
        
        // Show loading state
        setSubmitButtonState(true, microblog_ajax.l10n?.submitting || 'Submitting...');
        showMessage(microblog_ajax.l10n?.submittingPost || 'Submitting post...', 'info');
        
        // Submit form
        fetch(microblog_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showMessage(data.data.message || microblog_ajax.l10n?.postSubmittedSuccess || 'Post submitted successfully!', 'success');
                
                // Reset form
                microblogForm.reset(); 
                
                // Reset TinyMCE editor
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('microblog_content')) {
                    tinyMCE.get('microblog_content').setContent('');
                }
                
                // Reset image preview
                handleRemoveImage(new Event('click')); // Simulate click to reset image UI
                
                // Redirect if specified in AJAX response
                const redirectUrlFromServer = data.data.redirect;
                if (redirectUrlFromServer) {
                    setTimeout(() => {
                        window.location.href = redirectUrlFromServer;
                    }, 2000); // Delay for user to see success message
                }
                
            } else {
                showMessage(data.data.message || microblog_ajax.l10n?.submissionFailed || 'Submission failed', 'error');
            }
        })
        .catch(error => {
            console.error('MicroBlog Submission error:', error);
            showMessage(microblog_ajax.l10n?.submissionError || 'Submission failed. Please try again.', 'error');
        })
        .finally(() => {
            // Restore button state
            setSubmitButtonState(false);
        });
    }

    /**
     * Helper to set submit button state
     */
    function setSubmitButtonState(disabled, text) {
        const submitBtn = microblogForm ? microblogForm.querySelector('button[type="submit"]') : null;
        if (submitBtn) {
            submitBtn.disabled = disabled;
            if (text) {
                // Store original text if not already stored
                if (!submitBtn.dataset.originalText && !disabled) {
                     // This case should not happen often if originalText is set on disabling
                } else if (disabled) {
                     if(!submitBtn.dataset.originalText) submitBtn.dataset.originalText = submitBtn.textContent;
                     submitBtn.textContent = text;
                } else {
                     submitBtn.textContent = submitBtn.dataset.originalText || (microblog_ajax.l10n?.submitButtonDefault || 'Submit Post');
                }
            } else if (!disabled && submitBtn.dataset.originalText) {
                 submitBtn.textContent = submitBtn.dataset.originalText;
            }
        }
    }
    
    // --- UI Helper Functions ---

    /**
     * Show message to user
     */
    function showMessage(message, type) {
        if (!messageDiv) return;
        
        messageDiv.textContent = message;
        messageDiv.className = 'microblog-message microblog-message-' + type; // e.g., microblog-message-success
        messageDiv.style.display = 'block';
        messageDiv.setAttribute('role', type === 'error' ? 'alert' : 'status');
        
        // Auto-hide success/info messages after a delay
        if (type === 'success' || type === 'info') {
            setTimeout(() => {
                if (messageDiv.textContent === message) { // Hide only if it's still the same message
                    messageDiv.style.display = 'none';
                }
            }, type === 'success' ? 5000 : 3000); // Longer for success
        }
    }
    
    // --- Validation Functions ---

    /**
     * Check if image MIME type is valid
     */
    function isValidImageType(mimeType) {
        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']; // Added GIF
        return validTypes.includes(mimeType);
    }
    
    /**
     * Check if uploaded file (from file input) is a valid image type
     */
    function isValidImageFile(file) {
        return isValidImageType(file.type);
    }
    
    // --- oEmbed & Editor Enhancements ---

    /**
     * Initialize oEmbed-like support for content editor.
     * WordPress handles actual oEmbed processing server-side.
     * TinyMCE often has its own media preview capabilities for pasted URLs.
     */
    function initOEmbedSupport() {
        if (typeof tinyMCE !== 'undefined') {
            tinyMCE.on('AddEditor', function(e) {
                const editor = e.editor;
                
                editor.on('init', function() {
                    // Add custom styling for embedded content preview if TinyMCE uses a specific class
                    // This is speculative and depends on TinyMCE's preview mechanism.
                    editor.dom.addStyle(`
                        .mce-preview-object { /* Example class, might be different */
                            border: 1px dashed #ccc;
                            padding: 10px;
                            margin: 10px 0;
                            background: #f9f9f9;
                            max-width: 100%;
                            box-sizing: border-box;
                        }
                        .mce-preview-object iframe {
                            max-width: 100%;
                        }
                    `);
                });
                // Further oEmbed client-side handling (like paste detection) is removed
                // to rely on TinyMCE's built-in features and WordPress server-side processing,
                // which are generally more robust.
            });
        }
    }
    
    // --- Dynamic Form Validation Hints ---

    /**
     * Handle dynamic form validation hints (e.g., on blur)
     */
    function initFormValidation() {
        if (titleInput) {
            titleInput.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('microblog-input-error');
                    // Optionally show a specific error message next to the field
                } else {
                    this.classList.remove('microblog-input-error');
                }
            });
            
            titleInput.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('microblog-input-error');
                }
            });
        }
        // Similar validation can be added for other fields if needed.
    }
    
    // --- Responsive Preview ---

    /**
     * Handle responsive image preview adjustments
     */
    function initResponsivePreview() {
        if (previewImg) {
            // CSS should primarily handle responsiveness (e.g., max-width: 100%)
            // This JS is more of a fallback or for specific dynamic adjustments if needed.
            previewImg.addEventListener('load', function() {
                // Ensure image fits within its container if not handled by CSS
                this.style.maxWidth = '100%'; 
                this.style.height = 'auto';
            });
        }
    }
    
    // --- Accessibility ---

    /**
     * Accessibility improvements for form elements
     */
    function initAccessibility() {
        // Add ARIA describedby for upload button for instructions
        if (uploadBtn) {
            const helpTextId = 'microblog-upload-help';
            uploadBtn.setAttribute('aria-describedby', helpTextId);
            
            let helpTextElement = document.getElementById(helpTextId);
            if (!helpTextElement && uploadBtn.parentNode) {
                helpTextElement = document.createElement('div');
                helpTextElement.id = helpTextId;
                helpTextElement.className = 'screen-reader-text'; // Visually hidden but readable by screen readers
                helpTextElement.textContent = microblog_ajax.l10n?.uploadHelpText || 'Supported formats: JPG, PNG, WebP, GIF. Maximum one image.';
                uploadBtn.parentNode.appendChild(helpTextElement);
            }
        }
        
        // Ensure form fields are properly labelled for accessibility
        if (microblogForm) {
            const formFields = microblogForm.querySelectorAll('input:not([type="hidden"]), select, textarea');
            formFields.forEach(field => {
                // If field has an ID and no aria-label or aria-labelledby, try to link to its label
                if (field.id && !field.getAttribute('aria-label') && !field.getAttribute('aria-labelledby')) {
                    const label = microblogForm.querySelector(`label[for="${field.id}"]`);
                    if (label) {
                        if (!label.id) {
                            label.id = field.id + '-label'; // Ensure label has an ID
                        }
                        field.setAttribute('aria-labelledby', label.id);
                    }
                }
            });
        }
    }

});
				// End DOMContentLoaded

**Key recommendations for your PHP and setup:**

1.  **Reconcile jQuery Dependency:** In `Microblog_Plugin::enqueue_scripts()`, change `array('jquery')` to `array()` if this Vanilla JS is the final version.
2.  **Localization (`l10n`):** I've added placeholders like `microblog_ajax.l10n?.someText || 'Default Text'`. You should add these translatable strings to your `wp_localize_script` call in PHP if you want to make them easily translatable and configurable from PHP. For example:
    ```php
    // In Microblog_Plugin::enqueue_scripts()
    wp_localize_script( 'microblog-js', 'microblog_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'microblog_nonce' ),
        'maxFileSizeMB' => get_option('microblog_settings')['max_file_size'] ?? 5, // Pass max file size
        'l10n'     => array(
            'selectImageTitle' => __( 'Select or Upload Image', 'microblog' ),
            'useImageButton'   => __( 'Use This Image', 'microblog' ),
            'invalidFileType'  => __( 'Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.', 'microblog' ),
            'invalidFileTypeFallback' => __( 'Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.', 'microblog' ),
            'fileTooLarge'     => __( 'File is too large. Maximum size is %s MB.', 'microblog' ), // %s will be replaced by JS
            'uploadingImage'   => __( 'Uploading image...', 'microblog' ),
            'imageUploadedSuccess' => __( 'Image uploaded successfully!', 'microblog' ),
            'uploadFailed'     => __( 'Upload failed.', 'microblog' ),
            'uploadError'      => __( 'Upload failed. Please try again.', 'microblog' ),
            'changeImageButton'=> __( 'Change Image', 'microblog' ),
            'chooseImageButton'=> __( 'Choose Image', 'microblog' ),
            'imagePreviewAlt'  => __( 'Selected image preview', 'microblog' ),
            'titleRequired'    => __( 'Title is required.', 'microblog' ),
            // 'contentRequired'  => __( 'Content is required.', 'microblog' ), // Removed as per new logic
            'submitting'       => __( 'Submitting...', 'microblog' ),
            'submittingPost'   => __( 'Submitting post...', 'microblog' ),
            'postSubmittedSuccess' => __( 'Post submitted successfully!', 'microblog' ),
            'submissionFailed' => __( 'Submission failed.', 'microblog' ),
            'submissionError'  => __( 'Submission failed. Please try again.', 'microblog' ),
            'submitButtonDefault' => __( 'Submit Post', 'microblog' ),
            'uploadHelpText'   => __( 'Supported formats: JPG, PNG, WebP, GIF. Maximum one image.', 'microblog' ),
        )
    ) );
