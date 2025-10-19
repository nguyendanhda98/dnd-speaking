<?php

/**
 * REST API for DND Speaking plugin
 */

class DND_Speaking_REST_API {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_ajax_get_student_session_history', [$this, 'ajax_get_student_session_history']);
        add_action('wp_ajax_get_session_history', [$this, 'ajax_get_session_history']);
        add_action('wp_ajax_get_student_sessions', [$this, 'ajax_get_student_sessions']);
        add_action('wp_ajax_update_session_status', [$this, 'ajax_update_session_status']);
    }

    public function register_routes() {
        register_rest_route('dnd-speaking/v1', '/credits', [
            'methods' => 'GET',
            'callback' => [$this, 'get_credits'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        register_rest_route('dnd-speaking/v1', '/teachers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_teachers'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dnd-speaking/v1', '/start-session', [
            'methods' => 'POST',
            'callback' => [$this, 'start_session'],
            'permission_callback' => '__return_true', // Add proper auth
        ]);

        register_rest_route('dnd-speaking/v1', '/end-session', [
            'methods' => 'POST',
            'callback' => [$this, 'end_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dnd-speaking/v1', '/student-sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_student_sessions'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        register_rest_route('dnd-speaking/v1', '/cancel-session', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel_session'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        register_rest_route('dnd-speaking/v1', '/teacher-availability/(?P<teacher_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_teacher_availability'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        register_rest_route('dnd-speaking/v1', '/book-session', [
            'methods' => 'POST',
            'callback' => [$this, 'book_session'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        register_rest_route('dnd-speaking/v1', '/session-history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_session_history'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('dnd-speaking/v1', '/student-session-history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_student_session_history'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        // Discord routes
        register_rest_route('dnd-speaking/v1', '/discord/auth-url', [
            'methods' => 'GET',
            'callback' => [$this, 'get_discord_auth_url'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        register_rest_route('dnd-speaking/v1', '/discord/callback', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_discord_callback'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dnd-speaking/v1', '/discord/disconnect', [
            'methods' => 'POST',
            'callback' => [$this, 'disconnect_discord'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);
    }

    public function check_user_logged_in() {
        return is_user_logged_in();
    }

    public function ajax_get_student_session_history() {
        error_log('AJAX Student Session History - Handler called');
        try {
            // Debug logging
            error_log('AJAX Student Session History - POST data: ' . print_r($_POST, true));
            error_log('AJAX Student Session History - User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
            error_log('AJAX Student Session History - User ID: ' . get_current_user_id());

            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'student_session_history_nonce')) {
                error_log('AJAX Student Session History - Nonce verification failed');
                wp_send_json_error('Security check failed');
                return;
            }

            // Check if user is logged in
            if (!is_user_logged_in()) {
                error_log('AJAX Student Session History - User not logged in');
                wp_send_json_error('You must be logged in');
                return;
            }

        $page = intval($_POST['page']) ?: 1;
        $per_page = intval($_POST['per_page']) ?: 10;
        $status_filter = sanitize_text_field($_POST['status_filter']) ?: 'all';

        $allowed_per_page = [1, 3, 5, 10];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 10;
        }

        $allowed_filters = ['all', 'completed', 'cancelled'];
        if (!in_array($status_filter, $allowed_filters)) {
            $status_filter = 'all';
        }

        $offset = ($page - 1) * $per_page;
        $user_id = get_current_user_id();

        // Build WHERE clause based on filter
        $where_clause = "s.student_id = %d";
        $query_params = [$user_id];

        if ($status_filter !== 'all') {
            $where_clause .= " AND s.status = %s";
            $query_params[] = $status_filter;
        } else {
            $where_clause .= " AND s.status IN ('completed', 'cancelled')";
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as teacher_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} u ON s.teacher_id = u.ID
             WHERE $where_clause
             ORDER BY s.start_time DESC
             LIMIT %d OFFSET %d",
            array_merge($query_params, [$per_page, $offset])
        ));

        error_log('AJAX Student Session History - SQL Query: ' . $wpdb->last_query);
        error_log('AJAX Student Session History - Sessions found: ' . count($sessions));

        // Get total count for pagination
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s WHERE $where_clause",
            $query_params
        ));

        $total_pages = ceil($total_sessions / $per_page);

        // Generate HTML for sessions
        $output = '';
        if (empty($sessions)) {
            $output .= '<div class="dnd-no-history">No session history available</div>';
        } else {
            $output .= '<div class="dnd-history-list">';
            foreach ($sessions as $session) {
                $session_datetime = $session->session_date . ' ' . $session->session_time;
                $formatted_date = date('M j, Y', strtotime($session->session_date));
                $formatted_time = date('g:i A', strtotime($session->session_time));

                $status_class = $session->status === 'completed' ? 'completed' : 'cancelled';
                $status_text = $session->status === 'completed' ? 'Completed' : 'Cancelled';

                $duration = isset($session->duration) ? $session->duration . ' min' : 'N/A';

                $output .= '<div class="dnd-history-item ' . $status_class . '">';
                $output .= '<div class="dnd-history-header">';
                $output .= '<div class="dnd-teacher-name">' . esc_html($session->teacher_name) . '</div>';
                $output .= '<div class="dnd-session-status ' . $status_class . '">' . $status_text . '</div>';
                $output .= '</div>';

                $output .= '<div class="dnd-history-details">';
                $output .= '<div class="dnd-session-datetime">' . $formatted_date . ' at ' . $formatted_time . '</div>';
                $output .= '<div class="dnd-session-duration">Duration: ' . $duration . '</div>';

                if ($session->status === 'cancelled') {
                    $output .= '<div class="dnd-session-cancellation">Session was cancelled</div>';
                }

                $output .= '</div>';

                if ($session->status === 'completed' && !empty($session->feedback)) {
                    $output .= '<div class="dnd-session-feedback">';
                    $output .= '<strong>Feedback:</strong> ' . esc_html($session->feedback);
                    $output .= '</div>';
                }

                $output .= '</div>';
            }
            $output .= '</div>';

            // Pagination
            if ($total_pages > 1) {
                $filter_param = $status_filter !== 'all' ? '&student_status_filter=' . $status_filter : '';
                $per_page_param = '&student_per_page=' . $per_page;
                $output .= '<div class="dnd-pagination">';
                if ($page > 1) {
                    $output .= '<a href="#" data-page="' . ($page - 1) . '" class="dnd-page-link">Previous</a>';
                }

                for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                    $active_class = ($i === $page) ? ' active' : '';
                    $output .= '<a href="#" data-page="' . $i . '" class="dnd-page-link' . $active_class . '">' . $i . '</a>';
                }

                if ($page < $total_pages) {
                    $output .= '<a href="#" data-page="' . ($page + 1) . '" class="dnd-page-link">Next</a>';
                }
                $output .= '</div>';
            }
        }

        wp_send_json([
            'success' => true,
            'html' => $output,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_sessions' => $total_sessions
            ]
        ]);
        } catch (Exception $e) {
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }

    public function ajax_get_session_history() {
        error_log('AJAX Session History - Handler called for teacher');
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
                wp_send_json_error('Security check failed');
                return;
            }

            // Check if user is logged in
            if (!is_user_logged_in()) {
                wp_send_json_error('You must be logged in');
                return;
            }

            $page = intval($_POST['page']) ?: 1;
            $per_page = intval($_POST['per_page']) ?: 10;
            $status_filter = sanitize_text_field($_POST['filter']) ?: 'all';

            $allowed_per_page = [1, 3, 5, 10];
            if (!in_array($per_page, $allowed_per_page)) {
                $per_page = 10;
            }

            $allowed_filters = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];
            if (!in_array($status_filter, $allowed_filters)) {
                $status_filter = 'all';
            }

            $offset = ($page - 1) * $per_page;
            $user_id = get_current_user_id();

            // Build WHERE clause based on filter
            $where_clause = "s.teacher_id = %d";
            $query_params = [$user_id];

            switch ($status_filter) {
                case 'pending':
                    $where_clause .= " AND s.status = 'pending'";
                    break;
                case 'confirmed':
                    $where_clause .= " AND s.status IN ('confirmed', 'in_progress')";
                    break;
                case 'completed':
                    $where_clause .= " AND s.status = 'completed'";
                    break;
                case 'cancelled':
                    $where_clause .= " AND s.status = 'cancelled'";
                    break;
                default:
                    // All: include all statuses
                    break;
            }

            global $wpdb;
            $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

            $sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, u.display_name as student_name
                 FROM $sessions_table s
                 LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
                 WHERE $where_clause
                 ORDER BY s.start_time DESC
                 LIMIT %d OFFSET %d",
                array_merge($query_params, [$per_page, $offset])
            ));

            // Get total count for pagination
            $total_sessions = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sessions_table s WHERE $where_clause",
                $query_params
            ));

            $total_pages = ceil($total_sessions / $per_page);

            // Generate HTML for sessions
            $output = '';
            if (empty($sessions)) {
                $output .= '<div class="dnd-no-history">No session history available</div>';
            } else {
                $output .= '<div class="dnd-history-list">';
                foreach ($sessions as $session) {
                    $session_datetime = $session->session_date . ' ' . $session->session_time;
                    $formatted_date = date('M j, Y', strtotime($session->session_date));
                    $formatted_time = date('g:i A', strtotime($session->session_time));

                    $status_class = $session->status;
                    $status_text = ucfirst($session->status);
                    
                    // Custom status text for in_progress
                    if ($session->status === 'in_progress') {
                        $status_text = 'Đang diễn ra';
                    }

                    $duration = isset($session->duration) ? $session->duration . ' min' : 'N/A';

                    $output .= '<div class="dnd-history-item ' . $status_class . '">';
                    $output .= '<div class="dnd-history-header">';
                    $output .= '<div class="dnd-student-name">' . esc_html($session->student_name) . '</div>';
                    $output .= '<div class="dnd-session-status ' . $status_class . '">' . $status_text . '</div>';
                    $output .= '</div>';

                    $output .= '<div class="dnd-history-details">';
                    $output .= '<div class="dnd-session-datetime">' . $formatted_date . ' at ' . $formatted_time . '</div>';
                    $output .= '<div class="dnd-session-duration">Duration: ' . $duration . '</div>';

                    if ($session->status === 'cancelled') {
                        $output .= '<div class="dnd-session-cancellation">Session was cancelled</div>';
                    }

                    $output .= '</div>';

                    if ($session->status === 'completed' && !empty($session->feedback)) {
                        $output .= '<div class="dnd-session-feedback">';
                        $output .= '<strong>Feedback:</strong> ' . esc_html($session->feedback);
                        $output .= '</div>';
                    }

                    // Actions based on status
                    $output .= '<div class="dnd-session-actions">';
                    switch ($session->status) {
                        case 'pending':
                            $output .= '<button class="dnd-btn dnd-btn-confirm" data-session-id="' . $session->id . '">Xác nhận</button>';
                            $output .= '<button class="dnd-btn dnd-btn-reject" data-session-id="' . $session->id . '">Từ chối</button>';
                            break;
                        case 'confirmed':
                            $output .= '<button class="dnd-btn dnd-btn-start" data-session-id="' . $session->id . '">Bắt đầu</button>';
                            $output .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy buổi học</button>';
                            break;
                        case 'in_progress':
                            $output .= '<button class="dnd-btn dnd-btn-end" data-session-id="' . $session->id . '">Kết thúc</button>';
                            $output .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy buổi học</button>';
                            break;
                        case 'completed':
                            $output .= '<button class="dnd-btn dnd-btn-view" data-session-id="' . $session->id . '">Xem chi tiết</button>';
                            break;
                        case 'cancelled':
                            // No actions for cancelled sessions
                            break;
                    }
                    $output .= '</div>';

                    $output .= '</div>';
                }
                $output .= '</div>';

                // Pagination - always show at least page 1
                if ($total_pages >= 1) {
                    $output .= '<div class="dnd-pagination">';
                    $pages = $this->get_pagination_links($page, $total_pages);
                    foreach ($pages as $p) {
                        if ($p === '...') {
                            $output .= '<span class="dnd-page-dots">...</span>';
                        } else {
                            $active = ($p == $page) ? ' active' : '';
                            $output .= '<a href="#" class="dnd-page-link' . $active . '" data-page="' . $p . '">' . $p . '</a>';
                        }
                    }
                    $output .= '</div>';
                }
            }

            wp_send_json([
                'success' => true,
                'html' => $output,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_sessions' => $total_sessions
                ]
            ]);
        error_log('AJAX Student Session History - Response sent successfully for user ' . get_current_user_id());
        } catch (Exception $e) {
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }

    public function get_credits() {
        $user_id = get_current_user_id();
        $credits = DND_Speaking_Helpers::get_user_credits($user_id);
        return ['credits' => $credits];
    }

    public function get_teachers() {
        $teacher_role = get_option('dnd_teacher_role', 'teacher');
        $users = get_users(['role' => $teacher_role]);

        // If no users with teacher role, try to get all users with editor/admin role as fallback
        if (empty($users)) {
            $users = get_users(['role__in' => ['administrator', 'editor', 'author']]);
        }

        // If still no users, create sample data
        if (empty($users)) {
            return [
                ['id' => 1, 'name' => 'Teacher 1', 'available' => true],
                ['id' => 2, 'name' => 'Teacher 2', 'available' => false],
                ['id' => 3, 'name' => 'Teacher 3', 'available' => true],
            ];
        }

        $teachers = [];
        foreach ($users as $user) {
            $available = get_user_meta($user->ID, 'dnd_available', true) == '1';
            $teachers[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'available' => $available,
            ];
        }

        return $teachers;
    }

    public function start_session($request) {
        $student_id = intval($request->get_param('student_id'));
        $teacher_id = intval($request->get_param('teacher_id'));
        $discord_channel = sanitize_text_field($request->get_param('discord_channel'));

        // Set timezone to Vietnam
        date_default_timezone_set('Asia/Ho_Chi_Minh');

        // Check credits
        if (!DND_Speaking_Helpers::deduct_user_credits($student_id)) {
            return new WP_Error('insufficient_credits', 'Not enough credits', ['status' => 400]);
        }

        // Create session
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_sessions';
        $wpdb->insert($table, [
            'student_id' => $student_id,
            'teacher_id' => $teacher_id,
            'status' => 'active'
        ]);
        return ['success' => true, 'session_id' => $wpdb->insert_id];
    }

    public function end_session($request) {
        $session_id = intval($request->get_param('session_id'));

        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_sessions';
        $wpdb->update($table, [
            'status' => 'completed',
            'end_time' => current_time('mysql')
        ], ['id' => $session_id]);

        return ['success' => true];
    }

    public function get_student_sessions() {
        $user_id = get_current_user_id();
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_sessions';
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as teacher_name 
             FROM $table s 
             LEFT JOIN {$wpdb->users} u ON s.teacher_id = u.ID 
             WHERE s.student_id = %d AND s.status IN ('pending', 'confirmed') 
             AND s.start_time > NOW() 
             ORDER BY s.start_time ASC",
            $user_id
        ));

        $result = [];
        foreach ($sessions as $session) {
            $result[] = [
                'id' => $session->id,
                'teacher_name' => $session->teacher_name ?: 'Unknown Teacher',
                'status' => $session->status,
                'scheduled_time' => $session->start_time,
                'created_at' => $session->start_time,
            ];
        }

        return $result;
    }

    public function cancel_session($request) {
        $session_id = intval($request->get_param('session_id'));
        $user_id = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_sessions';

        // Check if session belongs to user and is cancellable
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND student_id = %d AND status IN ('pending', 'confirmed')",
            $session_id, $user_id
        ));

        if (!$session) {
            return new WP_Error('not_found', 'Session not found or not cancellable', ['status' => 404]);
        }

        // Update status to cancelled
        $update_data = ['status' => 'cancelled'];
        
        // Check if cancelled_by column exists
        $columns = $wpdb->get_col("DESCRIBE $table");
        if (in_array('cancelled_by', $columns)) {
            $update_data['cancelled_by'] = $user_id;
            $update_data['cancelled_at'] = current_time('mysql');
        }
        
        $wpdb->update($table, $update_data, ['id' => $session_id]);

        return ['success' => true];
    }

    public function book_session($request) {
        $student_id = intval($request->get_param('student_id'));
        $teacher_id = intval($request->get_param('teacher_id'));
        $start_time = sanitize_text_field($request->get_param('start_time'));

        // Set timezone to Vietnam
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        
        // Validate start_time is in the future and within 1 week
        $start_timestamp = strtotime($start_time);
        $now = time();
        $one_week_later = strtotime('+7 days');

        if ($start_timestamp <= $now) {
            return new WP_Error('invalid_time', 'Cannot book sessions in the past', ['status' => 400]);
        }

        if ($start_timestamp > $one_week_later) {
            return new WP_Error('invalid_time', 'Cannot book sessions more than 1 week in advance', ['status' => 400]);
        }

        // Validate that the slot is within teacher's available schedule
        $slot_day_of_week = date('N', $start_timestamp); // 1=Monday, 7=Sunday
        $slot_time = date('H:i', $start_timestamp);
        
        $weekly_schedule = get_user_meta($teacher_id, 'dnd_weekly_schedule', true);
        $is_valid_slot = false;
        
        if ($weekly_schedule && is_array($weekly_schedule)) {
            $day_names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $day_key = isset($day_names[$slot_day_of_week - 1]) ? $day_names[$slot_day_of_week - 1] : null;
            
            if ($day_key && isset($weekly_schedule[$day_key]) && $weekly_schedule[$day_key]['enabled']) {
                $slot_timestamp = strtotime($slot_time);
                
                // Check if time_slots exist (new format)
                if (isset($weekly_schedule[$day_key]['time_slots']) && is_array($weekly_schedule[$day_key]['time_slots'])) {
                    foreach ($weekly_schedule[$day_key]['time_slots'] as $time_slot) {
                        if (!isset($time_slot['start']) || !isset($time_slot['end'])) {
                            continue;
                        }
                        $day_start = strtotime($time_slot['start']);
                        $day_end = strtotime($time_slot['end']);
                        // Check if slot is within available time and at least 30 minutes before end
                        if ($slot_timestamp >= $day_start && $slot_timestamp <= strtotime('-30 minutes', $day_end)) {
                            $is_valid_slot = true;
                            break;
                        }
                    }
                } elseif (isset($weekly_schedule[$day_key]['start']) && isset($weekly_schedule[$day_key]['end'])) {
                    // Backward compatibility for old format
                    $day_start = strtotime($weekly_schedule[$day_key]['start']);
                    $day_end = strtotime($weekly_schedule[$day_key]['end']);
                    // Check if slot is within available time and at least 30 minutes before end
                    if ($slot_timestamp >= $day_start && $slot_timestamp <= strtotime('-30 minutes', $day_end)) {
                        $is_valid_slot = true;
                    }
                } else {
                    // Default fallback
                    $day_start = strtotime('09:00');
                    $day_end = strtotime('17:00');
                    if ($slot_timestamp >= $day_start && $slot_timestamp <= strtotime('-30 minutes', $day_end)) {
                        $is_valid_slot = true;
                    }
                }
            }
        } else {
            // Default schedule: 9 AM to 5 PM
            $slot_hour = intval(date('H', $start_timestamp));
            if ($slot_hour >= 9 && $slot_hour < 17) {
                $is_valid_slot = true;
            }
        }
        
        if (!$is_valid_slot) {
            return new WP_Error('invalid_slot', 'This time slot is not available for booking', ['status' => 400]);
        }

        // Check if slot is still available
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_sessions';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE teacher_id = %d 
             AND start_time = %s 
             AND status IN ('pending', 'confirmed', 'active')",
            $teacher_id, $start_time
        ));

        if ($existing > 0) {
            return new WP_Error('slot_taken', 'This time slot is no longer available', ['status' => 400]);
        }

        // Check credits
        if (!DND_Speaking_Helpers::deduct_user_credits($student_id)) {
            return new WP_Error('insufficient_credits', 'Not enough credits', ['status' => 400]);
        }

        // Book the session
        $insert_data = [
            'student_id' => $student_id,
            'teacher_id' => $teacher_id,
            'start_time' => $start_time,
            'status' => 'pending'
        ];
        
        // Check if session_date/session_time columns exist
        $columns = $wpdb->get_col("DESCRIBE $table");
        if (in_array('session_date', $columns) && in_array('session_time', $columns)) {
            $insert_data['session_date'] = date('Y-m-d', $start_timestamp);
            $insert_data['session_time'] = date('H:i:s', $start_timestamp);
        }
        
        $wpdb->insert($table, $insert_data);

        return ['success' => true, 'session_id' => $wpdb->insert_id];
    }

    public function get_teacher_availability($request) {
        $teacher_id = intval($request->get_param('teacher_id'));
        
        // Set timezone to Vietnam
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $now = time();
        $start_date = date('Y-m-d', $now);
        $end_date = date('Y-m-d', strtotime('+7 days', $now));

        // Get teacher's available days from weekly schedule (1=Monday, 7=Sunday)
        $weekly_schedule = get_user_meta($teacher_id, 'dnd_weekly_schedule', true);
        $available_days = [];
        $day_schedules = [];
        
        if ($weekly_schedule && is_array($weekly_schedule)) {
            $day_mapping = [
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                'sunday' => 7
            ];
            
            foreach ($weekly_schedule as $day_key => $day_data) {
                if (isset($day_data['enabled']) && $day_data['enabled'] && isset($day_mapping[$day_key])) {
                    $day_num = $day_mapping[$day_key];
                    $available_days[] = $day_num;
                    
                    // Store schedule for this day - handle both old format (start/end) and new format (time_slots)
                    if (isset($day_data['time_slots']) && is_array($day_data['time_slots'])) {
                        $day_schedules[$day_num] = $day_data['time_slots'];
                    } elseif (isset($day_data['start']) && isset($day_data['end'])) {
                        // Backward compatibility for old format
                        $day_schedules[$day_num] = [
                            ['start' => $day_data['start'], 'end' => $day_data['end']]
                        ];
                    } else {
                        // Default fallback
                        $day_schedules[$day_num] = [
                            ['start' => '09:00', 'end' => '17:00']
                        ];
                    }
                }
            }
        }
        
        
        if (empty($available_days)) {
            // If no schedule is set, teacher has no availability - return empty array
            return new WP_REST_Response([], 200);
        }

        // Get booked sessions for this teacher in the next week
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_sessions';
        $booked_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time FROM $table 
             WHERE teacher_id = %d 
             AND status IN ('pending', 'confirmed', 'active') 
             AND DATE(start_time) BETWEEN %s AND %s",
            $teacher_id, $start_date, $end_date
        ));

        $booked_times = [];
        foreach ($booked_sessions as $session) {
            $booked_times[] = date('Y-m-d H:i:s', strtotime($session->start_time));
        }

        // Generate available slots: 9 AM to 5 PM, 30 minute slots, starting from current time
        $available_slots = [];
        $current_date = strtotime($start_date);
        $end_date_time = strtotime($end_date . ' 23:59:59');

        while ($current_date <= $end_date_time) {
            $day_of_week = date('N', $current_date); // 1=Monday, 7=Sunday
            
            // Check if teacher is available on this day
            if (in_array($day_of_week, $available_days)) {
                
                // Get schedule for this day - now it's an array of time slots
                $day_time_slots = isset($day_schedules[$day_of_week]) ? $day_schedules[$day_of_week] : [['start' => '09:00', 'end' => '17:00']];
                
                // Loop through each time slot for this day
                foreach ($day_time_slots as $time_slot) {
                    if (!isset($time_slot['start']) || !isset($time_slot['end'])) {
                        continue; // Skip invalid slots
                    }
                    
                    $day_start_time = strtotime(date('Y-m-d', $current_date) . " {$time_slot['start']}:00");
                    $day_end_time = strtotime(date('Y-m-d', $current_date) . " {$time_slot['end']}:00");
                
                // Sessions can be booked up to 30 minutes before end time
                $bookable_end_time = strtotime('-30 minutes', $day_end_time);
                
                // Start from day start time or current time if today, whichever is later
                $start_time = ($current_date == strtotime($start_date)) ? max($day_start_time, $now) : $day_start_time;
                
                // Round up to next 30-minute slot
                $start_hour = intval(date('H', $start_time));
                $start_minute = intval(date('i', $start_time));
                
                if ($start_minute > 0 && $start_minute < 30) {
                    $start_minute = 30;
                } elseif ($start_minute > 30) {
                    $start_minute = 0;
                    $start_hour++;
                }
                
                $current_time = strtotime(date('Y-m-d', $current_date) . " {$start_hour}:{$start_minute}:00");
                
                while ($current_time <= $bookable_end_time) {
                    $slot_time = date('Y-m-d H:i:s', $current_time);
                    if (!in_array($slot_time, $booked_times)) {
                        $available_slots[] = [
                            'date' => date('Y-m-d', $current_date),
                            'time' => date('H:i', $current_time),
                            'datetime' => $slot_time,
                        ];
                    }
                    $current_time = strtotime('+30 minutes', $current_time);
                }
                } // End foreach time slot
            }
            $current_date = strtotime('+1 day', $current_date);
        }

        return $available_slots;
    }

    public function get_session_history($request) {
        $user_id = get_current_user_id();
        $page = intval($request->get_param('page')) ?: 1;
        $per_page = intval($request->get_param('per_page')) ?: 10;
        $status_filter = sanitize_text_field($request->get_param('status_filter')) ?: 'all';

        $allowed_per_page = [1, 3, 5, 10];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 10;
        }

        $allowed_filters = ['all', 'completed', 'cancelled'];
        if (!in_array($status_filter, $allowed_filters)) {
            $status_filter = 'all';
        }

        $offset = ($page - 1) * $per_page;

        // Build WHERE clause based on filter
        $where_clause = "s.teacher_id = %d";
        $query_params = [$user_id];

        if ($status_filter !== 'all') {
            $where_clause .= " AND s.status = %s";
            $query_params[] = $status_filter;
        } else {
            $where_clause .= " AND s.status IN ('completed', 'cancelled')";
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as student_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
             WHERE $where_clause
             ORDER BY s.start_time DESC
             LIMIT %d OFFSET %d",
            array_merge($query_params, [$per_page, $offset])
        ));

        // Get total count for pagination
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s WHERE $where_clause",
            $query_params
        ));

        $total_pages = ceil($total_sessions / $per_page);

        // Get cancelled_by names if column exists and needed
        $cancelled_by_names = [];
        $columns = $wpdb->get_col("DESCRIBE $sessions_table");
        if (in_array('cancelled_by', $columns) && ($status_filter === 'all' || $status_filter === 'cancelled')) {
            $cancel_where = "s.teacher_id = %d AND s.status = 'cancelled'";
            $cancel_params = [$user_id];

            $cancelled_sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT s.id, cu.display_name as cancelled_by_name, s.cancelled_at
                 FROM $sessions_table s
                 LEFT JOIN {$wpdb->users} cu ON s.cancelled_by = cu.ID
                 WHERE $cancel_where",
                $cancel_params
            ));
            if ($cancelled_sessions) {
                foreach ($cancelled_sessions as $cs) {
                    $cancelled_by_names[$cs->id] = [
                        'name' => $cs->cancelled_by_name,
                        'at' => $cs->cancelled_at
                    ];
                }
            }
        }

        // Generate HTML for sessions
        $output = '';
        if (empty($sessions)) {
            $output .= '<div class="dnd-no-history">No session history available</div>';
        } else {
            $output .= '<div class="dnd-history-list">';
            foreach ($sessions as $session) {
                $session_datetime = $session->session_date . ' ' . $session->session_time;
                $formatted_date = date('M j, Y', strtotime($session->session_date));
                $formatted_time = date('g:i A', strtotime($session->session_time));

                $status_class = $session->status === 'completed' ? 'completed' : 'cancelled';
                $status_text = $session->status === 'completed' ? 'Completed' : 'Cancelled';

                $duration = isset($session->duration) ? $session->duration . ' min' : 'N/A';

                $output .= '<div class="dnd-history-item ' . $status_class . '">';
                $output .= '<div class="dnd-history-header">';
                $output .= '<div class="dnd-student-name">' . esc_html($session->student_name) . '</div>';
                $output .= '<div class="dnd-session-status ' . $status_class . '">' . $status_text . '</div>';
                $output .= '</div>';

                $output .= '<div class="dnd-history-details">';
                $output .= '<div class="dnd-session-datetime">' . $formatted_date . ' at ' . $formatted_time . '</div>';
                $output .= '<div class="dnd-session-duration">Duration: ' . $duration . '</div>';

                if ($session->status === 'cancelled' && isset($cancelled_by_names[$session->id])) {
                    $cancel_info = $cancelled_by_names[$session->id];
                    $cancelled_at = !empty($cancel_info['at']) ? date('M j, Y g:i A', strtotime($cancel_info['at'])) : 'N/A';
                    $output .= '<div class="dnd-session-cancellation">';
                    $output .= '<strong>Cancelled by:</strong> ' . esc_html($cancel_info['name']) . '<br>';
                    $output .= '<strong>Cancelled at:</strong> ' . $cancelled_at;
                    $output .= '</div>';
                }

                $output .= '</div>';

                if ($session->status === 'completed' && !empty($session->feedback)) {
                    $output .= '<div class="dnd-session-feedback">';
                    $output .= '<strong>Feedback:</strong> ' . esc_html($session->feedback);
                    $output .= '</div>';
                }

                $output .= '</div>';
            }
            $output .= '</div>';

            // Pagination
            if ($total_pages > 1) {
                $filter_param = $status_filter !== 'all' ? '&status_filter=' . $status_filter : '';
                $per_page_param = '&per_page=' . $per_page;
                $output .= '<div class="dnd-pagination">';
                if ($page > 1) {
                    $output .= '<a href="#" data-page="' . ($page - 1) . '" class="dnd-page-link">Previous</a>';
                }

                for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                    $active_class = ($i === $page) ? ' active' : '';
                    $output .= '<a href="#" data-page="' . $i . '" class="dnd-page-link' . $active_class . '">' . $i . '</a>';
                }

                if ($page < $total_pages) {
                    $output .= '<a href="#" data-page="' . ($page + 1) . '" class="dnd-page-link">Next</a>';
                }
                $output .= '</div>';
            }
        }

        return [
            'success' => true,
            'html' => $output,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_sessions' => $total_sessions
            ]
        ];
    }

    public function get_student_session_history($request) {
        $user_id = get_current_user_id();
        $page = intval($request->get_param('page')) ?: 1;
        $per_page = intval($request->get_param('per_page')) ?: 10;
        $status_filter = sanitize_text_field($request->get_param('status_filter')) ?: 'all';

        $allowed_per_page = [1, 3, 5, 10];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 10;
        }

        $allowed_filters = ['all', 'completed', 'cancelled'];
        if (!in_array($status_filter, $allowed_filters)) {
            $status_filter = 'all';
        }

        $offset = ($page - 1) * $per_page;

        // Build WHERE clause based on filter
        $where_clause = "s.student_id = %d";
        $query_params = [$user_id];

        if ($status_filter !== 'all') {
            $where_clause .= " AND s.status = %s";
            $query_params[] = $status_filter;
        } else {
            $where_clause .= " AND s.status IN ('completed', 'cancelled')";
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, t.display_name as teacher_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} t ON s.teacher_id = t.ID
             WHERE $where_clause
             ORDER BY s.start_time DESC
             LIMIT %d OFFSET %d",
            array_merge($query_params, [$per_page, $offset])
        ));

        // Get total count for pagination
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s WHERE $where_clause",
            $query_params
        ));

        $total_pages = ceil($total_sessions / $per_page);

        // Get cancelled_by names if column exists and needed
        $cancelled_by_names = [];
        $columns = $wpdb->get_col("DESCRIBE $sessions_table");
        if (in_array('cancelled_by', $columns) && ($status_filter === 'all' || $status_filter === 'cancelled')) {
            $cancel_where = "s.student_id = %d AND s.status = 'cancelled'";
            $cancel_params = [$user_id];

            $cancelled_sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT s.id, cu.display_name as cancelled_by_name, s.cancelled_at
                 FROM $sessions_table s
                 LEFT JOIN {$wpdb->users} cu ON s.cancelled_by = cu.ID
                 WHERE $cancel_where",
                $cancel_params
            ));
            if ($cancelled_sessions) {
                foreach ($cancelled_sessions as $cs) {
                    $cancelled_by_names[$cs->id] = [
                        'name' => $cs->cancelled_by_name,
                        'at' => $cs->cancelled_at
                    ];
                }
            }
        }

        // Generate HTML for sessions
        $output = '';
        if (empty($sessions)) {
            $output .= '<div class="dnd-no-history">No session history available</div>';
        } else {
            $output .= '<div class="dnd-history-list">';
            foreach ($sessions as $session) {
                $session_datetime = $session->session_date . ' ' . $session->session_time;
                $formatted_date = date('M j, Y', strtotime($session->session_date));
                $formatted_time = date('g:i A', strtotime($session->session_time));

                $status_class = $session->status === 'completed' ? 'completed' : 'cancelled';
                $status_text = $session->status === 'completed' ? 'Completed' : 'Cancelled';

                $duration = isset($session->duration) ? $session->duration . ' min' : 'N/A';

                $output .= '<div class="dnd-history-item ' . $status_class . '">';
                $output .= '<div class="dnd-history-header">';
                $output .= '<div class="dnd-teacher-name">' . esc_html($session->teacher_name) . '</div>';
                $output .= '<div class="dnd-session-status ' . $status_class . '">' . $status_text . '</div>';
                $output .= '</div>';

                $output .= '<div class="dnd-history-details">';
                $output .= '<div class="dnd-session-datetime">' . $formatted_date . ' at ' . $formatted_time . '</div>';
                $output .= '<div class="dnd-session-duration">Duration: ' . $duration . '</div>';

                if ($session->status === 'cancelled' && isset($cancelled_by_names[$session->id])) {
                    $cancel_info = $cancelled_by_names[$session->id];
                    $cancelled_at = !empty($cancel_info['at']) ? date('M j, Y g:i A', strtotime($cancel_info['at'])) : 'N/A';
                    $output .= '<div class="dnd-session-cancellation">';
                    $output .= '<strong>Cancelled by:</strong> ' . esc_html($cancel_info['name']) . '<br>';
                    $output .= '<strong>Cancelled at:</strong> ' . $cancelled_at;
                    $output .= '</div>';
                }

                $output .= '</div>';

                if ($session->status === 'completed' && !empty($session->feedback)) {
                    $output .= '<div class="dnd-session-feedback">';
                    $output .= '<strong>Feedback:</strong> ' . esc_html($session->feedback);
                    $output .= '</div>';
                }

                $output .= '</div>';
            }
            $output .= '</div>';

            // Pagination
            if ($total_pages > 1) {
                $filter_param = $status_filter !== 'all' ? '&student_status_filter=' . $status_filter : '';
                $per_page_param = '&student_per_page=' . $per_page;
                $output .= '<div class="dnd-pagination">';
                if ($page > 1) {
                    $output .= '<a href="#" data-page="' . ($page - 1) . '" class="dnd-page-link">Previous</a>';
                }

                for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                    $active_class = ($i === $page) ? ' active' : '';
                    $output .= '<a href="#" data-page="' . $i . '" class="dnd-page-link' . $active_class . '">' . $i . '</a>';
                }

                if ($page < $total_pages) {
                    $output .= '<a href="#" data-page="' . ($page + 1) . '" class="dnd-page-link">Next</a>';
                }
                $output .= '</div>';
            }
        }

        return [
            'success' => true,
            'html' => $output,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_sessions' => $total_sessions
            ]
        ];
    }

    public function ajax_update_session_status() {
        check_ajax_referer('wp_rest', 'nonce');

        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }

        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';

        if (!$session_id || !in_array($new_status, ['confirmed', 'cancelled', 'in_progress', 'completed'])) {
            wp_send_json_error('Invalid parameters');
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';
        $user_id = get_current_user_id();

        // Verify that the session belongs to this teacher
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sessions_table WHERE id = %d AND teacher_id = %d",
            $session_id, $user_id
        ));

        if (!$session) {
            wp_send_json_error('Session not found or access denied');
        }

        // Update the session status
        $result = $wpdb->update(
            $sessions_table,
            ['status' => $new_status],
            ['id' => $session_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success('Session status updated successfully');
        } else {
            wp_send_json_error('Failed to update session status');
        }
    }

    public function ajax_get_student_sessions() {
        check_ajax_referer('wp_rest', 'nonce');

        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }

        $user_id = get_current_user_id();
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $offset = ($page - 1) * $per_page;

        // Build WHERE clause based on filter
        $where_clause = "s.student_id = %d";
        $query_params = [$user_id];

        switch ($filter) {
            case 'pending':
                $where_clause .= " AND s.status = 'pending'";
                break;
            case 'confirmed':
                $where_clause .= " AND s.status IN ('confirmed', 'in_progress')";
                break;
            case 'completed':
                $where_clause .= " AND s.status = 'completed'";
                break;
            case 'cancelled':
                $where_clause .= " AND s.status = 'cancelled'";
                break;
            default:
                // All: include all statuses
                break;
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, t.display_name as teacher_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} t ON s.teacher_id = t.ID
             WHERE $where_clause
             ORDER BY s.start_time DESC
             LIMIT %d OFFSET %d",
            array_merge($query_params, [$per_page, $offset])
        ));

        // Get total count for pagination
        $total_sessions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sessions_table s WHERE $where_clause", $query_params));
        $total_pages = ceil($total_sessions / $per_page);

        $output = '';

        if (empty($sessions)) {
            $output .= '<p>Không có buổi học nào.</p>';
        } else {
            foreach ($sessions as $session) {
                $output .= $this->render_student_session_card($session);
            }
        }

        // Pagination - always show at least page 1
        if ($total_pages >= 1) {
            $output .= '<div class="dnd-pagination">';
            $pages = $this->get_pagination_links($page, $total_pages);
            foreach ($pages as $p) {
                if ($p === '...') {
                    $output .= '<span class="dnd-page-dots">...</span>';
                } else {
                    $active = ($p == $page) ? ' active' : '';
                    $output .= '<a href="#" class="dnd-page-link' . $active . '" data-page="' . $p . '">' . $p . '</a>';
                }
            }
            $output .= '</div>';
        }

        wp_send_json([
            'success' => true,
            'html' => $output,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_sessions' => $total_sessions
            ]
        ]);
    }

    private function render_student_session_card($session) {
        $status_text = '';
        $status_class = '';
        $actions = '';

        switch ($session->status) {
            case 'pending':
                $status_text = 'Chờ xác nhận';
                $status_class = 'pending';
                $actions = '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy</button>';
                break;
            case 'confirmed':
                $status_text = 'Đã xác nhận';
                $status_class = 'confirmed';
                $actions = '';
                // Check if within 15 minutes before start
                if (!empty($session->session_date) && !empty($session->session_time)) {
                    $session_datetime = strtotime($session->session_date . ' ' . $session->session_time);
                    if (time() >= ($session_datetime - 15 * 60) && time() < $session_datetime) {
                        $actions .= '<button class="dnd-btn dnd-btn-join" data-session-id="' . $session->id . '">Tham gia ngay</button>';
                    }
                }
                $actions .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy</button>';
                break;
            case 'in_progress':
                $status_text = 'Đang diễn ra';
                $status_class = 'in_progress';
                $actions = '<button class="dnd-btn dnd-btn-join" data-session-id="' . $session->id . '">Tham gia ngay</button>';
                $actions .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy</button>';
                break;
            case 'completed':
                $status_text = 'Hoàn thành';
                $status_class = 'completed';
                // Check if rated
                $actions = '<button class="dnd-btn dnd-btn-rate" data-session-id="' . $session->id . '">Đánh giá</button>';
                $actions .= '<button class="dnd-btn dnd-btn-feedback" data-session-id="' . $session->id . '">Giáo viên phản hồi</button>';
                break;
            case 'cancelled':
                $status_text = 'Đã huỷ';
                $status_class = 'cancelled';
                $actions = ''; // No actions for cancelled
                break;
        }

        $scheduled_time = 'N/A';
        if (!empty($session->session_date) && !empty($session->session_time)) {
            $scheduled_time = date('d/m/Y H:i', strtotime($session->session_date . ' ' . $session->session_time));
        }

        return '
            <div class="dnd-session-card" data-session-id="' . $session->id . '">
                <div class="dnd-session-teacher">Giáo viên: ' . esc_html($session->teacher_name ?: 'N/A') . '</div>
                <div class="dnd-session-status ' . $status_class . '">Trạng thái: ' . $status_text . '</div>
                <div class="dnd-session-time">Thời gian: ' . $scheduled_time . '</div>
                <div class="dnd-session-actions">' . $actions . '</div>
            </div>
        ';
    }

    private function get_pagination_links($current_page, $total_pages) {
        $links = [];

        if ($total_pages <= 0) {
            return [1]; // Always show at least page 1
        }

        if ($total_pages <= 7) {
            // Show all pages if <=7
            for ($i = 1; $i <= $total_pages; $i++) {
                $links[] = $i;
            }
        } else {
            // Always show first page
            $links[] = 1;

            // Calculate range around current page
            $start = max(2, $current_page - 2);
            $end = min($total_pages - 1, $current_page + 2);

            // Add ... if there's gap after first
            if ($start > 2) {
                $links[] = '...';
            }

            // Add pages in range
            for ($i = $start; $i <= $end; $i++) {
                $links[] = $i;
            }

            // Add ... if there's gap before last
            if ($end < $total_pages - 1) {
                $links[] = '...';
            }

            // Always show last page
            if ($total_pages > 1) {
                $links[] = $total_pages;
            }
        }

        return $links;
    }

    private function render_session_card($session) {
        $output = '';

        // Safely format date and time
        $date_timestamp = strtotime($session->session_date);
        $time_timestamp = strtotime($session->session_time);
        $formatted_date = $date_timestamp ? date('M j, Y', $date_timestamp) : 'Invalid date';
        $formatted_time = $time_timestamp ? date('g:i A', $time_timestamp) : 'Invalid time';

        $status_class = $session->status;
        $status_text = ucfirst($session->status);

        $duration = isset($session->duration) ? $session->duration . ' min' : 'N/A';

        $output .= '<div class="dnd-session-card">';
        $output .= '<div class="dnd-session-teacher">' . esc_html($session->teacher_name) . '</div>';
        $output .= '<div class="dnd-session-status ' . $status_class . '">' . $status_text . '</div>';
        $output .= '<div class="dnd-session-time">' . $formatted_date . ' at ' . $formatted_time . ' (Duration: ' . $duration . ')</div>';

        // Actions based on status
        $output .= '<div class="dnd-session-actions">';
        switch ($session->status) {
            case 'pending':
                $output .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Cancel</button>';
                break;
            case 'confirmed':
                $output .= '<button class="dnd-btn dnd-btn-join" data-session-id="' . $session->id . '">Join Session</button>';
                break;
            case 'completed':
                if (empty($session->feedback)) {
                    $output .= '<button class="dnd-btn dnd-btn-feedback" data-session-id="' . $session->id . '">Leave Feedback</button>';
                } else {
                    $output .= '<div class="dnd-feedback-display">Feedback: ' . esc_html($session->feedback) . '</div>';
                }
                break;
            case 'cancelled':
                $output .= '<div class="dnd-cancelled-note">Session was cancelled</div>';
                break;
        }
                    $output .= '</div>';

                    // Actions based on status
                    $output .= '<div class="dnd-session-actions">';
                    switch ($session->status) {
                        case 'pending':
                            $output .= '<button class="dnd-btn dnd-btn-confirm" data-session-id="' . $session->id . '">Xác nhận</button>';
                            $output .= '<button class="dnd-btn dnd-btn-reject" data-session-id="' . $session->id . '">Từ chối</button>';
                            break;
                        case 'confirmed':
                            $output .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy buổi học</button>';
                            break;
                        case 'completed':
                            $output .= '<button class="dnd-btn dnd-btn-view" data-session-id="' . $session->id . '">Xem chi tiết</button>';
                            break;
                        case 'cancelled':
                            // No actions for cancelled sessions
                            break;
                    }
                    $output .= '</div>';

                    $output .= '</div>';        return $output;
    }

    // Discord methods
    public function get_discord_auth_url($request) {
        $client_id = get_option('dnd_discord_client_id');
        $redirect_uri = get_site_url() . '/wp-json/dnd-speaking/v1/discord/callback';

        if (!$client_id) {
            return new WP_Error('discord_config_missing', 'Discord Client ID not configured', ['status' => 500]);
        }

        $state = wp_create_nonce('discord_auth_' . get_current_user_id());
        update_user_meta(get_current_user_id(), 'discord_auth_state', $state);
    // Also map state -> user for callback when cookies/session aren't present
    set_transient('dnd_discord_state_' . $state, get_current_user_id(), 10 * MINUTE_IN_SECONDS);

        // Request broader scopes as required by product needs
        $scopes = [
            'identify',
            'email',
            'guilds',
            'guilds.join',
            'guilds.members.read',
            'gdm.join'
        ];

        $auth_url = 'https://discord.com/api/oauth2/authorize?' . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            // Discord expects space-delimited scopes
            'scope' => implode(' ', $scopes),
            'state' => $state
        ]);

        return ['url' => $auth_url];
    }

    public function handle_discord_callback($request) {
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        // Try to resolve the initiating user by state mapping first (handles no-cookie redirects)
        $mapped_user_id = (int) get_transient('dnd_discord_state_' . $state);
        $user_id = $mapped_user_id ?: get_current_user_id();

        // Basic debug logging
        if (function_exists('error_log')) {
            error_log('[DND Discord] Callback invoked. code present=' . (!empty($code) ? 'yes' : 'no') . ', state=' . sanitize_text_field((string)$state) . ', mapped_user_id=' . $mapped_user_id . ', current_user_id=' . get_current_user_id());
        }

        if (!$code || !$state) {
            wp_redirect(home_url());
            exit;
        }

        // Verify state
        $stored_state = $user_id ? get_user_meta($user_id, 'discord_auth_state', true) : '';
        if ($state !== $stored_state) {
            if (function_exists('error_log')) {
                error_log('[DND Discord] State mismatch or user not resolved. user_id=' . $user_id . ', stored_state=' . (string)$stored_state);
            }
            wp_redirect(home_url());
            exit;
        }

        // Exchange code for token
        $client_id = get_option('dnd_discord_client_id');
        $client_secret = get_option('dnd_discord_client_secret');
        $redirect_uri = get_site_url() . '/wp-json/dnd-speaking/v1/discord/callback';

        $response = wp_remote_post('https://discord.com/api/oauth2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri
            ]
        ]);

        if (is_wp_error($response)) {
            if (function_exists('error_log')) {
                error_log('[DND Discord] Token exchange failed: ' . $response->get_error_message());
            }
            wp_redirect(home_url());
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (function_exists('error_log')) {
            error_log('[DND Discord] Token response keys: ' . implode(',', array_keys((array)$body)));
        }
        if (isset($body['access_token'])) {
            update_user_meta($user_id, 'discord_access_token', $body['access_token']);
            // Persist refresh token/expires/scopes when available
            if (!empty($body['refresh_token'])) {
                update_user_meta($user_id, 'discord_refresh_token', $body['refresh_token']);
            }
            if (!empty($body['expires_in'])) {
                // Store absolute expiry timestamp for convenience
                update_user_meta($user_id, 'discord_token_expires_at', time() + intval($body['expires_in']));
            }
            if (!empty($body['scope'])) {
                update_user_meta($user_id, 'discord_scopes', $body['scope']);
            }
            update_user_meta($user_id, 'discord_connected', true);

            // Get user info
            $user_response = wp_remote_get('https://discord.com/api/users/@me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $body['access_token']
                ]
            ]);

            if (is_wp_error($user_response)) {
                if (function_exists('error_log')) {
                    error_log('[DND Discord] Failed to fetch user info: ' . $user_response->get_error_message());
                }
            } else {
                $user_data = json_decode(wp_remote_retrieve_body($user_response), true);
                if (!empty($user_data['id'])) {
                    update_user_meta($user_id, 'discord_user_id', $user_data['id']);
                }
                if (!empty($user_data['username'])) {
                    update_user_meta($user_id, 'discord_username', $user_data['username']);
                }
                // Email returned when 'email' scope is granted
                if (!empty($user_data['email'])) {
                    update_user_meta($user_id, 'discord_email', $user_data['email']);
                }
                if (function_exists('error_log')) {
                    error_log('[DND Discord] Saved user meta for user_id=' . $user_id);
                }
            }
            // Invalidate one-time state mapping
            delete_transient('dnd_discord_state_' . $state);
        }

        wp_redirect(home_url());
        exit;
    }

    public function disconnect_discord($request) {
        $user_id = get_current_user_id();

        delete_user_meta($user_id, 'discord_access_token');
        delete_user_meta($user_id, 'discord_connected');
        delete_user_meta($user_id, 'discord_user_id');
        delete_user_meta($user_id, 'discord_username');
        delete_user_meta($user_id, 'discord_email');
        delete_user_meta($user_id, 'discord_scopes');
        delete_user_meta($user_id, 'discord_refresh_token');
        delete_user_meta($user_id, 'discord_token_expires_at');

        return ['success' => true];
    }
}
