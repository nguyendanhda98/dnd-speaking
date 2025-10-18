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

        if ($(this).hasClass('dnd-btn-disconnect')) {
            disconnectDiscord();
        } else {
            connectDiscord();
        }
    });

    function connectDiscord() {
        $status.html('<p>Đang kết nối với Discord...</p>');

        // Get Discord auth URL
        $.ajax({
            url: dnd_discord_data.rest_url + 'discord/auth-url',
            method: 'GET',
            headers: {
                'X-WP-Nonce': dnd_discord_data.nonce
            },
            success: function(response) {
                if (response.url) {
                    // Redirect to Discord auth
                    window.location.href = response.url;
                } else {
                    $status.html('<p>Lỗi: Không thể lấy URL xác thực Discord.</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error getting Discord auth URL:', xhr.responseText);
                $status.html('<p>Lỗi khi kết nối Discord. Vui lòng thử lại.</p>');
            }
        });
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
                if (response.success) {
                    $connectBtn.removeClass('dnd-btn-disconnect').addClass('dnd-btn-connect').text('Connect to Discord');
                    $status.html('<p>Đã ngắt kết nối Discord.</p>');
                } else {
                    $status.html('<p>Lỗi khi ngắt kết nối.</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error disconnecting Discord:', xhr.responseText);
                $status.html('<p>Lỗi khi ngắt kết nối Discord.</p>');
            }
        });
    }
});