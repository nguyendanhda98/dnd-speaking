jQuery(document).ready(function($) {
    const $sessionsList = $('#dnd-student-sessions-list');

    if ($sessionsList.length === 0) return;

    // Check if user is logged in
    if (!dnd_speaking_data.user_id) {
        $sessionsList.html('<p>Vui lòng đăng nhập để xem lịch học của bạn.</p>');
        return;
    }

    // Function to fetch and render student sessions
    function fetchStudentSessions() {
        $.ajax({
            url: dnd_speaking_data.rest_url + 'student-sessions',
            method: 'GET',
            headers: {
                'X-WP-Nonce': dnd_speaking_data.nonce
            },
            success: function(sessions) {
                console.log('Student sessions loaded:', sessions); // Debug log
                renderSessions(sessions);
            },
            error: function(xhr, status, error) {
                console.error('Error loading student sessions:', xhr.responseText, status, error); // Debug log
                $sessionsList.html('<p>Không thể tải lịch học. Vui lòng kiểm tra console để biết chi tiết.</p>');
            }
        });
    }

    // Initial load
    fetchStudentSessions();

    // Expose refresh function globally
    window.refreshStudentSessions = fetchStudentSessions;

    function renderSessions(sessions) {
        if (sessions.length === 0) {
            $sessionsList.html('<p>Bạn chưa có buổi học nào sắp tới.</p>');
            return;
        }

        const html = sessions.map(session => {
            const statusText = session.status === 'pending' ? 'Chờ xác nhận' : 'Đã xác nhận';
            const statusClass = session.status === 'pending' ? 'pending' : 'confirmed';
            const scheduledTime = new Date(session.scheduled_time).toLocaleString('vi-VN');

            return `
                <div class="dnd-session-card">
                    <div class="dnd-session-teacher">Giáo viên: ${session.teacher_name}</div>
                    <div class="dnd-session-status ${statusClass}">Trạng thái: ${statusText}</div>
                    <div class="dnd-session-time">Thời gian: ${scheduledTime}</div>
                    <button class="dnd-btn dnd-btn-cancel" data-session-id="${session.id}">
                        Hủy buổi học
                    </button>
                </div>
            `;
        }).join('');

        $sessionsList.html(html);

        // Add event listeners for cancel buttons
        $('.dnd-btn-cancel').on('click', function() {
            const sessionId = $(this).data('session-id');
            if (confirm('Bạn có chắc muốn hủy buổi học này?')) {
                cancelSession(sessionId, $(this).closest('.dnd-session-card'));
            }
        });
    }

    function cancelSession(sessionId, $card) {
        $.ajax({
            url: dnd_speaking_data.rest_url + 'cancel-session',
            method: 'POST',
            headers: {
                'X-WP-Nonce': dnd_speaking_data.nonce
            },
            data: {
                session_id: sessionId
            },
            success: function(response) {
                if (response.success) {
                    $card.fadeOut(300, function() {
                        $(this).remove();
                        if ($('.dnd-session-card').length === 0) {
                            $sessionsList.html('<p>Bạn chưa có buổi học nào sắp tới.</p>');
                        }
                    });
                } else {
                    alert('Không thể hủy buổi học. Vui lòng thử lại.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error canceling session:', xhr.responseText, status, error);
                alert('Có lỗi xảy ra khi hủy buổi học.');
            }
        });
    }
});