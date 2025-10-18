jQuery(document).ready(function($) {
    const $sessionsBlock = $('.dnd-student-sessions-block');
    const $sessionsList = $('#dnd-student-sessions-list');

    if ($sessionsBlock.length === 0) return;

    // Check if user is logged in
    if (!dnd_speaking_data.user_id) {
        $sessionsList.html('<p>Vui lòng đăng nhập để xem lịch học của bạn.</p>');
        return;
    }

    let currentFilter = $sessionsList.data('filter') || 'all';
    let currentPerPage = $sessionsList.data('per-page') || 10;
    let currentPage = $sessionsList.data('page') || 1;

    // Set initial select value
    $('#student_sessions_per_page').val(currentPerPage);

    // Initial load
    loadSessions();

    // Handle filter buttons
    $sessionsBlock.on('click', '.dnd-filter-btn', function() {
        const filter = $(this).data('filter');
        if (filter !== currentFilter) {
            currentFilter = filter;
            currentPage = 1; // Reset to first page
            loadSessions();
        }
    });

    // Handle per page change
    $sessionsBlock.on('change', '#student_sessions_per_page', function() {
        currentPerPage = parseInt($(this).val());
        currentPage = 1; // Reset to first page
        loadSessions();
    });

    // Handle pagination
    $sessionsBlock.on('click', '.dnd-page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && page !== currentPage) {
            currentPage = parseInt(page);
            loadSessions();
        }
    });

    // Handle cancel buttons
    $sessionsBlock.on('click', '.dnd-btn-cancel', function() {
        const sessionId = $(this).data('session-id');
        if (confirm('Bạn có chắc muốn hủy buổi học này?')) {
            cancelSession(sessionId);
        }
    });

    // Handle join buttons
    $sessionsBlock.on('click', '.dnd-btn-join', function() {
        const sessionId = $(this).data('session-id');
        // Implement join logic, e.g., open meeting link
        alert('Đã click tham gia buổi học');
    });

    // Handle rate buttons
    $sessionsBlock.on('click', '.dnd-btn-rate', function() {
        const sessionId = $(this).data('session-id');
        // Implement rating modal or redirect
        alert('Đánh giá buổi học: ' + sessionId);
    });

    // Handle feedback buttons
    $sessionsBlock.on('click', '.dnd-btn-feedback', function() {
        const sessionId = $(this).data('session-id');
        // Implement view feedback logic
        alert('Xem phản hồi giáo viên: ' + sessionId);
    });

    function loadSessions() {
        $sessionsList.html('<div class="dnd-loading">Loading...</div>');

        $.ajax({
            url: dnd_speaking_data.ajax_url,
            method: 'POST',
            data: {
                action: 'get_student_sessions',
                filter: currentFilter,
                per_page: currentPerPage,
                page: currentPage,
                nonce: dnd_speaking_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('AJAX Response:', response);
                    console.log('Total sessions:', response.pagination.total_sessions, 'Total pages:', response.pagination.total_pages);
                    $sessionsList.html(response.html);
                    updateFilterButtons();
                } else {
                    $sessionsList.html('<p>Có lỗi xảy ra khi tải dữ liệu.</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading sessions:', xhr.responseText, status, error);
                $sessionsList.html('<p>Có lỗi xảy ra khi tải dữ liệu.</p>');
            }
        });
    }

    function updateFilterButtons() {
        $('.dnd-filter-btn').removeClass('active');
        $('.dnd-filter-btn[data-filter="' + currentFilter + '"]').addClass('active');
    }

    function cancelSession(sessionId) {
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
                    loadSessions(); // Reload after cancel
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