/**
 * Teacher Requests Block - Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    $('.dnd-teacher-requests').on('click', '.dnd-btn-accept, .dnd-btn-decline', function(e) {
        e.preventDefault();

        const button = $(this);
        const requestItem = button.closest('.dnd-request-item');
        const sessionId = requestItem.data('session-id');
        const action = button.data('action');

        // Disable buttons and show processing state
        requestItem.addClass('processing');
        requestItem.find('.dnd-btn').prop('disabled', true);

        // Remove any existing messages
        requestItem.find('.dnd-request-message').remove();

        // Send AJAX request
        $.ajax({
            url: dnd_teacher_requests_data.ajax_url,
            type: 'POST',
            data: {
                action: 'handle_teacher_request',
                session_id: sessionId,
                request_action: action,
                nonce: dnd_teacher_requests_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    const messageClass = action === 'accept' ? 'success' : 'success';
                    const messageText = action === 'accept' ? 'Request accepted successfully!' : 'Request declined successfully!';

                    requestItem.append('<div class="dnd-request-message ' + messageClass + '">' + messageText + '</div>');

                    // Remove the request item after a short delay
                    setTimeout(function() {
                        requestItem.fadeOut(300, function() {
                            $(this).remove();

                            // Check if no more requests
                            if ($('.dnd-request-item').length === 0) {
                                $('.dnd-requests-list').html('<div class="dnd-no-requests">No pending requests</div>');
                            }
                        });
                    }, 1500);

                } else {
                    // Show error message
                    requestItem.append('<div class="dnd-request-message error">' + (response.data || 'An error occurred') + '</div>');

                    // Re-enable buttons
                    requestItem.removeClass('processing');
                    requestItem.find('.dnd-btn').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);

                // Show error message
                requestItem.append('<div class="dnd-request-message error">Network error. Please try again.</div>');

                // Re-enable buttons
                requestItem.removeClass('processing');
                requestItem.find('.dnd-btn').prop('disabled', false);
            }
        });
    });
});