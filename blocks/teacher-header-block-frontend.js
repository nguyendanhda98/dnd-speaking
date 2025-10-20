jQuery(document).ready(function($) {
    const $toggle = $('#teacher-status-toggle');
    const $roomLink = $('.room-link');
    const $discordMessage = $('#discord-connect-message');

    if ($toggle.length === 0) return;

    // Check if toggle is disabled (teacher is busy)
    if ($toggle.prop('disabled')) {
        // Prevent any interaction when teacher is busy
        $toggle.on('click', function(e) {
            e.preventDefault();
            alert('Bạn đang trong buổi học. Vui lòng hoàn thành hoặc hủy buổi học hiện tại trước khi thay đổi trạng thái.');
            return false;
        });
        return; // Don't attach other event handlers
    }

    // Handle availability toggle
    $toggle.on('change', function() {
        const isAvailable = $(this).is(':checked');
        const userId = dnd_teacher_data.user_id;

        // Show confirmation dialog
        const confirmMessage = isAvailable 
            ? 'Bạn có chắc muốn chuyển sang trạng thái Online?' 
            : 'Bạn có chắc muốn chuyển sang trạng thái Offline?';
        
        if (!confirm(confirmMessage)) {
            // User cancelled, revert toggle
            $toggle.prop('checked', !isAvailable);
            return;
        }

        // Disable toggle to prevent spam clicks
        $toggle.prop('disabled', true);

        // Show loading message when going online or offline
        if (isAvailable) {
            $discordMessage.html('<span style="color: #0066cc;">⏳ Đang tạo phòng học...</span>');
            $discordMessage.show();
        } else {
            $discordMessage.html('<span style="color: #0066cc;">⏳ Đang xóa phòng...</span>');
            $discordMessage.show();
        }

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
                // Re-enable toggle
                $toggle.prop('disabled', false);
                
                if (response.success) {
                    if (response.data.invite_link) {
                        // Room created successfully
                        $roomLink.attr('href', response.data.invite_link).text('Tham gia phòng');
                        $discordMessage.html('<span style="color: #00aa00;">✓ Tạo phòng thành công!</span>');
                        // Hide success message after 3 seconds
                        setTimeout(function() {
                            $discordMessage.fadeOut();
                        }, 3000);
                    } else {
                        // When going offline, reset link and show success message
                        $roomLink.attr('href', '#').text('Link room');
                        $discordMessage.html('<span style="color: #00aa00;">✓ Xóa phòng thành công!</span>');
                        // Hide success message after 3 seconds
                        setTimeout(function() {
                            $discordMessage.fadeOut();
                        }, 3000);
                    }
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
                        $discordMessage.html('<span style="color: #cc0000;">✗ ' + (response.data ? response.data.message : 'Có lỗi xảy ra. Vui lòng thử lại.') + '</span>');
                        $discordMessage.show();
                    }
                    // Revert toggle on error
                    $toggle.prop('checked', !isAvailable);
                }
            },
            error: function(xhr, status, error) {
                // Re-enable toggle
                $toggle.prop('disabled', false);
                
                console.error('Error updating availability:', error);
                $discordMessage.html('<span style="color: #cc0000;">✗ Có lỗi xảy ra. Vui lòng thử lại.</span>');
                $discordMessage.show();
                // Revert toggle on error
                $toggle.prop('checked', !isAvailable);
            }
        });
    });
});