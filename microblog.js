(function($) {
  // Wait for the DOM to be ready
  $(function() {
    // Find the form element
    var $form = $('form#microblog-form');

    // Find the textarea element
    var $textarea = $form.find('textarea#microblog-content');

    // Find the submit button element
    var $submit = $form.find('input[type="submit"]');

    // Find the category select element
    var $categorySelect = $form.find('select#microblog-category');

    // Add a click event handler to the submit button
    $submit.on('click', function(event) {
      // Prevent the form from submitting
      event.preventDefault();

      // Disable the submit button
      $submit.prop('disabled', true);

      // Get the content from the textarea
      var content = $textarea.val();

      // Extract the title, tags, and category from the content
      var title = '';
      var tags = [];
      var category = $categorySelect.val();
      var lines = content.split('\n');
      if (category === '') {
        category = microblogData.defaultCategory;
      }

      for (var i = 0; i < lines.length; i++) {
        var line = lines[i];

        // Check for title ONLY on the first line and if it's within parentheses
        if (i === 0 && line.indexOf('(') === 0 && line.indexOf(')') === line.length - 1) {
          title = line.substring(1, line.length - 1).trim();
          lines.splice(0, 1); // Remove the first line from the 'lines' array
          continue; // Skip subsequent processing for the first line
        }

        // Extract tags from hashtagged words (refined version)
var hashtags = line.match(/(?:^|\s)(#\w+)/g); // Match and capture the hashtags

if (hashtags) {
    var extractedTags = hashtags.map(function(tag) {
        return tag.trim().substring(1).toLowerCase(); // Remove the leading '#' and convert to lowercase
    });
    tags = tags.concat(extractedTags);
}

      }

      // Reconstruct the content without the first line (if it was used as the title)
      content = lines.join('\n');

      // Send an AJAX request to create the post
      $.ajax({
        url: microblogData.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'microblog_submit',
          content: content,
          title: title,
          tags: tags,
          microblog_category: category,
          nonce: microblogData.nonce
        },
        success: function(response) {
          if (response && response.success) {
            // Redirect to the created post
            window.location.href = microblogData.siteUrl + '?p=' + response.data.post_id;
          } else {
            // Show an error message
            alert('Error: ' + (response && response.data ? response.data : 'Unknown error occurred.'));
          }
        },
        error: function(xhr, status, error) {
          // Handle AJAX errors
          alert('An error occurred while creating the post. Please try again later.');

          // Re-enable the submit button
          $submit.prop('disabled', false);
        }
      });
    });
  });
})(jQuery);