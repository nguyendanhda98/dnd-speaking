jQuery(document).ready(function($) {
    const $toggle = $('#teacher-status-toggle');
    const $roomLink = $('.room-link');
    const $discordMessage = $('#discord-connect-message');

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
                    if (response.data.invite_link) {
                        $roomLink.attr('href', response.data.invite_link).text('Tham gia phòng');
                    } else {
                        // When going offline, reset link
                        $roomLink.attr('href', '#').text('Link room');
                    }
                    // Hide discord message when successful
                    $discordMessage.hide();
                    console.log('Availability updated successfully');
                } else {
                    if (response.data && response.data.need_discord) {
                        // Show message and get connect link
                        $discordMessage.html('Bạn chưa kết nối với tài khoản Discord. <a href="#" id="connect-discord-link">Click here to connect</a> để có thể nhận học viên.');
                        $discordMessage.show();
                        
                        // Bind click event for connect link
                        $('#connect-discord-link').off('click').on('click', function(e) {
                            e.preventDefault();
                            if (dnd_teacher_data.discord_auth_url) {
                                window.location.href = dnd_teacher_data.discord_auth_url;
                            } else {
                                alert('Discord auth URL not configured. Please contact administrator.');
                            }
                        });
                        
                        // Revert toggle
                        $toggle.prop('checked', false);
                    } else {
                        alert(response.data ? response.data.message : 'Có lỗi xảy ra. Vui lòng thử lại.');
                    }
                    // Revert toggle on error
                    $toggle.prop('checked', !isAvailable);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating availability:', error);
                alert('Có lỗi xảy ra. Vui lòng thử lại.');
                // Revert toggle on error
                $toggle.prop('checked', !isAvailable);
            }
        });
    });
});