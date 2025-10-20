jQuery(document).ready(function($) {
    const $connectBtn = $('#dnd-discord-connect-btn');
    const $status = $('#dnd-discord-status');

    if ($connectBtn.length === 0) return;

    // Check if user is logged in
    if (!dnd_discord_data.user_id) {
        $status.html('<p>Vui lòng đăng nhập để kết nối Discord.</p>');
        return;
    }

    // Bind click event
    $connectBtn.on('click', function(e) {
        e.preventDefault();

        // Disable button to prevent spam
        $connectBtn.prop('disabled', true);

        if ($(this).hasClass('dnd-btn-disconnect')) {
            disconnectDiscord();
        } else {
            connectDiscord();
        }
    });

    function connectDiscord() {
        $status.html('<p>Đang kết nối với Discord...</p>');

        if (dnd_discord_data.discord_auth_url) {
            // Redirect to Discord auth (no need to re-enable button since we're redirecting)
            window.location.href = dnd_discord_data.discord_auth_url;
        } else {
            // Re-enable button on error
            $connectBtn.prop('disabled', false);
            $status.html('<p>Lỗi: URL xác thực Discord chưa được cấu hình.</p>');
        }
    }

    function disconnectDiscord() {
        $status.html('<p>Đang ngắt kết nối...</p>');

        $.ajax({
            url: dnd_discord_data.rest_url + 'discord/disconnect',
            method: 'POST',
            headers: {
                'X-WP-Nonce': dnd_discord_data.nonce
            },
            success: function(response) {
                // Re-enable button
                $connectBtn.prop('disabled', false);
                
                if (response.success) {
                    $connectBtn.removeClass('dnd-btn-disconnect').addClass('dnd-btn-connect').text('Connect to Discord');
                    $status.html('<p>Đã ngắt kết nối Discord.</p>');
                } else {
                    $status.html('<p>Lỗi khi ngắt kết nối.</p>');
                }
            },
            error: function(xhr, status, error) {
                // Re-enable button on error
                $connectBtn.prop('disabled', false);
                
                console.error('Error disconnecting Discord:', xhr.responseText);
                $status.html('<p>Lỗi khi ngắt kết nối Discord.</p>');
            }
        });
    }
});