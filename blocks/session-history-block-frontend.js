jQuery(document).ready(function($) {
    const $historyBlock = $('.dnd-session-history');
    const $historyList = $('#dnd-session-history-list');

    if ($historyBlock.length === 0) return;

    // Check if user is logged in
    if (!dnd_session_history_data.user_id) {
        $historyList.html('<p>Vui lòng đăng nhập để xem lịch sử buổi học.</p>');
        return;
    }

    let currentFilter = $historyList.data('filter') || 'all';
    let currentPerPage = $historyList.data('per-page') || 10;
    let currentPage = $historyList.data('page') || 1;

    // Set initial select value
    $('#session_history_per_page').val(currentPerPage);

    // Initial load
    loadSessionHistory();

    // Handle filter buttons
    $historyBlock.on('click', '.dnd-filter-btn', function() {
        const filter = $(this).data('filter');
        if (filter !== currentFilter) {
            currentFilter = filter;
            currentPage = 1; // Reset to first page
            loadSessionHistory();
        }
    });

    // Handle per page change
    $historyBlock.on('change', '#session_history_per_page', function() {
        currentPerPage = parseInt($(this).val());
        currentPage = 1; // Reset to first page
        loadSessionHistory();
    });

    // Handle pagination
    $historyBlock.on('click', '.dnd-page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && page !== currentPage) {
            currentPage = parseInt(page);
            loadSessionHistory();
        }
    });

    function loadSessionHistory() {
        $historyList.html('<div class="dnd-loading">Loading...</div>');

        $.ajax({
            url: dnd_session_history_data.ajax_url,
            method: 'POST',
            data: {
                action: 'get_session_history',
                filter: currentFilter,
                per_page: currentPerPage,
                page: currentPage,
                nonce: dnd_session_history_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('AJAX Response:', response);
                    console.log('Total sessions:', response.pagination.total_sessions, 'Total pages:', response.pagination.total_pages);
                    $historyList.html(response.html);
                    updateFilterButtons();
                } else {
                    $historyList.html('<p>Có lỗi xảy ra khi tải dữ liệu.</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading session history:', xhr.responseText, status, error);
                $historyList.html('<p>Có lỗi xảy ra khi tải dữ liệu.</p>');
            }
        });
    }

    function updateFilterButtons() {
        $('.dnd-filter-btn').removeClass('active');
        $('.dnd-filter-btn[data-filter="' + currentFilter + '"]').addClass('active');
    }

    // Handle action buttons
    $historyBlock.on('click', '.dnd-btn-confirm', function() {
        const sessionId = $(this).data('session-id');
        if (confirm('Bạn có chắc muốn xác nhận buổi học này?')) {
            updateSessionStatus(sessionId, 'confirmed');
        }
    });

    $historyBlock.on('click', '.dnd-btn-reject', function() {
        const sessionId = $(this).data('session-id');
        if (confirm('Bạn có chắc muốn từ chối buổi học này?')) {
            updateSessionStatus(sessionId, 'cancelled');
        }
    });

    $historyBlock.on('click', '.dnd-btn-cancel', function() {
        const sessionId = $(this).data('session-id');
        if (confirm('Bạn có chắc muốn hủy buổi học này?')) {
            updateSessionStatus(sessionId, 'cancelled');
        }
    });

    $historyBlock.on('click', '.dnd-btn-view', function() {
        const sessionId = $(this).data('session-id');
        // Implement view session details
        alert('Xem chi tiết buổi học: ' + sessionId);
    });

    $historyBlock.on('click', '.dnd-btn-start', function() {
        const sessionId = $(this).data('session-id');
        alert('Bắt đầu buổi học: ' + sessionId);
        // Update status to in_progress
        updateSessionStatus(sessionId, 'in_progress');
    });

    $historyBlock.on('click', '.dnd-btn-end', function() {
        const sessionId = $(this).data('session-id');
        if (confirm('Bạn có chắc muốn kết thúc buổi học này?')) {
            updateSessionStatus(sessionId, 'completed');
        }
    });

    function updateSessionStatus(sessionId, newStatus) {
        $.ajax({
            url: dnd_session_history_data.ajax_url,
            method: 'POST',
            data: {
                action: 'update_session_status',
                session_id: sessionId,
                new_status: newStatus,
                nonce: dnd_session_history_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadSessionHistory(); // Reload after status update
                } else {
                    alert('Không thể cập nhật trạng thái buổi học. Vui lòng thử lại.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating session status:', xhr.responseText, status, error);
                alert('Có lỗi xảy ra khi cập nhật trạng thái buổi học.');
            }
        });
    }
});