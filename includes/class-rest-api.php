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
             ORDER BY s.session_date DESC, s.session_time DESC
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
                    $where_clause .= " AND s.status = 'confirmed'";
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
                 ORDER BY s.session_date DESC, s.session_time DESC
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
             ORDER BY s.session_date DESC, s.session_time DESC
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
             ORDER BY s.session_date DESC, s.session_time DESC
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
                $where_clause .= " AND s.status = 'confirmed'";
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
             ORDER BY s.id DESC
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
                $output .= $this->render_session_card($session);
            }
        }

        // Pagination
        if ($total_pages > 1) {
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

        wp_send_json_success(['html' => $output, 'total_sessions' => $total_sessions, 'total_pages' => $total_pages]);
    }

    private function get_pagination_links($current_page, $total_pages) {
        $links = [];

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

        $output .= '</div>';

        return $output;
    }
}
