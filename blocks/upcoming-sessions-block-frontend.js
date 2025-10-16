/**
 * Upcoming Sessions Block - Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    $('.dnd-upcoming-sessions').on('click', '.dnd-btn-start, .dnd-btn-cancel', function(e) {
        e.preventDefault();

        const button = $(this);
        const sessionItem = button.closest('.dnd-session-item');
        const sessionId = sessionItem.data('session-id');
        const action = button.data('action');

        // Disable buttons and show processing state
        sessionItem.addClass('processing');
        sessionItem.find('.dnd-btn').prop('disabled', true);

        // Remove any existing messages
        sessionItem.find('.dnd-session-message').remove();

        // Send AJAX request
        $.ajax({
            url: dnd_upcoming_sessions_data.ajax_url,
            type: 'POST',
            data: {
                action: 'handle_upcoming_session',
                session_id: sessionId,
                session_action: action,
                nonce: dnd_upcoming_sessions_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    let messageText = '';
                    let messageClass = 'success';

                    if (action === 'start') {
                        messageText = 'Session started successfully!';
                        // Optionally redirect to session room or update UI
                    } else if (action === 'cancel') {
                        messageText = 'Session cancelled successfully!';
                    }

                    sessionItem.append('<div class="dnd-session-message ' + messageClass + '">' + messageText + '</div>');

                    // Remove the session item after a short delay for cancel action
                    if (action === 'cancel') {
                        setTimeout(function() {
                            sessionItem.fadeOut(300, function() {
                                $(this).remove();

                                // Check if no more sessions
                                if ($('.dnd-session-item').length === 0) {
                                    $('.dnd-sessions-list').html('<div class="dnd-no-sessions">No upcoming sessions</div>');
                                }
                            });
                        }, 1500);
                    } else {
                        // For start action, re-enable buttons after showing message
                        setTimeout(function() {
                            sessionItem.removeClass('processing');
                            sessionItem.find('.dnd-btn').prop('disabled', false);
                        }, 2000);
                    }

                } else {
                    // Show error message
                    sessionItem.append('<div class="dnd-session-message error">' + (response.data || 'An error occurred') + '</div>');

                    // Re-enable buttons
                    sessionItem.removeClass('processing');
                    sessionItem.find('.dnd-btn').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);

                // Show error message
                sessionItem.append('<div class="dnd-session-message error">Network error. Please try again.</div>');

                // Re-enable buttons
                sessionItem.removeClass('processing');
                sessionItem.find('.dnd-btn').prop('disabled', false);
            }
        });
    });
});