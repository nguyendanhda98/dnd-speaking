jQuery(document).ready(function($) {
    console.log('Discord settings script loaded');

    // Copy redirect URL to clipboard when clicking the input
    $('.dnd-discord-redirect-input').on('click', function() {
        console.log('Input clicked');
        var $input = $(this);
        var $container = $input.parent();
        var $feedback = $container.find('.dnd-copy-feedback');
        var originalValue = $input.val();
        var redirectUrl = originalValue;

        console.log('Original value:', originalValue);
        console.log('Feedback element found:', $feedback.length);

        // Copy to clipboard
        navigator.clipboard.writeText(redirectUrl).then(function() {
            console.log('Copy successful');
            showCopyFeedback($input, $feedback, originalValue);
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = redirectUrl;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);

            console.log('Fallback copy successful');
            showCopyFeedback($input, $feedback, originalValue);
        });
    });

    function showCopyFeedback($input, $feedback, originalValue) {
        console.log('Showing feedback');
        // Change input value and show checkmark
        $input.val('✓ Copied!');
        $input.css({
            'background-color': '#e8f5e8',
            'color': '#2d5a2d',
            'font-weight': 'bold'
        });
        $feedback.show();

        // Reset after 2 seconds
        setTimeout(function() {
            console.log('Resetting feedback');
            $input.val(originalValue);
            $input.css({
                'background-color': '#f5f5f5',
                'color': '',
                'font-weight': ''
            });
            $feedback.hide();
        }, 2000);
    }

    // Populate the redirect page dropdown
    var $pageDropdown = $('#dnd_discord_redirect_page');
    
    if ($pageDropdown.length > 0) {
        console.log('Redirect page dropdown found, fetching pages...');
        
        // Fetch pages from the server
        $.ajax({
            url: dndSettings.ajaxurl,
            method: 'POST',
            data: {
                action: 'get_pages'
            },
            success: function(pages) {
                console.log('Pages loaded:', pages);
                var savedPage = $pageDropdown.data('selected') || '';
                
                pages.forEach(function(page) {
                    var $option = $('<option></option>')
                        .val(page.url)
                        .text(page.title);
                    
                    if (page.url === savedPage) {
                        $option.prop('selected', true);
                    }
                    
                    $pageDropdown.append($option);
                });
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error: ' + textStatus + ': ' + errorThrown);
            }
        });
    }


    // Append the dropdown to the settings container
    $('.settings-container').append($pageDropdown);
});