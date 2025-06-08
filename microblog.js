/**
 * Microblog Frontend JavaScript
 * Handles form submission, image upload, and media library integration (Vanilla JS)
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // --- DOM Element References ---
    const microblogForm = document.getElementById('microblog-form');
    const uploadBtn = document.getElementById('microblog-upload-btn');
    const imagePreviewDiv = document.getElementById('microblog-image-preview');
    const previewImg = document.getElementById('microblog-preview-img');
    const removeImageBtn = document.getElementById('microblog-remove-image');
    const thumbnailIdInput = document.getElementById('microblog-thumbnail-id');
    const messageDiv = document.getElementById('microblog-message');
    const titleInput = document.getElementById('microblog-title');
    const categorySelect = document.getElementById('microblog-category');
    const contentTextarea = document.getElementById('microblog_content');
    
    let mediaFrame = null; // For WordPress media uploader

    // --- Initialization ---

    // Initialize media uploader (WP Media Library or fallback)
    if (uploadBtn) {
        if (typeof wp !== 'undefined' && wp.media && typeof wp.media.featuredImage !== 'undefined') {
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
        removeImageBtn.setAttribute('aria-label', 'Remove selected image');
        removeImageBtn.setAttribute('title', 'Remove image');
    }

    // Initialize other components
    initOEmbedSupport();
    initFormValidation();
    initResponsivePreview();
    initAccessibility();
    initCategoryVisibility();

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
                title: getLocalizedText('selectImageTitle', 'Select Image'),
                button: {
                    text: getLocalizedText('useImageButton', 'Use this image')
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
                    const imageUrl = attachment.sizes?.medium?.url || 
                                   attachment.sizes?.thumbnail?.url || 
                                   attachment.url;
                    setSelectedImage(attachment.id, imageUrl);
                } else {
                    showMessage(getLocalizedText('invalidFileType', 'Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.'), 'error');
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
        fileInput.accept = '.jpg,.jpeg,.png,.webp,.gif';
        fileInput.style.display = 'none';
        
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
                    showMessage(getLocalizedText('invalidFileTypeFallback', 'Invalid file type. Only JPG, PNG, WebP, and GIF are allowed.'), 'error');
                    this.value = '';
                    return;
                }
                
                // Check file size
                const maxFileSizeMB = getConfigValue('maxFileSizeMB', 5);
                if (file.size > maxFileSizeMB * 1024 * 1024) {
                    const message = getLocalizedText('fileTooLarge', 'File is too large. Maximum size is %s MB.');
                    showMessage(message.replace('%s', maxFileSizeMB), 'error');
                    this.value = '';
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
        formData.append('nonce', getConfigValue('nonce', ''));
        formData.append('image', file);
        
        showMessage(getLocalizedText('uploadingImage', 'Uploading image...'), 'info');
        setSubmitButtonState(true, getLocalizedText('uploadingImage', 'Uploading...'));

        fetch(getConfigValue('ajax_url', '/wp-admin/admin-ajax.php'), {
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
                showMessage(data.data.message || getLocalizedText('imageUploadedSuccess', 'Image uploaded successfully!'), 'success');
            } else {
                showMessage(data.data.message || getLocalizedText('uploadFailed', 'Upload failed'), 'error');
            }
        })
        .catch(error => {
            console.error('MicroBlog Upload error:', error);
            showMessage(getLocalizedText('uploadError', 'Upload failed. Please try again.'), 'error');
        })
        .finally(() => {
            setSubmitButtonState(false);
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
            previewImg.alt = getLocalizedText('imagePreviewAlt', 'Selected image preview');
        }
        
        if (imagePreviewDiv) {
            imagePreviewDiv.style.display = 'block';
        }
        
        if (uploadBtn) {
            uploadBtn.textContent = getLocalizedText('changeImageButton', 'Change Image');
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
            uploadBtn.textContent = getLocalizedText('chooseImageButton', 'Choose Image');
        }
        
        // Clear fallback file input if it exists
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
            showMessage(getLocalizedText('titleRequired', 'Title is required.'), 'error');
            if (titleInput) titleInput.focus();
            return;
        }
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'microblog_submit_post');
        formData.append('nonce', getConfigValue('nonce', ''));
        formData.append('title', titleInput.value.trim());
        formData.append('content', content);
        
        if (categorySelect) {
            formData.append('category', categorySelect.value);
        }
        
        if (thumbnailIdInput && thumbnailIdInput.value) {
            formData.append('thumbnail', thumbnailIdInput.value);
        }

        // Add redirect_url to FormData
        if (microblogForm.dataset.redirect) {
            formData.append('redirect_url', microblogForm.dataset.redirect);
        }
        
        // Show loading state
        setSubmitButtonState(true, getLocalizedText('submitting', 'Submitting...'));
        showMessage(getLocalizedText('submittingPost', 'Submitting post...'), 'info');
        
        // Submit form
        fetch(getConfigValue('ajax_url', '/wp-admin/admin-ajax.php'), {
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
                showMessage(data.data.message || getLocalizedText('postSubmittedSuccess', 'Post submitted successfully!'), 'success');
                
                // Reset form
                microblogForm.reset(); 
                
                // Reset TinyMCE editor
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('microblog_content')) {
                    tinyMCE.get('microblog_content').setContent('');
                }
                
                // Reset image preview
                handleRemoveImage(new Event('click'));
                
                // Redirect if specified
                const redirectUrlFromServer = data.data.redirect;
                if (redirectUrlFromServer) {
                    setTimeout(() => {
                        window.location.href = redirectUrlFromServer;
                    }, 2000);
                }
                
            } else {
                showMessage(data.data.message || getLocalizedText('submissionFailed', 'Submission failed'), 'error');
            }
        })
        .catch(error => {
            console.error('MicroBlog Submission error:', error);
            showMessage(getLocalizedText('submissionError', 'Submission failed. Please try again.'), 'error');
        })
        .finally(() => {
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
            if (text && disabled) {
                if (!submitBtn.dataset.originalText) {
                    submitBtn.dataset.originalText = submitBtn.textContent;
                }
                submitBtn.textContent = text;
            } else if (!disabled) {
                submitBtn.textContent = submitBtn.dataset.originalText || getLocalizedText('submitButtonDefault', 'Submit Post');
            }
        }
    }
    
    // --- Category Visibility ---
    
    /**
     * Initialize category visibility based on data attribute
     */
    function initCategoryVisibility() {
        if (categorySelect && categorySelect.dataset.hideCategory === "1") {
            categorySelect.style.display = 'none';

            // Also hide the label
            const label = document.querySelector('label[for="microblog-category"]');
            if (label) {
                label.style.display = 'none';
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
        messageDiv.className = 'microblog-message microblog-message-' + type;
        messageDiv.style.display = 'block';
        messageDiv.setAttribute('role', type === 'error' ? 'alert' : 'status');
        
        // Auto-hide success/info messages after a delay
        if (type === 'success' || type === 'info') {
            setTimeout(() => {
                if (messageDiv.textContent === message) {
                    messageDiv.style.display = 'none';
                }
            }, type === 'success' ? 5000 : 3000);
        }
    }
    
    // --- Validation Functions ---

    /**
     * Check if image MIME type is valid
     */
    function isValidImageType(mimeType) {
        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        return validTypes.includes(mimeType);
    }
    
    /**
     * Check if uploaded file is a valid image type
     */
    function isValidImageFile(file) {
        return isValidImageType(file.type);
    }
    
    // --- oEmbed & Editor Enhancements ---

    /**
     * Initialize oEmbed-like support for content editor
     */
    function initOEmbedSupport() {
        if (typeof tinyMCE !== 'undefined') {
            tinyMCE.on('AddEditor', function(e) {
                const editor = e.editor;
                
                editor.on('init', function() {
                    editor.dom.addStyle(`
                        .mce-preview-object {
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
            });
        }
    }
    
    // --- Dynamic Form Validation Hints ---

    /**
     * Handle dynamic form validation hints
     */
    function initFormValidation() {
        if (titleInput) {
            titleInput.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('microblog-input-error');
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
    }
    
    // --- Responsive Preview ---

    /**
     * Handle responsive image preview adjustments
     */
    function initResponsivePreview() {
        if (previewImg) {
            previewImg.addEventListener('load', function() {
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
        if (uploadBtn) {
            const helpTextId = 'microblog-upload-help';
            uploadBtn.setAttribute('aria-describedby', helpTextId);
            
            let helpTextElement = document.getElementById(helpTextId);
            if (!helpTextElement && uploadBtn.parentNode) {
                helpTextElement = document.createElement('div');
                helpTextElement.id = helpTextId;
                helpTextElement.className = 'screen-reader-text';
                helpTextElement.textContent = getLocalizedText('uploadHelpText', 'Supported formats: JPG, PNG, WebP, GIF. Maximum one image.');
                uploadBtn.parentNode.appendChild(helpTextElement);
            }
        }
        
        // Ensure form fields are properly labelled
        if (microblogForm) {
            const formFields = microblogForm.querySelectorAll('input:not([type="hidden"]), select, textarea');
            formFields.forEach(field => {
                if (field.id && !field.getAttribute('aria-label') && !field.getAttribute('aria-labelledby')) {
                    const label = microblogForm.querySelector(`label[for="${field.id}"]`);
                    if (label) {
                        if (!label.id) {
                            label.id = field.id + '-label';
                        }
                        field.setAttribute('aria-labelledby', label.id);
                    }
                }
            });
        }
    }

    // --- Utility Functions ---

    /**
     * Get localized text with fallback
     */
    function getLocalizedText(key, fallback) {
        if (typeof microblog_ajax !== 'undefined' && 
            microblog_ajax.l10n && 
            microblog_ajax.l10n[key]) {
            return microblog_ajax.l10n[key];
        }
        return fallback;
    }

    /**
     * Get config value with fallback
     */
    function getConfigValue(key, fallback) {
        if (typeof microblog_ajax !== 'undefined' && microblog_ajax[key]) {
            return microblog_ajax[key];
        }
        return fallback;
    }

}); // End DOMContentLoaded
