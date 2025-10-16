jQuery(document).ready(function($) {
    const $teachersList = $('#dnd-teachers-list');

    if ($teachersList.length === 0) return;

    // Check if user is logged in
    if (!dnd_speaking_data.user_id) {
        $teachersList.html('<p>Vui lòng đăng nhập để xem danh sách giáo viên.</p>');
        return;
    }

    // Fetch teachers
    $.ajax({
        url: dnd_speaking_data.rest_url + 'teachers',
        method: 'GET',
        success: function(teachers) {
            console.log('Teachers loaded:', teachers); // Debug log
            renderTeachers(teachers);
        },
        error: function(xhr, status, error) {
            console.error('Error loading teachers:', xhr.responseText, status, error); // Debug log
            $teachersList.html('<p>Unable to load teachers. Please check console for details.</p>');
        }
    });

    function renderTeachers(teachers) {
        const html = teachers.map(teacher => `
            <div class="dnd-teacher-card">
                <div class="dnd-teacher-name">${teacher.name}</div>
                <div class="dnd-teacher-status ${teacher.available ? 'online' : ''}">
                    ${teacher.available ? 'Online' : 'Offline'}
                </div>
                <div class="dnd-teacher-buttons">
                    <button class="dnd-btn dnd-btn-book" data-teacher-id="${teacher.id}">
                        <svg class="dnd-btn-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM9 14H7v-2h2v2zm4 0h-2v-2h2v2zm4 0h-2v-2h2v2z"/>
                        </svg>
                        Book Now
                    </button>
                    ${teacher.available ? `
                        <button class="dnd-btn dnd-btn-start" data-teacher-id="${teacher.id}">
                            <svg class="dnd-btn-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z"/>
                            </svg>
                            Start Now
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');

        $teachersList.html(html);

        // Handle button clicks
        $('.dnd-btn-book').on('click', function() {
            const teacherId = $(this).data('teacher-id');
            // Handle book now - redirect to booking page or show modal
            window.location.href = '/booking?teacher=' + teacherId;
        });

        $('.dnd-btn-start').on('click', function() {
            const teacherId = $(this).data('teacher-id');
            startSession(teacherId);
        });

        $('.dnd-btn-start').on('click', function() {
            const teacherId = $(this).data('teacher-id');
            // Handle start now - start session
            startSession(teacherId);
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
});