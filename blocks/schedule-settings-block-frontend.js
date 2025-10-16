/**
 * Schedule Settings Block - Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Toggle time settings visibility when checkbox changes
    $('.dnd-schedule-settings').on('change', '.dnd-day-toggle input[type="checkbox"]', function() {
        const checkbox = $(this);
        const timeSettings = checkbox.closest('.dnd-day-setting').find('.dnd-time-settings');

        if (checkbox.is(':checked')) {
            timeSettings.slideDown(200);
        } else {
            timeSettings.slideUp(200);
        }
    });

    // Handle form submission
    $('#dnd-schedule-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const submitButton = form.find('.dnd-btn-save');
        const messageDiv = $('#dnd-schedule-message');

        // Disable button and show loading state
        submitButton.prop('disabled', true).text('Saving...');

        // Remove any existing messages
        messageDiv.empty();

        // Collect form data
        const formData = new FormData(this);
        const scheduleData = {};

        // Process the form data into our expected structure
        for (let [key, value] of formData.entries()) {
            const matches = key.match(/days\[(\w+)\]\[(\w+)\]/);
            if (matches) {
                const day = matches[1];
                const field = matches[2];

                if (!scheduleData[day]) {
                    scheduleData[day] = {};
                }

                if (field === 'enabled') {
                    scheduleData[day][field] = value === 'on';
                } else {
                    scheduleData[day][field] = value;
                }
            }
        }

        // Send AJAX request
        $.ajax({
            url: dnd_schedule_settings_data.ajax_url,
            type: 'POST',
            data: {
                action: 'save_teacher_schedule',
                schedule_data: JSON.stringify(scheduleData),
                nonce: dnd_schedule_settings_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    messageDiv.html('<div class="dnd-schedule-message success">Schedule saved successfully!</div>');

                    // Re-enable button
                    submitButton.prop('disabled', false).text('Save Schedule');

                } else {
                    // Show error message
                    messageDiv.html('<div class="dnd-schedule-message error">' + (response.data || 'An error occurred while saving') + '</div>');

                    // Re-enable button
                    submitButton.prop('disabled', false).text('Save Schedule');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);

                // Show error message
                messageDiv.html('<div class="dnd-schedule-message error">Network error. Please try again.</div>');

                // Re-enable button
                submitButton.prop('disabled', false).text('Save Schedule');
            }
        });
    });
});