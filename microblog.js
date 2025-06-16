/**
 * MicroBlog Frontend JavaScript
 * 
 * Handles the frontend form submission for microblog posts,
 * including title extraction from parentheses and hashtag processing.
 * 
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function () {
    // Get the microblog form element
    var form = document.querySelector('form#microblog-form');
    if (!form) return;

    // Get form elements
    var textarea = form.querySelector('textarea#microblog-content');
    var submitBtn = form.querySelector('input[type="submit"]');
    var categorySelect = form.querySelector('select#microblog-category');

    // Validate that all required elements exist
    if (!textarea || !submitBtn || !categorySelect) {
        console.error('MicroBlog: Required form elements not found');
        return;
    }

    /**
     * Handle form submission
     */
    submitBtn.addEventListener('click', function (event) {
        event.preventDefault();
        
        // Disable submit button to prevent double submission
        submitBtn.disabled = true;
        submitBtn.value = 'Submitting...';

        // Get form values
        var rawContent = textarea.value.trim();
        var category = categorySelect.value || microblogData.defaultCategory;

        // Validate content
        if (!rawContent) {
            showMessage(microblogData.messages.emptyContent, 'error');
            resetSubmitButton();
            return;
        }

        // Process the content to extract title and clean content
        var processedData = processContent(rawContent);

        // Prepare form data for AJAX
        var data = new FormData();
        data.append('action', 'microblog_submit');
        data.append('microblog_content', processedData.content);
        data.append('microblog_title', processedData.title);
        data.append('microblog_category', category);
        data.append('microblog_nonce', microblogData.nonce);

        // Send AJAX request
        fetch(microblogData.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data,
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function (response) {
            handleSubmissionResponse(response);
        })
        .catch(function (error) {
            console.error('MicroBlog Submission Error:', error);
            showMessage(microblogData.messages.error, 'error');
            resetSubmitButton();
        });
    });

    /**
     * Process content to extract title and hashtags
     * 
     * @param {string} rawContent - The raw content from the textarea
     * @returns {Object} Object containing processed title, content, and hashtags
     */
    function processContent(rawContent) {
        var lines = rawContent.split('\n');
        var title = '';
        var contentLines = lines.slice(); // Create a copy of lines array
        var hashtags = [];

        // Check for title only on the first line if it's within parentheses
        if (lines.length > 0) {
            var firstLine = lines[0].trim();
            if (firstLine.startsWith('(') && firstLine.endsWith(')')) {
                // Extract title from parentheses
                title = firstLine.substring(1, firstLine.length - 1).trim();
                contentLines.shift(); // Remove the title line from content
            }
        }

        // Extract hashtags from all content lines (including title line if present)
        lines.forEach(function (line) {
            var lineHashtags = extractHashtagsFromLine(line);
            hashtags = hashtags.concat(lineHashtags);
        });

        // Remove duplicates from hashtags
        hashtags = [...new Set(hashtags)];

        // Rebuild content without title line
        var processedContent = contentLines.join('\n').trim();

        return {
            title: title,
            content: processedContent,
            hashtags: hashtags
        };
    }

    /**
     * Extract hashtags from a single line of text
     * 
     * @param {string} line - The line of text to process
     * @returns {Array} Array of hashtags (without the # symbol)
     */
    function extractHashtagsFromLine(line) {
        var hashtags = [];
        // Match hashtags with word characters (letters, digits, underscore)
        // Using a more comprehensive regex that handles Unicode characters
        var hashtagRegex = /(?:^|\s)(#[\w\u00C0-\u017F\u0400-\u04FF]+)/g;
        var matches = line.match(hashtagRegex);
        
        if (matches) {
            matches.forEach(function (match) {
                // Remove leading whitespace and '#', convert to lowercase
                var cleanHashtag = match.trim().substring(1).toLowerCase();
                if (cleanHashtag && hashtags.indexOf(cleanHashtag) === -1) {
                    hashtags.push(cleanHashtag);
                }
            });
        }
        
        return hashtags;
    }

    /**
     * Handle the response from the AJAX submission
     * 
     * @param {Object} response - The JSON response from the server
     */
    function handleSubmissionResponse(response) {
        if (response && response.success) {
            // Clear the form
            textarea.value = '';
            
            // Reset category to default
            if (microblogData.defaultCategory) {
                var defaultOption = categorySelect.querySelector('option[value="' + microblogData.defaultCategory + '"]');
                if (defaultOption) {
                    categorySelect.value = microblogData.defaultCategory;
                }
            }

            // Show success message
            var successMessage = microblogData.messages.success;
            if (response.data && response.data.message) {
                successMessage = response.data.message;
            }
            showMessage(successMessage, 'success');

            // Auto-redirect to the created post after a short delay
            if (response.data && response.data.post_id) {
                setTimeout(function() {
                    window.location.href = microblogData.siteUrl + '?p=' + response.data.post_id;
                }, 2000); // 2 second delay to show the success message
            }
        } else {
            // Handle error response
            var errorMessage = microblogData.messages.error;
            if (response && response.data) {
                if (typeof response.data === 'string') {
                    errorMessage = response.data;
                } else if (response.data.message) {
                    errorMessage = response.data.message;
                }
            }
            showMessage(errorMessage, 'error');
            resetSubmitButton();
        }
    }

    /**
     * Reset the submit button to its original state
     */
    function resetSubmitButton() {
        submitBtn.disabled = false;
        submitBtn.value = 'Submit';
    }

    /**
     * Display a message in ClassicPress/WordPress style
     * 
     * @param {string} message - The message to display
     * @param {string} type - The type of message ('success', 'error', 'warning', 'info')
     */
    function showMessage(message, type) {
        type = type || 'info';
        
        // Get or create the message container
        var messageContainer = document.getElementById('microblog-messages');
        if (!messageContainer) {
            messageContainer = document.createElement('div');
            messageContainer.id = 'microblog-messages';
            messageContainer.className = 'microblog-messages';
            form.parentNode.insertBefore(messageContainer, form);
        }

        // Clear existing messages
        messageContainer.innerHTML = '';

        // Create the message element
        var messageDiv = document.createElement('div');
        messageDiv.className = 'notice notice-' + type + ' is-dismissible microblog-notice';
        
        // Create message content
        var messageParagraph = document.createElement('p');
        messageParagraph.textContent = message;
        messageDiv.appendChild(messageParagraph);

        // Create dismiss button
        var dismissButton = document.createElement('button');
        dismissButton.type = 'button';
        dismissButton.className = 'notice-dismiss';
        dismissButton.innerHTML = '<span class="screen-reader-text">Dismiss this notice.</span>';
        dismissButton.addEventListener('click', function() {
            messageDiv.style.display = 'none';
        });
        messageDiv.appendChild(dismissButton);

        // Add the message to the container
        messageContainer.appendChild(messageDiv);

        // Scroll to the message
        messageContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        // Auto-dismiss success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                if (messageDiv && messageDiv.style.display !== 'none') {
                    messageDiv.style.opacity = '0';
                    messageDiv.style.transition = 'opacity 0.5s ease-out';
                    setTimeout(function() {
                        if (messageDiv.parentNode) {
                            messageDiv.parentNode.removeChild(messageDiv);
                        }
                    }, 500);
                }
            }, 5000);
        }
    }

    /**
     * Add character counter functionality (optional enhancement)
     */
    if (textarea) {
        // Add character counter below textarea
        var charCounter = document.createElement('div');
        charCounter.className = 'microblog-char-counter';
        charCounter.style.fontSize = '12px';
        charCounter.style.color = '#666';
        charCounter.style.marginTop = '5px';
        textarea.parentNode.insertBefore(charCounter, textarea.nextSibling);

        // Update character count
        function updateCharCount() {
            var count = textarea.value.length;
            charCounter.textContent = count + ' characters';
            
            // Change color if approaching common limits
            if (count > 280) {
                charCounter.style.color = '#ff6b6b';
            } else if (count > 240) {
                charCounter.style.color = '#ffa500';
            } else {
                charCounter.style.color = '#666';
            }
        }

        // Initial count
        updateCharCount();

        // Update on input
        textarea.addEventListener('input', updateCharCount);
        textarea.addEventListener('paste', function() {
            // Delay to allow paste to complete
            setTimeout(updateCharCount, 10);
        });
    }

    /**
     * Add auto-resize functionality to textarea
     */
    if (textarea) {
        function autoResize() {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        }

        textarea.addEventListener('input', autoResize);
        textarea.addEventListener('paste', function() {
            setTimeout(autoResize, 10);
        });

        // Initial resize
        autoResize();
    }
});

/**
 * Global error handler for uncaught errors in microblog functionality
 */
window.addEventListener('error', function(event) {
    if (event.filename && event.filename.includes('microblog')) {
        console.error('MicroBlog Error:', event.error);
    }
});
