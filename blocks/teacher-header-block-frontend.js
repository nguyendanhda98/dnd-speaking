jQuery(document).ready(function($) {
    const $toggle = $('#dnd-teacher-available');

    if ($toggle.length === 0) return;

    // Handle availability toggle
    $toggle.on('change', function() {
        const isAvailable = $(this).is(':checked');
        const userId = dnd_teacher_data.user_id;

        $.ajax({
            url: dnd_teacher_data.ajax_url,
            method: 'POST',
            data: {
                action: 'update_teacher_availability',
                user_id: userId,
                available: isAvailable ? 1 : 0,
                nonce: dnd_teacher_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Availability updated successfully');
                } else {
                    console.error('Failed to update availability');
                    // Revert toggle on error
                    $toggle.prop('checked', !isAvailable);
                }
            },
            error: function() {
                console.error('Error updating availability');
                // Revert toggle on error
                $toggle.prop('checked', !isAvailable);
            }
        });
    });
});