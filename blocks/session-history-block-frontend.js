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
    let currentFilterDay = $historyList.data('filter-day') || '';
    let currentFilterMonth = $historyList.data('filter-month') || '';
    let currentFilterYear = $historyList.data('filter-year') || '';
    let currentPerPage = $historyList.data('per-page') || 10;
    let currentPage = $historyList.data('page') || 1;

    // Set initial select values
    $('#session_history_per_page').val(currentPerPage);
    $('#filter_day').val(currentFilterDay);
    $('#filter_month').val(currentFilterMonth);
    $('#filter_year').val(currentFilterYear);

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

    // Handle apply time filter button
    $historyBlock.on('click', '#apply_time_filter', function() {
        let day = $('#filter_day').val();
        let month = $('#filter_month').val();
        let year = $('#filter_year').val();
        
        // Validate day (0 < day < 32)
        if (day !== '' && day !== null) {
            day = parseInt(day);
            if (day <= 0 || day >= 32) {
                alert('Ngày phải từ 1 đến 31');
                return;
            }
            day = day.toString();
        } else {
            day = '';
        }
        
        // Validate month (0 < month < 13)
        if (month !== '' && month !== null) {
            month = parseInt(month);
            if (month <= 0 || month >= 13) {
                alert('Tháng phải từ 1 đến 12');
                return;
            }
            month = month.toString();
        } else {
            month = '';
        }
        
        // Validate year (year >= 2025)
        if (year !== '' && year !== null) {
            year = parseInt(year);
            if (year < 2025) {
                alert('Năm phải từ 2025 trở lên');
                return;
            }
            year = year.toString();
        } else {
            year = '';
        }
        
        currentFilterDay = day;
        currentFilterMonth = month;
        currentFilterYear = year;
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
                filter_day: currentFilterDay,
                filter_month: currentFilterMonth,
                filter_year: currentFilterYear,
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
                    
                    // Update filter button counts if provided
                    if (response.filter_counts) {
                        updateFilterCounts(response.filter_counts);
                    }
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

    function updateFilterCounts(counts) {
        // Update the count display in each filter button
        $('.dnd-filter-btn').each(function() {
            const filter = $(this).data('filter');
            if (counts[filter] !== undefined) {
                const label = $(this).text().replace(/\(\d+\)/, '').trim();
                $(this).text(label + ' (' + counts[filter] + ')');
            }
        });
    }

    // Handle action buttons
    $historyBlock.on('click', '.dnd-btn-confirm', function() {
        const $button = $(this);
        const sessionId = $button.data('session-id');
        
        if (confirm('Bạn có chắc muốn xác nhận buổi học này?')) {
            // Find the parent session item to disable all action buttons
            const $sessionItem = $button.closest('.dnd-history-item');
            
            // Disable ALL action buttons in this session
            $sessionItem.find('.dnd-btn-confirm, .dnd-btn-reject').prop('disabled', true);
            
            // Update button text
            $button.text('Đang xử lý...');
            
            updateSessionStatus(sessionId, 'confirmed', $button, $sessionItem);
        }
    });

    $historyBlock.on('click', '.dnd-btn-reject', function() {
        const $button = $(this);
        const sessionId = $button.data('session-id');
        
        if (confirm('Bạn có chắc muốn từ chối buổi học này?')) {
            // Find the parent session item to disable all action buttons
            const $sessionItem = $button.closest('.dnd-history-item');
            
            // Disable ALL action buttons in this session
            $sessionItem.find('.dnd-btn-confirm, .dnd-btn-reject').prop('disabled', true);
            
            // Update button text
            $button.text('Đang xử lý...');
            
            updateSessionStatus(sessionId, 'cancelled', $button, $sessionItem);
        }
    });

    $historyBlock.on('click', '.dnd-btn-cancel', function() {
        const $button = $(this);
        const sessionId = $button.data('session-id');
        
        if (confirm('Bạn có chắc muốn hủy buổi học này?')) {
            // Find the parent session item to disable all action buttons
            const $sessionItem = $button.closest('.dnd-history-item');
            
            // Disable ALL action buttons in this session (including start button if present)
            $sessionItem.find('.dnd-btn-start, .dnd-btn-cancel, .dnd-btn-end').prop('disabled', true);
            
            // Update button text
            $button.text('Đang xử lý...');
            
            updateSessionStatus(sessionId, 'cancelled', $button, $sessionItem);
        }
    });

    $historyBlock.on('click', '.dnd-btn-cancel-session', function() {
        const $button = $(this);
        const sessionId = $button.data('session-id');
        
        if (confirm('Bạn có chắc muốn hủy buổi học này? Phòng học sẽ bị xóa.')) {
            // Find the parent session item to disable all action buttons
            const $sessionItem = $button.closest('.dnd-history-item');
            
            // Disable ALL action buttons in this session
            $sessionItem.find('.dnd-btn-join, .dnd-btn-complete, .dnd-btn-cancel-session').prop('disabled', true);
            
            // Update button text
            $button.text('Đang xử lý...');
            
            updateSessionStatus(sessionId, 'cancelled', $button, $sessionItem);
        }
    });

    $historyBlock.on('click', '.dnd-btn-complete', function() {
        const $button = $(this);
        const sessionId = $button.data('session-id');
        
        if (confirm('Bạn có chắc muốn hoàn thành buổi học này? Phòng học sẽ bị xóa.')) {
            // Find the parent session item to disable all action buttons
            const $sessionItem = $button.closest('.dnd-history-item');
            
            // Disable ALL action buttons in this session
            $sessionItem.find('.dnd-btn-join, .dnd-btn-complete, .dnd-btn-cancel-session').prop('disabled', true);
            
            // Update button text
            $button.text('Đang xử lý...');
            
            updateSessionStatus(sessionId, 'completed', $button, $sessionItem);
        }
    });

    $historyBlock.on('click', '.dnd-btn-view', function() {
        const sessionId = $(this).data('session-id');
        // Implement view session details
        alert('Xem chi tiết buổi học: ' + sessionId);
    });

    $historyBlock.on('click', '.dnd-btn-start', function() {
        const $button = $(this);
        const sessionId = $button.data('session-id');
        const studentId = $button.data('student-id');
        
        if (confirm('Bạn có chắc muốn bắt đầu buổi học này?')) {
            // Find the parent session item to disable all action buttons
            const $sessionItem = $button.closest('.dnd-history-item');
            
            // Disable ALL buttons in this session (both start and cancel)
            $sessionItem.find('.dnd-btn-start, .dnd-btn-cancel').prop('disabled', true);
            
            // Update button text to show loading
            $button.text('Đang tạo phòng học...');
            
            // Call REST API endpoint to start session
            $.ajax({
                url: dnd_session_history_data.rest_url + 'teacher/start-session',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', dnd_session_history_data.nonce);
                },
                data: JSON.stringify({
                    session_id: sessionId
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.success) {
                        alert('Phòng học đã được tạo thành công! Bạn có thể tham gia ngay.');
                        // Reload the session history to show the join button
                        loadSessionHistory();
                    } else {
                        // Re-enable buttons on error
                        $sessionItem.find('.dnd-btn-start, .dnd-btn-cancel').prop('disabled', false);
                        $button.text('Bắt đầu');
                        
                        const errorMsg = response.message || 'Không thể bắt đầu buổi học. Vui lòng thử lại.';
                        alert(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    // Re-enable buttons on error
                    $sessionItem.find('.dnd-btn-start, .dnd-btn-cancel').prop('disabled', false);
                    $button.text('Bắt đầu');
                    
                    console.error('Error starting session:', xhr.responseText, status, error);
                    
                    // Try to parse error message from response
                    let errorMsg = 'Có lỗi xảy ra khi bắt đầu buổi học.';
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        // WP REST API returns errors in 'message' property
                        if (errorResponse.message) {
                            errorMsg = errorResponse.message;
                        } else if (errorResponse.data && errorResponse.data.message) {
                            errorMsg = errorResponse.data.message;
                        }
                    } catch (e) {
                        // Keep default error message
                    }
                    
                    alert(errorMsg);
                    // Reload session history to reflect any status changes
                    loadSessionHistory();
                }
            });
        }
    });

    $historyBlock.on('click', '.dnd-btn-end', function() {
        const $button = $(this);
        const sessionId = $button.data('session-id');
        
        if (confirm('Bạn có chắc muốn kết thúc buổi học này?')) {
            // Find the parent session item to disable all action buttons
            const $sessionItem = $button.closest('.dnd-history-item');
            
            // Disable ALL action buttons in this session
            $sessionItem.find('.dnd-btn-cancel, .dnd-btn-end').prop('disabled', true);
            
            // Update button text
            $button.text('Đang xử lý...');
            
            updateSessionStatus(sessionId, 'completed', $button, $sessionItem);
        }
    });

    function updateSessionStatus(sessionId, newStatus, $button, $sessionItem) {
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
                    // If we cancelled or completed an in-progress session, reload the entire page to refresh teacher status block
                    if (response.data && response.data.room_cleared) {
                        if (newStatus === 'cancelled') {
                            alert('Buổi học đã được hủy. Phòng học đã được xóa và trạng thái của bạn đã được cập nhật về Offline.');
                        } else if (newStatus === 'completed') {
                            alert('Buổi học đã hoàn thành. Phòng học đã được xóa và trạng thái của bạn đã được cập nhật về Offline.');
                        }
                        location.reload();
                    } else {
                        loadSessionHistory(); // Reload after status update
                    }
                } else {
                    // Re-enable buttons on error
                    if ($sessionItem) {
                        // Re-enable the appropriate buttons based on status being attempted
                        if (newStatus === 'confirmed' || (newStatus === 'cancelled' && $button && $button.hasClass('dnd-btn-reject'))) {
                            $sessionItem.find('.dnd-btn-confirm, .dnd-btn-reject').prop('disabled', false);
                        } else if (newStatus === 'cancelled' && $button && $button.hasClass('dnd-btn-cancel-session')) {
                            $sessionItem.find('.dnd-btn-join, .dnd-btn-complete, .dnd-btn-cancel-session').prop('disabled', false);
                        } else if (newStatus === 'completed' && $button && $button.hasClass('dnd-btn-complete')) {
                            $sessionItem.find('.dnd-btn-join, .dnd-btn-complete, .dnd-btn-cancel-session').prop('disabled', false);
                        } else if (newStatus === 'cancelled' && $button && $button.hasClass('dnd-btn-cancel')) {
                            // Re-enable both start and cancel buttons for confirmed sessions
                            $sessionItem.find('.dnd-btn-start, .dnd-btn-cancel, .dnd-btn-end').prop('disabled', false);
                        } else {
                            $sessionItem.find('.dnd-btn-cancel, .dnd-btn-end').prop('disabled', false);
                        }
                    } else if ($button) {
                        $button.prop('disabled', false);
                    }
                    
                    // Restore original text based on status
                    if ($button) {
                        if (newStatus === 'cancelled') {
                            // Could be from reject, cancel, or cancel-session button
                            if ($button.hasClass('dnd-btn-reject')) {
                                $button.text('Từ chối');
                            } else if ($button.hasClass('dnd-btn-cancel-session')) {
                                $button.text('Hủy');
                            } else {
                                $button.text('Hủy buổi học');
                            }
                        } else if (newStatus === 'completed') {
                            if ($button.hasClass('dnd-btn-complete')) {
                                $button.text('Hoàn thành');
                            } else {
                                $button.text('Kết thúc');
                            }
                        } else if (newStatus === 'confirmed') {
                            $button.text('Xác nhận');
                        }
                    }
                    // wp_send_json_error() returns data directly as string, not as data.message
                    var errorMessage = 'Không thể cập nhật trạng thái buổi học. Vui lòng thử lại.';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    alert(errorMessage);
                    // Reload session history after showing error (to reflect cancelled status)
                    loadSessionHistory();
                }
            },
            error: function(xhr, status, error) {
                // Re-enable buttons on error
                if ($sessionItem) {
                    // Re-enable the appropriate buttons based on status being attempted
                    if (newStatus === 'confirmed' || (newStatus === 'cancelled' && $button && $button.hasClass('dnd-btn-reject'))) {
                        $sessionItem.find('.dnd-btn-confirm, .dnd-btn-reject').prop('disabled', false);
                    } else if (newStatus === 'cancelled' && $button && $button.hasClass('dnd-btn-cancel-session')) {
                        $sessionItem.find('.dnd-btn-join, .dnd-btn-complete, .dnd-btn-cancel-session').prop('disabled', false);
                    } else if (newStatus === 'completed' && $button && $button.hasClass('dnd-btn-complete')) {
                        $sessionItem.find('.dnd-btn-join, .dnd-btn-complete, .dnd-btn-cancel-session').prop('disabled', false);
                    } else if (newStatus === 'cancelled' && $button && $button.hasClass('dnd-btn-cancel')) {
                        // Re-enable both start and cancel buttons for confirmed sessions
                        $sessionItem.find('.dnd-btn-start, .dnd-btn-cancel, .dnd-btn-end').prop('disabled', false);
                    } else {
                        $sessionItem.find('.dnd-btn-cancel, .dnd-btn-end').prop('disabled', false);
                    }
                } else if ($button) {
                    $button.prop('disabled', false);
                }
                
                // Restore original text based on status
                if ($button) {
                    if (newStatus === 'cancelled') {
                        // Could be from reject, cancel, or cancel-session button
                        if ($button.hasClass('dnd-btn-reject')) {
                            $button.text('Từ chối');
                        } else if ($button.hasClass('dnd-btn-cancel-session')) {
                            $button.text('Hủy');
                        } else {
                            $button.text('Hủy buổi học');
                        }
                    } else if (newStatus === 'completed') {
                        if ($button.hasClass('dnd-btn-complete')) {
                            $button.text('Hoàn thành');
                        } else {
                            $button.text('Kết thúc');
                        }
                    } else if (newStatus === 'confirmed') {
                        $button.text('Xác nhận');
                    }
                }
                console.error('Error updating session status:', xhr.responseText, status, error);
                alert('Có lỗi xảy ra khi cập nhật trạng thái buổi học.');
            }
        });
    }
});