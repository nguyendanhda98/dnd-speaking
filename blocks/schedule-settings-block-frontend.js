/**
 * Schedule Settings Block - Frontend JavaScript
 */

(function($) {
    'use strict';

    console.log('Schedule Settings Frontend JS loaded');

    // Prevent buttons from submitting form
    $(document).on('click', 'button[type="button"]', function(e) {
        e.preventDefault();
        console.log('Button clicked, prevented default');
    });

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

    // Handle add time slot button
    $(document).on('click', '.dnd-add-slot', function(e) {
        console.log('Add Time Slot button clicked');
        e.preventDefault();
        const timeSlotsContainer = $(this).closest('.dnd-time-settings').find('.dnd-time-slots');
        console.log('Time slots container found:', timeSlotsContainer.length);
        const dayKey = $(this).closest('.dnd-day-setting').find('input[type="checkbox"]').attr('name').match(/days\[(\w+)\]/)[1];
        console.log('Day key:', dayKey);
        const slotCount = timeSlotsContainer.find('.dnd-time-slot').length;
        console.log('Current slot count:', slotCount);

        const newSlotHtml = `
            <div class="dnd-time-slot" data-slot-index="${slotCount}">
                <div class="dnd-time-inputs">
                    <label>Start: <input type="time" name="days[${dayKey}][time_slots][${slotCount}][start]" value="09:00" /></label>
                    <label>End: <input type="time" name="days[${dayKey}][time_slots][${slotCount}][end]" value="17:00" /></label>
                    <button type="button" class="dnd-remove-slot">Remove</button>
                </div>
            </div>
        `;

        timeSlotsContainer.find('.dnd-add-slot').before(newSlotHtml);
        console.log('New slot added');
        updateRemoveButtons(timeSlotsContainer);
    });

    // Handle remove time slot button
    $(document).on('click', '.dnd-remove-slot', function(e) {
        console.log('Remove Time Slot button clicked');
        e.preventDefault();
        const timeSlotsContainer = $(this).closest('.dnd-time-slots');
        $(this).closest('.dnd-time-slot').remove();
        updateRemoveButtons(timeSlotsContainer);
        reindexSlots(timeSlotsContainer);
    });

    // Function to update remove button visibility
    function updateRemoveButtons(container) {
        const slots = container.find('.dnd-time-slot');
        const removeButtons = container.find('.dnd-remove-slot');

        if (slots.length > 1) {
            removeButtons.show();
        } else {
            removeButtons.hide();
        }
    }

    // Function to reindex slots after removal
    function reindexSlots(container) {
        const dayKey = container.closest('.dnd-day-setting').find('input[type="checkbox"]').attr('name').match(/days\[(\w+)\]/)[1];

        container.find('.dnd-time-slot').each(function(index) {
            $(this).attr('data-slot-index', index);
            $(this).find('input[name*="time_slots"]').each(function() {
                const name = $(this).attr('name');
                const newName = name.replace(/time_slots\[\d+\]/, `time_slots[${index}]`);
                $(this).attr('name', newName);
            });
        });
    }

    // Handle form submission
    $('#dnd-schedule-form').on('submit', function(e) {
        console.log('Form submitted');
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
            const matches = key.match(/days\[(\w+)\]\[(\w+)\](?:\[(\d+)\])?(?:\[(\w+)\])?/);
            if (matches) {
                const day = matches[1];
                const field = matches[2];
                const slotIndex = matches[3];
                const slotField = matches[4];

                if (!scheduleData[day]) {
                    scheduleData[day] = { time_slots: [] };
                }

                if (field === 'enabled') {
                    scheduleData[day][field] = value === 'on';
                } else if (field === 'time_slots' && slotIndex !== undefined && slotField) {
                    if (!scheduleData[day].time_slots[slotIndex]) {
                        scheduleData[day].time_slots[slotIndex] = {};
                    }
                    scheduleData[day].time_slots[slotIndex][slotField] = value;
                }
            }
        }

        // Clean up time_slots arrays (remove empty slots)
        Object.keys(scheduleData).forEach(day => {
            if (scheduleData[day].time_slots) {
                scheduleData[day].time_slots = scheduleData[day].time_slots.filter(slot => slot && slot.start && slot.end);
            }
        });

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

})(jQuery);
