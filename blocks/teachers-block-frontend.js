jQuery(document).ready(function($) {
    const $teachersList = $('#dnd-teachers-list');

    if ($teachersList.length === 0) return;

    // Check if user is logged in
    if (!dnd_speaking_data.user_id) {
        $teachersList.html('<p>Vui lòng đăng nhập để xem danh sách giáo viên.</p>');
        return;
    }

    // Function to load teachers with optional filters
    function loadTeachers(filters = {}) {
        let url = dnd_speaking_data.rest_url + 'teachers';
        
        // Build query parameters if filters are provided
        if (filters.days && filters.days.length > 0) {
            url += '?' + filters.days.map((day, index) => `days[${index}]=${day}`).join('&');
            if (filters.start_time && filters.end_time) {
                url += `&start_time=${filters.start_time}&end_time=${filters.end_time}`;
            }
        }

        $.ajax({
            url: url,
            method: 'GET',
            headers: {
                'X-WP-Nonce': dnd_speaking_data.nonce
            },
            success: function(teachers) {
                console.log('Teachers loaded:', teachers);
                if (teachers.length === 0) {
                    $teachersList.html('<p class="dnd-no-results">Không tìm thấy giáo viên phù hợp với bộ lọc.</p>');
                } else {
                    renderTeachers(teachers);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading teachers:', xhr.responseText, status, error);
                $teachersList.html('<p>Unable to load teachers. Please check console for details.</p>');
            }
        });
    }

    // Handle filter apply button
    $('#dnd-apply-filter').on('click', function() {
        const selectedDays = [];
        $('.dnd-day-input:checked').each(function() {
            selectedDays.push($(this).val());
        });

        const startTime = $('#dnd-start-time').val();
        const endTime = $('#dnd-end-time').val();

        // Validate time range
        if (selectedDays.length > 0 && startTime && endTime) {
            if (startTime >= endTime) {
                alert('Thời gian bắt đầu phải nhỏ hơn thời gian kết thúc!');
                return;
            }
        }

        // Apply filters
        const filters = {};
        if (selectedDays.length > 0) {
            filters.days = selectedDays;
            filters.start_time = startTime;
            filters.end_time = endTime;
        }

        loadTeachers(filters);
    });

    // Handle filter reset button
    $('#dnd-reset-filter').on('click', function() {
        // Uncheck all day checkboxes
        $('.dnd-day-input').prop('checked', false);
        
        // Reset time selectors to default
        $('#dnd-start-time').val('00:00');
        $('#dnd-end-time').val('23:30');
        
        // Load all teachers without filters
        loadTeachers();
    });

    // Initial load of teachers
    loadTeachers();

    // Bind book now event handler immediately
    $(document).on('click', '.dnd-btn-book', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Book now clicked for teacher:', $(this).data('teacher-id'));
        
        const $button = $(this);
        const $card = $button.closest('.dnd-teacher-card');
        const teacherId = $button.data('teacher-id');
        const teacherName = $card.find('.dnd-teacher-name').text();
        
        // Disable both buttons immediately to prevent spam
        const $allButtons = $card.find('.dnd-btn');
        $allButtons.prop('disabled', true);
        
        // Show loading state on clicked button
        const originalHtml = $button.html();
        $button.html('<span class="dnd-btn-loading"></span> Đang xử lý...');
        
        // Check Discord connection first, then credits before opening booking modal
        checkDiscordAndProceed(function() {
            // Discord connected, now check credits
            checkCreditsAndProceed(function() {
                // Re-enable buttons after credit check passes
                $allButtons.prop('disabled', false);
                $button.html(originalHtml);
                openBookingModal(teacherId, teacherName);
            }, function() {
                // Re-enable buttons if credit check fails
                $allButtons.prop('disabled', false);
                $button.html(originalHtml);
            });
        }, function() {
            // Re-enable buttons if Discord check fails or user cancels
            $allButtons.prop('disabled', false);
            $button.html(originalHtml);
        });
        return false;
    });

    function renderTeachers(teachers) {
        const html = teachers.map(teacher => {
            let statusText = 'Offline';
            let statusClass = '';
            let showStartNow = false;
            
            if (teacher.status === '1') {
                statusText = 'Online';
                statusClass = 'online';
                showStartNow = true;
            } else if (teacher.status === 'busy') {
                statusText = 'Busy';
                statusClass = 'busy';
                showStartNow = false;
            }
            
            // Generate YouTube embed or placeholder at the top
            let videoSection = '';
            if (teacher.youtube_video_id) {
                videoSection = `
                    <div class="dnd-teacher-video">
                        <div class="dnd-video-wrapper">
                            <iframe 
                                src="https://www.youtube.com/embed/${teacher.youtube_video_id}" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                `;
            } else {
                // Placeholder if no video
                videoSection = `<div class="dnd-teacher-video-placeholder"></div>`;
            }
            
            // Get first letter of name for avatar
            const avatarLetter = teacher.name.charAt(0).toUpperCase();
            
            return `
            <div class="dnd-teacher-card">
                ${videoSection}
                <div class="dnd-teacher-info">
                    <div class="dnd-teacher-header">
                        <div class="dnd-teacher-avatar">${avatarLetter}</div>
                        <div class="dnd-teacher-details">
                            <div class="dnd-teacher-name">${teacher.name}</div>
                            <div class="dnd-teacher-status ${statusClass}">
                                ${statusText}
                            </div>
                        </div>
                    </div>
                    <div class="dnd-teacher-buttons">
                        ${showStartNow ? `
                            <button class="dnd-btn dnd-btn-start" type="button" data-teacher-id="${teacher.id}">
                                <svg class="dnd-btn-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                                </svg>
                                Học ngay
                            </button>
                        ` : ''}
                        <button class="dnd-btn dnd-btn-book" type="button" data-teacher-id="${teacher.id}">
                            <svg class="dnd-btn-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z"/>
                            </svg>
                            Đặt lịch
                        </button>
                    </div>
                </div>
            </div>
        `;
        }).join('');

        $teachersList.html(html);

        // Add booking modal
        if (!$('#dnd-booking-modal').length) {
            $('body').append(`
                <div id="dnd-booking-modal" class="dnd-booking-modal">
                    <div class="dnd-modal-content">
                        <div class="dnd-modal-header">
                            <h2 class="dnd-modal-title">Book Session</h2>
                            <button class="dnd-modal-close">&times;</button>
                        </div>
                        <div id="dnd-availability-slots"></div>
                        <button id="dnd-confirm-booking" class="dnd-book-btn">Select a time slot</button>
                    </div>
                </div>
            `);
        }

        // Handle button clicks - moved outside renderTeachers for better event delegation
        // $('.dnd-btn-book').on('click', function(e) {
        //     e.preventDefault();
        //     console.log('Book now clicked for teacher:', $(this).data('teacher-id'));
        //     const teacherId = $(this).data('teacher-id');
        //     const teacherName = $(this).closest('.dnd-teacher-card').find('.dnd-teacher-name').text();
        //     openBookingModal(teacherId, teacherName);
        // });

        $('.dnd-btn-start').on('click', function() {
            const $button = $(this);
            const $card = $button.closest('.dnd-teacher-card');
            const teacherId = $button.data('teacher-id');
            const teacherName = $card.find('.dnd-teacher-name').text();
            
            // Disable both buttons immediately to prevent spam
            const $allButtons = $card.find('.dnd-btn');
            $allButtons.prop('disabled', true);
            
            // Show loading state on clicked button
            const originalHtml = $button.html();
            $button.html('<span class="dnd-btn-loading"></span> Đang xử lý...');
            
            // Check Discord connection first, then credits before starting session
            checkDiscordAndProceed(function() {
                // Discord connected, now check credits
                checkCreditsAndProceed(function() {
                    // Keep buttons disabled and continue with start now
                    startNowSession(teacherId, teacherName, $button, $allButtons, originalHtml);
                }, function() {
                    // Re-enable buttons if credit check fails
                    $allButtons.prop('disabled', false);
                    $button.html(originalHtml);
                });
            }, function() {
                // Re-enable buttons if Discord check fails or user cancels
                $allButtons.prop('disabled', false);
                $button.html(originalHtml);
            });
        });
    }

    function startNowSession(teacherId, teacherName, $button, $allButtons, originalHtml) {
        // Show confirmation dialog
        if (!confirm(`Bạn có muốn bắt đầu phiên học với ${teacherName} ngay bây giờ không?`)) {
            // User cancelled, re-enable buttons
            if ($allButtons) {
                $allButtons.prop('disabled', false);
            }
            if ($button) {
                $button.html(originalHtml);
            }
            return;
        }

        // Show loading state
        const $loadingModal = $('<div id="dnd-loading-modal" class="dnd-booking-modal show"><div class="dnd-modal-content"><p>Đang kết nối...</p></div></div>');
        $('body').append($loadingModal);

        $.ajax({
            url: dnd_speaking_data.rest_url + 'student/start-now',
            method: 'POST',
            headers: {
                'X-WP-Nonce': dnd_speaking_data.nonce
            },
            data: {
                teacher_id: teacherId
            },
            success: function(response) {
                $loadingModal.remove();
                
                if (response.success) {
                    // Successfully created session, redirect directly to Discord room
                    window.location.href = response.room_link;
                } else if (response.teacher_not_available) {
                    // Teacher is offline or busy - re-enable buttons
                    if ($allButtons) {
                        $allButtons.prop('disabled', false);
                    }
                    if ($button) {
                        $button.html(originalHtml);
                    }
                    alert(response.message);
                } else if (response.need_discord_connection) {
                    // Need to connect Discord first - re-enable buttons
                    if ($allButtons) {
                        $allButtons.prop('disabled', false);
                    }
                    if ($button) {
                        $button.html(originalHtml);
                    }
                    if (confirm(response.message + '\n\nBạn có muốn kết nối Discord ngay bây giờ không?')) {
                        window.location.href = response.discord_auth_url;
                    }
                } else if (response.has_active_session) {
                    // Already has an active session
                    if (confirm(response.message)) {
                        // Redirect to existing room
                        window.location.href = response.room_link;
                    } else {
                        // User doesn't want to join existing room - re-enable buttons
                        if ($allButtons) {
                            $allButtons.prop('disabled', false);
                        }
                        if ($button) {
                            $button.html(originalHtml);
                        }
                    }
                } else {
                    // Other error - re-enable buttons
                    if ($allButtons) {
                        $allButtons.prop('disabled', false);
                    }
                    if ($button) {
                        $button.html(originalHtml);
                    }
                    alert(response.message || 'Có lỗi xảy ra, vui lòng thử lại.');
                }
            },
            error: function(xhr) {
                $loadingModal.remove();
                
                // Re-enable buttons on error
                if ($allButtons) {
                    $allButtons.prop('disabled', false);
                }
                if ($button) {
                    $button.html(originalHtml);
                }
                
                const errorMessage = xhr.responseJSON && xhr.responseJSON.message 
                    ? xhr.responseJSON.message 
                    : 'Có lỗi xảy ra khi tạo phiên học.';
                alert(errorMessage);
            }
        });
    }

    function startSession(teacherId) {
        const studentId = dnd_speaking_data.user_id;
        $.ajax({
            url: dnd_speaking_data.rest_url + 'start-session',
            method: 'POST',
            data: {
                student_id: studentId,
                teacher_id: teacherId,
                discord_channel: 'general' // Or get from somewhere
            },
            success: function(response) {
                if (response.success) {
                    alert('Session started! Session ID: ' + response.session_id);
                    // Redirect to session page or refresh
                } else {
                    alert('Failed to start session');
                }
            },
            error: function(xhr) {
                alert('Error: ' + xhr.responseJSON.message);
            }
        });
    }

    function openBookingModal(teacherId, teacherName) {
        $('#dnd-booking-modal .dnd-modal-title').text('Book Session with ' + teacherName);
        $('#dnd-availability-slots').html('<p>Loading availability...</p>');
        
        // Show button but keep it disabled until slot is selected
        $('#dnd-confirm-booking').show().prop('disabled', true).text('Select a time slot');

        // Fetch availability
        $.ajax({
            url: dnd_speaking_data.rest_url + 'teacher-availability/' + teacherId,
            method: 'GET',
            headers: {
                'X-WP-Nonce': dnd_speaking_data.nonce
            },
            success: function(slots) {
                renderAvailabilitySlots(slots, teacherId);
            },
            error: function(xhr) {
                $('#dnd-availability-slots').html('<p>Error loading availability.</p>');
            }
        });

        $('#dnd-booking-modal').addClass('show');
    }

    function renderAvailabilitySlots(slots, teacherId) {
        if (slots.length === 0) {
            $('#dnd-availability-slots').html('<p>Teacher has not set their availability schedule yet.</p>');
            return;
        }

        const groupedSlots = {};
        slots.forEach(slot => {
            const date = slot.date;
            if (!groupedSlots[date]) {
                groupedSlots[date] = [];
            }
            groupedSlots[date].push(slot);
        });

        let html = '';
        Object.keys(groupedSlots).sort().forEach(date => {
            const dateObj = new Date(date);
            const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
            const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            html += `<h3>${dayName}, ${formattedDate}</h3>`;
            html += '<div class="dnd-availability-grid">';
            groupedSlots[date].forEach(slot => {
                // Convert 24-hour time to 12-hour format with AM/PM
                const time24 = slot.time;
                const [hours, minutes] = time24.split(':');
                const hour12 = hours % 12 || 12;
                const ampm = hours < 12 ? 'AM' : 'PM';
                const time12 = `${hour12}:${minutes} ${ampm}`;
                
                html += `
                    <div class="dnd-time-slot available" data-datetime="${slot.datetime}">
                        ${time12}
                    </div>
                `;
            });
            html += '</div>';
        });

        $('#dnd-availability-slots').html(html);

        // Handle slot selection
        let selectedSlot = null;
        $('.dnd-time-slot.available').on('click', function() {
            $('.dnd-time-slot').removeClass('selected');
            $(this).addClass('selected');
            selectedSlot = $(this).data('datetime');
            
            // Enable button and change text when slot is selected
            $('#dnd-confirm-booking').prop('disabled', false).text('Book Selected Slot');
        });

        // Handle booking confirmation
        $('#dnd-confirm-booking').off('click').on('click', function() {
            const $button = $(this);
            if (selectedSlot) {
                // Disable button to prevent spam
                $button.prop('disabled', true).text('Đang đặt lịch...');
                bookSession(teacherId, selectedSlot, $button);
            }
        });
    }

    function bookSession(teacherId, datetime, $button) {
        const studentId = dnd_speaking_data.user_id;
        $.ajax({
            url: dnd_speaking_data.rest_url + 'book-session',
            method: 'POST',
            headers: {
                'X-WP-Nonce': dnd_speaking_data.nonce
            },
            data: {
                student_id: studentId,
                teacher_id: teacherId,
                start_time: datetime
            },
            success: function(response) {
                if (response.success) {
                    alert('Session booked successfully!');
                    $('#dnd-booking-modal').removeClass('show');
                    // Reset button state
                    if ($button) {
                        $button.prop('disabled', true).text('Select a time slot');
                    }
                    // Refresh upcoming sessions if needed
                    if (typeof window.refreshStudentSessions === 'function') {
                        window.refreshStudentSessions();
                    }
                } else {
                    // Re-enable button on error
                    if ($button) {
                        $button.prop('disabled', false).text('Book Selected Slot');
                    }
                    alert('Failed to book session: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr) {
                // Re-enable button on error
                if ($button) {
                    $button.prop('disabled', false).text('Book Selected Slot');
                }
                alert('Error booking session: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error'));
            }
        });
    }

    // Close modal
    $(document).on('click', '.dnd-modal-close', function() {
        $('#dnd-booking-modal').removeClass('show');
    });

    $(document).on('click', '#dnd-booking-modal', function(e) {
        if (e.target === this) {
            $(this).removeClass('show');
        }
    });

    // Event delegation for book now button - REMOVED, moved to top
    // $(document).off('click', '.dnd-btn-book').on('click', '.dnd-btn-book', function(e) {
    //     e.preventDefault();
    //     alert('Book now clicked!'); // Debug alert
    //     console.log('Book now clicked for teacher:', $(this).data('teacher-id'));
    //     const teacherId = $(this).data('teacher-id');
    //     const teacherName = $(this).closest('.dnd-teacher-card').find('.dnd-teacher-name').text();
    //     openBookingModal(teacherId, teacherName);
    // });

    // Check credits before proceeding with booking or starting session
    function checkCreditsAndProceed(successCallback, failCallback) {
        $.ajax({
            url: dnd_speaking_data.rest_url + 'credits',
            method: 'GET',
            headers: {
                'X-WP-Nonce': dnd_speaking_data.nonce
            },
            success: function(response) {
                if (response.credits && response.credits > 0) {
                    // Has credits, proceed
                    successCallback();
                } else {
                    // No credits
                    alert('Bạn không đủ số buổi học. Vui lòng nạp thêm để tiếp tục.');
                    if (failCallback) {
                        failCallback();
                    }
                }
            },
            error: function(xhr) {
                console.error('Error checking lessons:', xhr);
                alert('Không thể kiểm tra số buổi học. Vui lòng thử lại.');
                if (failCallback) {
                    failCallback();
                }
            }
        });
    }

    // Check Discord connection before proceeding with booking or starting session
    function checkDiscordAndProceed(successCallback, failCallback) {
        $.ajax({
            url: dnd_speaking_data.rest_url + 'discord/check-connection',
            method: 'GET',
            headers: {
                'X-WP-Nonce': dnd_speaking_data.nonce
            },
            success: function(response) {
                if (response.connected) {
                    // Connected to Discord, proceed
                    successCallback();
                } else {
                    // Not connected to Discord
                    if (confirm('Bạn cần kết nối Discord để đặt lịch hoặc bắt đầu học. Bạn có muốn kết nối Discord ngay bây giờ không?')) {
                        // User wants to connect, redirect to Discord auth
                        if (response.auth_url) {
                            window.location.href = response.auth_url;
                        } else {
                            alert('Không thể lấy link kết nối Discord. Vui lòng thử lại.');
                            if (failCallback) {
                                failCallback();
                            }
                        }
                    } else {
                        // User doesn't want to connect
                        if (failCallback) {
                            failCallback();
                        }
                    }
                }
            },
            error: function(xhr) {
                console.error('Error checking Discord connection:', xhr);
                alert('Không thể kiểm tra kết nối Discord. Vui lòng thử lại.');
                if (failCallback) {
                    failCallback();
                }
            }
        });
    }
});