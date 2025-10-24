<?php

/**
 * REST API for DND Speaking plugin
 */

class DND_Speaking_REST_API {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_ajax_get_session_history', [$this, 'ajax_get_session_history']);
        add_action('wp_ajax_get_student_sessions', [$this, 'ajax_get_student_sessions']);
        add_action('wp_ajax_update_session_status', [$this, 'ajax_update_session_status']);
        add_action('wp', [$this, 'handle_discord_page_callback']);
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

        register_rest_route('dnd-speaking/v1', '/discord/disconnect', [
            'methods' => 'POST',
            'callback' => [$this, 'disconnect_discord'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        register_rest_route('dnd-speaking/v1', '/discord/user-info', [
            'methods' => 'GET',
            'callback' => [$this, 'get_discord_user_info'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        register_rest_route('dnd-speaking/v1', '/discord/create-voice-channel', [
            'methods' => 'POST',
            'callback' => [$this, 'create_discord_voice_channel'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        // Student start now endpoint
        register_rest_route('dnd-speaking/v1', '/student/start-now', [
            'methods' => 'POST',
            'callback' => [$this, 'student_start_now'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        // Teacher start session endpoint
        register_rest_route('dnd-speaking/v1', '/teacher/start-session', [
            'methods' => 'POST',
            'callback' => [$this, 'teacher_start_session'],
            'permission_callback' => [$this, 'check_user_logged_in'],
        ]);

        // Test endpoint
        register_rest_route('dnd-speaking/v1', '/test', [
            'methods' => 'GET',
            'callback' => [$this, 'test_endpoint'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function check_user_logged_in() {
        return is_user_logged_in();
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
            $filter_day = sanitize_text_field($_POST['filter_day']) ?: '';
            $filter_month = sanitize_text_field($_POST['filter_month']) ?: '';
            $filter_year = sanitize_text_field($_POST['filter_year']) ?: '';

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

            // Add time period filter (day, month and year)
            if (!empty($filter_year)) {
                $where_clause .= " AND YEAR(s.session_date) = %d";
                $query_params[] = intval($filter_year);
            }
            if (!empty($filter_month)) {
                $where_clause .= " AND MONTH(s.session_date) = %d";
                $query_params[] = intval($filter_month);
            }
            if (!empty($filter_day)) {
                $where_clause .= " AND DAY(s.session_date) = %d";
                $query_params[] = intval($filter_day);
            }

            // Store base where clause for counting (without status filter)
            $base_where_clause = $where_clause;
            $base_query_params = $query_params;

            // Add status filter
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
                 ORDER BY s.created_at DESC
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
                            // Add join button if discord_channel exists
                            if (!empty($session->discord_channel)) {
                                $output .= '<a href="' . esc_url($session->discord_channel) . '" class="dnd-btn dnd-btn-join" target="_blank">Tham gia ngay</a>';
                            }
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

            // Calculate counts for each status filter using the base where clause (time filter only)
            $filter_counts = [];
            
            // Count all
            $filter_counts['all'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sessions_table s WHERE $base_where_clause",
                $base_query_params
            ));
            
            // Count pending
            $filter_counts['pending'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sessions_table s WHERE $base_where_clause AND s.status = 'pending'",
                $base_query_params
            ));
            
            // Count confirmed (includes confirmed and in_progress)
            $filter_counts['confirmed'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sessions_table s WHERE $base_where_clause AND s.status IN ('confirmed', 'in_progress')",
                $base_query_params
            ));
            
            // Count completed
            $filter_counts['completed'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sessions_table s WHERE $base_where_clause AND s.status = 'completed'",
                $base_query_params
            ));
            
            // Count cancelled
            $filter_counts['cancelled'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sessions_table s WHERE $base_where_clause AND s.status = 'cancelled'",
                $base_query_params
            ));

            wp_send_json([
                'success' => true,
                'html' => $output,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_sessions' => $total_sessions
                ],
                'filter_counts' => $filter_counts
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
            $available_status = get_user_meta($user->ID, 'dnd_available', true);
            // Return status: '1' = online, 'busy' = busy, '' or other = offline
            $teachers[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'available' => $available_status === '1',
                'status' => $available_status, // Return raw status for frontend display
            ];
        }

        // Sort teachers: online first, then busy, then offline
        usort($teachers, function($a, $b) {
            // Define sort priority: online (1) = 0, busy = 1, offline = 2
            $priority_a = ($a['status'] === '1') ? 0 : (($a['status'] === 'busy') ? 1 : 2);
            $priority_b = ($b['status'] === '1') ? 0 : (($b['status'] === 'busy') ? 1 : 2);
            return $priority_a - $priority_b;
        });

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
            "SELECT * FROM $table WHERE id = %d AND student_id = %d AND status IN ('pending', 'confirmed', 'in_progress')",
            $session_id, $user_id
        ));

        if (!$session) {
            return new WP_Error('not_found', 'Session not found or not cancellable', ['status' => 404]);
        }

        $teacher_id = $session->teacher_id;
        $has_room_link = !empty($session->discord_channel);

        // Check if this is a future confirmed/pending session and if cancelled more than 24 hours before
        $should_refund = false;
        $hours_until_session = 0;
        $is_confirmed_session = false;
        
        if (in_array($session->status, ['confirmed', 'pending']) && !empty($session->start_time)) {
            $session_timestamp = strtotime($session->start_time);
            $current_timestamp = current_time('timestamp');
            $hours_until_session = ($session_timestamp - $current_timestamp) / 3600;
            $is_confirmed_session = ($session->status === 'confirmed');
            
            if ($hours_until_session > 24) {
                $should_refund = true;
                error_log('STUDENT CANCEL - Session is more than 24 hours away (' . round($hours_until_session, 2) . ' hours). Will refund credit.');
            } else {
                error_log('STUDENT CANCEL - Session is less than 24 hours away (' . round($hours_until_session, 2) . ' hours). No refund.');
            }
        }

        // If session is in_progress with room link, send webhook to clean up Discord room
        if ($session->status === 'in_progress' && $has_room_link) {
            $webhook_url = get_option('dnd_discord_webhook');
            
            // Extract room_id from discord_channel URL
            $room_id = '';
            if (preg_match('/channels\/\d+\/(\d+)/', $session->discord_channel, $matches)) {
                $room_id = $matches[1];
            }
            
            if ($webhook_url && $room_id) {
                error_log('STUDENT CANCEL IN_PROGRESS SESSION - Sending webhook to delete room: ' . $room_id);
                
                $webhook_response = wp_remote_post($webhook_url, [
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'action' => 'student_cancel_session',
                        'teacher_wp_id' => $teacher_id,
                        'student_wp_id' => $user_id,
                        'session_id' => $session_id,
                        'room_id' => $room_id,
                        'server_id' => get_option('dnd_discord_server_id')
                    ]),
                    'timeout' => 30,
                    'blocking' => true
                ]);
                
                // Check webhook response
                if (is_wp_error($webhook_response)) {
                    error_log('STUDENT CANCEL - Webhook error: ' . $webhook_response->get_error_message());
                    return new WP_Error('webhook_error', 'Không thể kết nối đến Discord server: ' . $webhook_response->get_error_message(), ['status' => 500]);
                }
                
                $response_code = wp_remote_retrieve_response_code($webhook_response);
                $response_body = json_decode(wp_remote_retrieve_body($webhook_response), true);
                
                if ($response_code !== 200) {
                    error_log('STUDENT CANCEL - Webhook failed with code: ' . $response_code);
                    return new WP_Error('webhook_failed', 'Discord server trả về lỗi (Code: ' . $response_code . ')', ['status' => 500]);
                }
                
                if (!isset($response_body['success']) || !$response_body['success']) {
                    $error_message = isset($response_body['message']) ? $response_body['message'] : 'Không thể xóa phòng học';
                    error_log('STUDENT CANCEL - Webhook returned error: ' . $error_message);
                    return new WP_Error('webhook_error', $error_message, ['status' => 500]);
                }
                
                error_log('STUDENT CANCEL - Webhook successful, room deleted');
            }

            // Clean up teacher's room metadata
            delete_user_meta($teacher_id, 'discord_voice_channel_id');
            delete_user_meta($teacher_id, 'discord_voice_channel_invite');
            
            // Reset teacher status to offline (not available)
            update_user_meta($teacher_id, 'dnd_available', '0');
            
            error_log('STUDENT CANCEL IN_PROGRESS SESSION - Cleaned up room metadata and set status to offline for teacher: ' . $teacher_id);
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
        
        // Refund credit logic (since credit was deducted when booking):
        // NEW LOGIC:
        // - Pending: Always refund (teacher hasn't accepted yet)
        // - Confirmed: Refund only if > 24 hours before session
        // - In-progress: NEVER refund (student already joined the session)
        $refunded = false;
        
        if ($session->status === 'pending') {
            // Pending sessions - always refund since teacher hasn't confirmed yet
            DND_Speaking_Helpers::refund_user_credits($user_id, 1, 'Student cancelled pending session');
            $refunded = true;
            error_log('STUDENT CANCEL PENDING - Refunded credit to student: ' . $user_id);
        } else if ($session->status === 'confirmed') {
            // Confirmed sessions - refund only if > 24 hours before
            if ($should_refund) {
                DND_Speaking_Helpers::refund_user_credits($user_id, 1, 'Student cancelled confirmed session more than 24 hours before');
                $refunded = true;
                error_log('STUDENT CANCEL CONFIRMED >24H - Refunded credit to student: ' . $user_id);
            } else {
                error_log('STUDENT CANCEL CONFIRMED <24H - No refund for student: ' . $user_id);
            }
        } else if ($session->status === 'in_progress') {
            // In-progress sessions - NEVER refund (student already joined)
            error_log('STUDENT CANCEL IN_PROGRESS - No refund (student already joined session): ' . $user_id);
        }

        return [
            'success' => true,
            'refunded' => $refunded,
            'message' => $refunded ? 'Đã hủy buổi học và hoàn lại 1 buổi.' : 'Đã hủy buổi học.'
        ];
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

        // Deduct credits immediately when booking
        if (!DND_Speaking_Helpers::deduct_user_credits($student_id, 1)) {
            return new WP_Error('insufficient_credits', 'Không đủ buổi học', ['status' => 400]);
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
        
        // Calculate end date: only show up to next Thursday
        // If today is Friday (5), Saturday (6), or Sunday (7), show until Thursday of next week
        // Otherwise show until Thursday of current week
        $current_day_of_week = date('N', $now); // 1=Monday, 2=Tuesday, ..., 5=Friday, 6=Saturday, 7=Sunday
        
        // Calculate days until next Thursday (4 = Thursday)
        if ($current_day_of_week <= 4) {
            // Monday to Thursday: show until Thursday this week
            $days_to_add = 4 - $current_day_of_week;
        } else {
            // Friday to Sunday: show until Thursday next week
            $days_to_add = (7 - $current_day_of_week) + 4;
        }
        
        $end_date = date('Y-m-d', strtotime("+{$days_to_add} days", $now));

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
                
                // Calculate minimum bookable time (now + 10 minutes)
                $min_bookable_time = strtotime('+10 minutes', $now);
                
                while ($current_time <= $bookable_end_time) {
                    // Skip slots that start within 10 minutes from now
                    if ($current_time <= $min_bookable_time) {
                        $current_time = strtotime('+30 minutes', $current_time);
                        continue;
                    }
                    
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
            $where_clause .= " AND s.status IN ('in_progress', 'completed', 'cancelled')";
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
            // Create a test in_progress session for debugging
            $test_session_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}dnd_speaking_sessions WHERE status = 'in_progress' LIMIT 1");
            if (!$test_session_id) {
                error_log('TEACHER SESSION HISTORY - No in_progress sessions found, creating test session');
                $wpdb->insert(
                    $wpdb->prefix . 'dnd_speaking_sessions',
                    [
                        'student_id' => 1, // Test student
                        'teacher_id' => $user_id,
                        'session_date' => current_time('Y-m-d'),
                        'session_time' => current_time('H:i:s'),
                        'start_time' => current_time('mysql'),
                        'status' => 'in_progress',
                        'discord_channel' => 'https://discord.com/channels/123456789/987654321',
                        'created_at' => current_time('mysql')
                    ]
                );
                error_log('TEACHER SESSION HISTORY - Test session created with ID: ' . $wpdb->insert_id);
            }
            
            $output .= '<div class="dnd-no-history">No session history available</div>';
        } else {
            $output .= '<div class="dnd-history-list">';
            foreach ($sessions as $session) {
                // Debug logging for in_progress sessions
                if ($session->status === 'in_progress') {
                    error_log('TEACHER SESSION HISTORY - Found in_progress session: ID=' . $session->id . ', discord_channel=' . ($session->discord_channel ?: 'NULL'));
                }
                
                $session_datetime = $session->session_date . ' ' . $session->session_time;
                $formatted_date = date('M j, Y', strtotime($session->session_date));
                $formatted_time = date('g:i A', strtotime($session->session_time));

                // Status mapping
                $status_class = '';
                $status_text = '';
                switch ($session->status) {
                    case 'confirmed':
                        $status_class = 'confirmed';
                        $status_text = 'Đã xác nhận';
                        break;
                    case 'in_progress':
                        $status_class = 'in_progress';
                        $status_text = 'Đang diễn ra';
                        break;
                    case 'completed':
                        $status_class = 'completed';
                        $status_text = 'Completed';
                        break;
                    case 'cancelled':
                        $status_class = 'cancelled';
                        $status_text = 'Cancelled';
                        break;
                    default:
                        $status_class = $session->status;
                        $status_text = ucfirst($session->status);
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

                // Show start button for confirmed sessions
                if ($session->status === 'confirmed') {
                    $output .= '<div class="dnd-session-actions">';
                    $output .= '<button class="dnd-btn dnd-btn-start" data-session-id="' . $session->id . '" data-student-id="' . $session->student_id . '">Bắt đầu</button>';
                    $output .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy buổi học</button>';
                    $output .= '</div>';
                }

                // Show join button for in_progress sessions
                if ($session->status === 'in_progress' && !empty($session->discord_channel)) {
                    error_log('TEACHER SESSION HISTORY - Adding join button for session ID=' . $session->id);
                    $output .= '<div class="dnd-session-actions">';
                    $output .= '<a href="' . esc_url($session->discord_channel) . '" class="dnd-btn dnd-btn-join" target="_blank">Tham gia ngay</a>';
                    $output .= '<button class="dnd-btn dnd-btn-complete" data-session-id="' . $session->id . '">Hoàn thành</button>';
                    $output .= '<button class="dnd-btn dnd-btn-cancel-session" data-session-id="' . $session->id . '">Hủy</button>';
                    $output .= '</div>';
                }

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

        $room_cleared = false;
        $webhook_url = get_option('dnd_discord_webhook');
        
        // Extract room_id from discord_channel URL if exists
        $room_id = '';
        if (!empty($session->discord_channel) && preg_match('/channels\/\d+\/(\d+)/', $session->discord_channel, $matches)) {
            $room_id = $matches[1];
        }

        // Case 1: Cancelling an in_progress session with a room
        if ($new_status === 'cancelled' && $session->status === 'in_progress' && !empty($session->discord_channel)) {
            if ($webhook_url && $room_id) {
                error_log('TEACHER CANCEL IN_PROGRESS SESSION - Sending webhook to delete room: ' . $room_id);
                
                $webhook_response = wp_remote_post($webhook_url, [
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'action' => 'teacher_cancel_session',
                        'teacher_wp_id' => $user_id,
                        'session_id' => $session_id,
                        'room_id' => $room_id,
                        'server_id' => get_option('dnd_discord_server_id')
                    ]),
                    'timeout' => 30,
                    'blocking' => true
                ]);
                
                // Check webhook response
                if (is_wp_error($webhook_response)) {
                    error_log('TEACHER CANCEL - Webhook error: ' . $webhook_response->get_error_message());
                    wp_send_json_error('Không thể kết nối đến Discord server: ' . $webhook_response->get_error_message());
                    return;
                }
                
                $response_code = wp_remote_retrieve_response_code($webhook_response);
                $response_body = json_decode(wp_remote_retrieve_body($webhook_response), true);
                
                if ($response_code !== 200) {
                    error_log('TEACHER CANCEL - Webhook failed with code: ' . $response_code);
                    wp_send_json_error('Discord server trả về lỗi (Code: ' . $response_code . ')');
                    return;
                }
                
                if (!isset($response_body['success']) || !$response_body['success']) {
                    $error_message = isset($response_body['message']) ? $response_body['message'] : 'Không thể xóa phòng học';
                    error_log('TEACHER CANCEL - Webhook returned error: ' . $error_message);
                    wp_send_json_error($error_message);
                    return;
                }
                
                error_log('TEACHER CANCEL - Webhook successful, room deleted');
            }

            // Clean up teacher's room metadata
            delete_user_meta($user_id, 'discord_voice_channel_id');
            delete_user_meta($user_id, 'discord_voice_channel_invite');
            
            // Reset teacher status to offline (not available)
            update_user_meta($user_id, 'dnd_available', '0');
            
            // Teacher cancels in-progress session - ALWAYS refund to student
            $student_id = $session->student_id;
            DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled in-progress session');
            
            $room_cleared = true;
            error_log('TEACHER CANCEL IN_PROGRESS SESSION - Cleaned up room metadata, set status to offline, and refunded credit to student: ' . $student_id);
        }
        
        // Case 1.5: Cancelling a confirmed session (not yet started) - ALWAYS refund
        if ($new_status === 'cancelled' && $session->status === 'confirmed') {
            // Teacher cancels confirmed session - ALWAYS refund to student
            $student_id = $session->student_id;
            DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled confirmed session');
            error_log('TEACHER CANCEL CONFIRMED SESSION - Refunded 1 credit to student: ' . $student_id);
        }
        
        // Case 1.6: Cancelling a pending session - ALWAYS refund
        if ($new_status === 'cancelled' && $session->status === 'pending') {
            // Teacher cancels/declines pending session - ALWAYS refund to student
            $student_id = $session->student_id;
            // Guard: only refund if session was actually pending (avoid duplicate refunds)
            DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled/declined pending session');
            error_log('TEACHER CANCEL PENDING SESSION - Refunded 1 credit to student: ' . $student_id . ' (via ajax_update_session_status)');
        }
        
        // Case 1.6: Teacher confirms pending session - NO CREDIT DEDUCTION
        // Credits are already deducted when student books the session
        // Just update the status, no need to deduct again
        
        // Case 2: Completing an in_progress session with a room
        if ($new_status === 'completed' && $session->status === 'in_progress' && !empty($session->discord_channel)) {
            if ($webhook_url && $room_id) {
                error_log('TEACHER COMPLETE IN_PROGRESS SESSION - Sending webhook to delete room: ' . $room_id);
                
                $webhook_response = wp_remote_post($webhook_url, [
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'action' => 'teacher_complete_session',
                        'teacher_wp_id' => $user_id,
                        'session_id' => $session_id,
                        'room_id' => $room_id,
                        'server_id' => get_option('dnd_discord_server_id')
                    ]),
                    'timeout' => 30,
                    'blocking' => true
                ]);
                
                // Check webhook response
                if (is_wp_error($webhook_response)) {
                    error_log('TEACHER COMPLETE - Webhook error: ' . $webhook_response->get_error_message());
                    wp_send_json_error('Không thể kết nối đến Discord server: ' . $webhook_response->get_error_message());
                    return;
                }
                
                $response_code = wp_remote_retrieve_response_code($webhook_response);
                $response_body = json_decode(wp_remote_retrieve_body($webhook_response), true);
                
                if ($response_code !== 200) {
                    error_log('TEACHER COMPLETE - Webhook failed with code: ' . $response_code);
                    wp_send_json_error('Discord server trả về lỗi (Code: ' . $response_code . ')');
                    return;
                }
                
                if (!isset($response_body['success']) || !$response_body['success']) {
                    $error_message = isset($response_body['message']) ? $response_body['message'] : 'Không thể xóa phòng học';
                    error_log('TEACHER COMPLETE - Webhook returned error: ' . $error_message);
                    wp_send_json_error($error_message);
                    return;
                }
                
                error_log('TEACHER COMPLETE - Webhook successful, room deleted');
            }

            // Clean up teacher's room metadata
            delete_user_meta($user_id, 'discord_voice_channel_id');
            delete_user_meta($user_id, 'discord_voice_channel_invite');
            
            // Reset teacher status to offline (not available)
            update_user_meta($user_id, 'dnd_available', '0');
            
            $room_cleared = true;
            error_log('TEACHER COMPLETE IN_PROGRESS SESSION - Cleaned up room metadata and set status to offline for teacher: ' . $user_id);
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
            wp_send_json_success([
                'message' => 'Session status updated successfully',
                'room_cleared' => $room_cleared
            ]);
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
             ORDER BY s.created_at DESC
             LIMIT %d OFFSET %d",
            array_merge($query_params, [$per_page, $offset])
        ));
        
        error_log('=== STUDENT SESSIONS DEBUG ===');
        error_log('SQL Query: ' . $wpdb->last_query);
        error_log('Sessions found: ' . count($sessions));
        error_log('Filter: ' . $filter . ', User ID: ' . $user_id);
        if (!empty($sessions)) {
            error_log('First session: ' . print_r($sessions[0], true));
        }

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

        // Calculate session timestamp for cancel warning
        $session_timestamp = '';
        if (!empty($session->start_time)) {
            $session_timestamp = strtotime($session->start_time);
        } else if (!empty($session->session_date) && !empty($session->session_time)) {
            $session_timestamp = strtotime($session->session_date . ' ' . $session->session_time);
        }

        switch ($session->status) {
            case 'pending':
                $status_text = 'Chờ xác nhận';
                $status_class = 'pending';
                $actions = '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '" data-session-time="' . $session_timestamp . '" data-session-status="pending">Hủy</button>';
                break;
            case 'confirmed':
                $status_text = 'Đã xác nhận';
                $status_class = 'confirmed';
                
                // Show join button with room link if available
                if (!empty($session->discord_channel)) {
                    $actions = '<a href="' . esc_url($session->discord_channel) . '" class="dnd-btn dnd-btn-join">Tham gia ngay</a>';
                } else {
                    $actions = '';
                }
                $actions .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '" data-session-time="' . $session_timestamp . '" data-session-status="confirmed">Hủy</button>';
                break;
            case 'in_progress':
                $status_text = 'Đang diễn ra';
                $status_class = 'in_progress';
                
                // Show join button with room link if available
                if (!empty($session->discord_channel)) {
                    $actions = '<a href="' . esc_url($session->discord_channel) . '" class="dnd-btn dnd-btn-join">Tham gia ngay</a>';
                } else {
                    $actions = '';
                }
                $actions .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '" data-session-time="' . $session_timestamp . '" data-session-status="in_progress">Hủy</button>';
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

        // Don't show room link text, only button will have the link
        $room_link_html = '';

        return '
            <div class="dnd-session-card" data-session-id="' . $session->id . '">
                <div class="dnd-session-teacher">Giáo viên: ' . esc_html($session->teacher_name ?: 'N/A') . '</div>
                <div class="dnd-session-status ' . $status_class . '">Trạng thái: ' . $status_text . '</div>
                <div class="dnd-session-time">Thời gian: ' . $scheduled_time . '</div>
                ' . $room_link_html . '
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
        if (function_exists('error_log')) {
            error_log('[DND Discord] get_discord_auth_url called by user_id=' . get_current_user_id());
        }
        
        // Use the generated URL from settings if available
        $generated_url = get_option('dnd_discord_generated_url');
        if ($generated_url) {
            // The generated URL already includes state, so just return it
            return ['auth_url' => $generated_url];
        }
        
        // Fallback to generating URL if not configured
        $client_id = get_option('dnd_discord_client_id');
        $redirect_page = get_option('dnd_discord_redirect_page_full') ?: get_option('dnd_discord_redirect_page') ?: get_site_url();
        $redirect_uri = $redirect_page ?: get_site_url();

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

        return ['auth_url' => $auth_url];
    }

    /*
    public function handle_discord_callback($request) {
        $code = $request->get_param('code');
        $state = $request->get_param('state');

        // Debug logging
        if (function_exists('error_log')) {
            error_log('[DND Discord Callback] Started. code=' . (!empty($code) ? 'present' : 'missing') . ', state=' . (!empty($state) ? 'present' : 'missing'));
        }
        // Try to resolve the initiating user by state mapping first (handles no-cookie redirects)
        $mapped_user_id = (int) get_transient('dnd_discord_state_' . $state);
        $user_id = $mapped_user_id ?: get_current_user_id();

        // Basic debug logging
        if (function_exists('error_log')) {
            error_log('[DND Discord Callback] Resolved user_id=' . $user_id . ', mapped_user_id=' . $mapped_user_id . ', current_user_id=' . get_current_user_id());
        }

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

        // Redirect to redirect page or home
        $redirect_page = get_option('dnd_discord_redirect_page');
        if (function_exists('error_log')) {
            error_log('[DND Discord Callback] Redirecting to: ' . ($redirect_page ?: home_url()));
        }
    }
    */

    public function disconnect_discord($request) {
        $user_id = get_current_user_id();

        delete_user_meta($user_id, 'discord_access_token');
        delete_user_meta($user_id, 'discord_refresh_token');
        delete_user_meta($user_id, 'discord_token_expires_at');
        delete_user_meta($user_id, 'discord_scopes');
        delete_user_meta($user_id, 'discord_user_id');
        delete_user_meta($user_id, 'discord_username');
        delete_user_meta($user_id, 'discord_global_name');
        delete_user_meta($user_id, 'discord_email');
        delete_user_meta($user_id, 'discord_connected');

        return ['success' => true];
    }

    public function handle_discord_page_callback() {
        // Debug: Log all GET parameters
        if (function_exists('error_log')) {
            error_log('[DND Discord Page Callback] Triggered. GET params: ' . print_r($_GET, true));
        }
        
        if (!isset($_GET['via']) || $_GET['via'] !== 'connect-dnd-speaking-discord') {
            return;
        }

        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';

        // Debug logging
        if (function_exists('error_log')) {
            error_log('[DND Discord Page Callback] Started. code=' . (!empty($code) ? 'present' : 'missing') . ', state=' . (!empty($state) ? 'present' : 'missing'));
        }

        if (!$code) {
            if (function_exists('error_log')) {
                error_log('[DND Discord Page Callback] No code parameter, redirecting to home');
            }
            wp_redirect(home_url());
            exit;
        }

        // Try to resolve the initiating user by state mapping first (handles no-cookie redirects)
        $mapped_user_id = $state ? (int) get_transient('dnd_discord_state_' . $state) : 0;
        $user_id = $mapped_user_id ?: get_current_user_id();

        // Basic debug logging
        if (function_exists('error_log')) {
            error_log('[DND Discord Page Callback] Resolved user_id=' . $user_id . ', mapped_user_id=' . $mapped_user_id . ', current_user_id=' . get_current_user_id());
        }

        // Verify state if present
        if ($state) {
            $stored_state = $user_id ? get_user_meta($user_id, 'discord_auth_state', true) : '';
            if ($state !== $stored_state) {
                if (function_exists('error_log')) {
                    error_log('[DND Discord Page Callback] State mismatch or user not resolved. user_id=' . $user_id . ', stored_state=' . (string)$stored_state);
                }
                wp_redirect(home_url());
                exit;
            }
        } else {
            if (function_exists('error_log')) {
                error_log('[DND Discord Page Callback] No state parameter, skipping verification');
            }
        }

        // Exchange code for token
        $client_id = get_option('dnd_discord_client_id');
        $client_secret = get_option('dnd_discord_client_secret');
        $redirect_uri = get_option('dnd_discord_redirect_page_full') ?: get_option('dnd_discord_redirect_page') ?: get_site_url();

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
                error_log('[DND Discord Page Callback] Token exchange failed: ' . $response->get_error_message());
            }
            wp_redirect(home_url());
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (function_exists('error_log')) {
            error_log('[DND Discord Page Callback] Token response keys: ' . implode(',', array_keys((array)$body)));
            error_log('[DND Discord Page Callback] Full token response: ' . print_r($body, true));
        }
        
        if (isset($body['access_token'])) {
            if (function_exists('error_log')) {
                error_log('[DND Discord Page Callback] Access token received. Scopes granted: ' . ($body['scope'] ?? 'none'));
                error_log('[DND Discord Page Callback] Token expires in: ' . ($body['expires_in'] ?? 'unknown') . ' seconds');
                error_log('[DND Discord Page Callback] Refresh token: ' . (!empty($body['refresh_token']) ? 'present' : 'not provided'));
            }
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
                if (function_exists('error_log')) {
                    error_log('[DND Discord Page Callback] Saved scopes to user meta: ' . $body['scope']);
                }
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
                    error_log('[DND Discord Page Callback] Failed to fetch user info: ' . $user_response->get_error_message());
                }
            } else {
                $user_data = json_decode(wp_remote_retrieve_body($user_response), true);
                if (function_exists('error_log')) {
                    error_log('[DND Discord Page Callback] User info response: ' . print_r($user_data, true));
                }
                if (!empty($user_data['id'])) {
                    update_user_meta($user_id, 'discord_user_id', $user_data['id']);
                }
                if (!empty($user_data['username'])) {
                    update_user_meta($user_id, 'discord_username', $user_data['username']);
                }
                if (!empty($user_data['global_name'])) {
                    update_user_meta($user_id, 'discord_global_name', $user_data['global_name']);
                }
                // Email returned when 'email' scope is granted
                if (!empty($user_data['email'])) {
                    update_user_meta($user_id, 'discord_email', $user_data['email']);
                }
                if (function_exists('error_log')) {
                    error_log('[DND Discord Page Callback] Saved user meta for user_id=' . $user_id . ': discord_user_id=' . ($user_data['id'] ?? 'none') . ', username=' . ($user_data['username'] ?? 'none') . ', global_name=' . ($user_data['global_name'] ?? 'none') . ', email=' . ($user_data['email'] ?? 'none'));
                }
            }
            // Invalidate one-time state mapping
            if ($state) {
                delete_transient('dnd_discord_state_' . $state);
            }
            if (function_exists('error_log')) {
                error_log('[DND Discord Page Callback] Discord OAuth flow completed successfully for user_id=' . $user_id);
            }
        }

        // Clean URL and redirect back to the same page without parameters
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $clean_url = remove_query_arg(['code', 'state', 'via'], $current_url);
        wp_redirect($clean_url);
        exit;
    }

    public function test_endpoint($request) {
        return [
            'message' => 'REST API is working!',
            'timestamp' => time(),
            'user_id' => get_current_user_id(),
            'site_url' => get_site_url()
        ];
    }

    public function create_discord_voice_channel($request) {
        $user_id = get_current_user_id();
        
        // Check if user is connected to Discord
        $discord_connected = get_user_meta($user_id, 'discord_connected', true);
        if (!$discord_connected) {
            return new WP_Error('discord_not_connected', 'User not connected to Discord', ['status' => 400]);
        }

        $bot_token = get_option('dnd_discord_bot_token');
        $guild_id = get_option('dnd_discord_server_id');
        
        if (!$bot_token || !$guild_id) {
            return new WP_Error('discord_config_missing', 'Discord bot token or guild ID not configured', ['status' => 500]);
        }

        $discord_user_id = get_user_meta($user_id, 'discord_user_id', true);
        if (!$discord_user_id) {
            return new WP_Error('discord_user_id_missing', 'Discord user ID not found', ['status' => 400]);
        }

        // Create private voice channel
        $channel_name = 'Teacher Room - ' . get_userdata($user_id)->display_name;
        
        $response = wp_remote_post("https://discord.com/api/guilds/{$guild_id}/channels", [
            'headers' => [
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => $channel_name,
                'type' => 2, // Voice channel
                'parent_id' => null, // No category
                'permission_overwrites' => [
                    [
                        'id' => $guild_id, // @everyone role
                        'type' => 0, // Role
                        'deny' => 1024 // VIEW_CHANNEL
                    ],
                    [
                        'id' => $discord_user_id,
                        'type' => 1, // Member
                        'allow' => 1024 | 512 | 256 | 128 | 64 // VIEW_CHANNEL, CONNECT, SPEAK, USE_VAD, PRIORITY_SPEAKER
                    ]
                ]
            ])
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('discord_api_error', 'Failed to create voice channel', ['status' => 500]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['id'])) {
            $channel_id = $body['id'];
            // Store channel ID for later cleanup
            update_user_meta($user_id, 'discord_voice_channel_id', $channel_id);
            
            // Generate invite link (temporary, expires in 1 hour)
            $invite_response = wp_remote_post("https://discord.com/api/channels/{$channel_id}/invites", [
                'headers' => [
                    'Authorization' => 'Bot ' . $bot_token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'max_age' => 3600, // 1 hour
                    'max_uses' => 0, // Unlimited uses
                    'temporary' => false,
                    'unique' => true
                ])
            ]);

            if (!is_wp_error($invite_response)) {
                $invite_body = json_decode(wp_remote_retrieve_body($invite_response), true);
                if (isset($invite_body['code'])) {
                    $invite_link = 'https://discord.gg/' . $invite_body['code'];
                    update_user_meta($user_id, 'discord_voice_channel_invite', $invite_link);
                    return ['success' => true, 'invite_link' => $invite_link];
                }
            }
            
            // If invite fails, return channel ID
            return ['success' => true, 'channel_id' => $channel_id];
        }

        return new WP_Error('discord_channel_creation_failed', 'Failed to create voice channel', ['status' => 500]);
    }

    public function get_discord_user_info($request) {
        $user_id = get_current_user_id();
        
        $discord_info = [
            'connected' => get_user_meta($user_id, 'discord_connected', true) == '1',
            'access_token' => get_user_meta($user_id, 'discord_access_token', true),
            'expires_at' => get_user_meta($user_id, 'discord_token_expires_at', true),
            'refresh_token' => get_user_meta($user_id, 'discord_refresh_token', true),
            'scope' => get_user_meta($user_id, 'discord_scopes', true),
            'id' => get_user_meta($user_id, 'discord_user_id', true),
            'username' => get_user_meta($user_id, 'discord_username', true),
            'global_name' => get_user_meta($user_id, 'discord_global_name', true),
            'email' => get_user_meta($user_id, 'discord_email', true),
        ];

        return $discord_info;
    }

    /**
     * Student Start Now - Request to join a speaking session immediately
     */
    public function student_start_now($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $teacher_id = intval($request->get_param('teacher_id'));
        
        if (!$teacher_id) {
            return new WP_Error('missing_teacher_id', 'Teacher ID is required', ['status' => 400]);
        }
        
        // Check if teacher is actually available (not offline or busy)
        $teacher_available = get_user_meta($teacher_id, 'dnd_available', true);
        if ($teacher_available !== '1') {
            return new WP_REST_Response([
                'success' => false,
                'teacher_not_available' => true,
                'message' => 'Xin lỗi, giáo viên hiện đang bận hoặc offline. Vui lòng thử lại sau.'
            ], 200);
        }
        
        // Check if student has connected Discord
        $discord_connected = get_user_meta($user_id, 'discord_connected', true);
        if (!$discord_connected) {
            $auth_url_response = $this->get_discord_auth_url($request);
            $auth_url = '';
            
            if (!is_wp_error($auth_url_response) && isset($auth_url_response['auth_url'])) {
                $auth_url = $auth_url_response['auth_url'];
            }
            
            return new WP_REST_Response([
                'success' => false,
                'need_discord_connection' => true,
                'message' => 'Bạn cần liên kết tài khoản Discord để bắt đầu phiên học.',
                'discord_auth_url' => $auth_url
            ], 200);
        }
        
        // Check if student already has an active session
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';
        $active_session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sessions_table WHERE student_id = %d AND status = 'confirmed' AND end_time IS NULL",
            $user_id
        ));
        
        if ($active_session) {
            return new WP_REST_Response([
                'success' => false,
                'has_active_session' => true,
                'session_id' => $active_session->id,
                'room_link' => $active_session->discord_channel,
                'message' => 'Bạn đang có một phiên học đang hoạt động. Bạn có muốn tiếp tục với phiên học này không?'
            ], 200);
        }
        
        // Get teacher's room ID (Discord channel ID)
        $teacher_channel_id = get_user_meta($teacher_id, 'discord_voice_channel_id', true);
        if (!$teacher_channel_id) {
            return new WP_Error('teacher_no_room', 'Giáo viên chưa có phòng học', ['status' => 400]);
        }

        // Check credits EARLY: if student has no credits, return immediately
        if (!DND_Speaking_Helpers::get_user_credits($user_id) || DND_Speaking_Helpers::get_user_credits($user_id) < 1) {
            return new WP_Error('insufficient_credits', 'Không đủ buổi học để tham gia', ['status' => 400]);
        }
        
        // Send webhook to get room assignment
        $webhook_url = get_option('dnd_discord_webhook');
        if (!$webhook_url) {
            return new WP_Error('webhook_not_configured', 'Webhook chưa được cấu hình', ['status' => 500]);
        }
        
        $student_discord_id = get_user_meta($user_id, 'discord_user_id', true);
        $student_discord_name = get_user_meta($user_id, 'discord_global_name', true);
        
        $webhook_response = wp_remote_post($webhook_url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'action' => 'student_start_now',
                'student_discord_id' => $student_discord_id,
                'student_discord_name' => $student_discord_name,
                'student_wp_id' => $user_id,
                'teacher_wp_id' => $teacher_id,
                'teacher_room_id' => $teacher_channel_id,
                'server_id' => get_option('dnd_discord_server_id')
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($webhook_response)) {
            return new WP_Error('webhook_error', 'Không thể kết nối đến server Discord: ' . $webhook_response->get_error_message(), ['status' => 500]);
        }
        
        $response_code = wp_remote_retrieve_response_code($webhook_response);
        if ($response_code !== 200) {
            return new WP_Error('webhook_failed', 'Server Discord trả về lỗi (Code: ' . $response_code . ')', ['status' => 500]);
        }
        
        $body = json_decode(wp_remote_retrieve_body($webhook_response), true);
        
        if (!isset($body['success']) || !$body['success']) {
            $error_message = isset($body['message']) ? $body['message'] : 'Không thể tạo phòng học';
            return new WP_Error('room_creation_failed', $error_message, ['status' => 500]);
        }
        
        // Get room link from webhook response
        $room_id = isset($body['room_id']) ? $body['room_id'] : $teacher_channel_id;
        $room_link = isset($body['room_link']) ? $body['room_link'] : '';
        
        // If no room link provided, construct it from room_id
        if (!$room_link && $room_id) {
            // Discord channel links format: https://discord.com/channels/SERVER_ID/CHANNEL_ID
            $server_id = get_option('dnd_discord_server_id');
            $room_link = "https://discord.com/channels/{$server_id}/{$room_id}";
        }
        
        // Deduct credit now that we successfully got the room link (double-check availability)
        if (!DND_Speaking_Helpers::deduct_user_credits($user_id)) {
            return new WP_Error('insufficient_credits', 'Không đủ buổi học để tham gia', ['status' => 400]);
        }
        
        // Create confirmed session
        $insert_result = $wpdb->insert(
            $sessions_table,
            [
                'student_id' => $user_id,
                'teacher_id' => $teacher_id,
                'session_date' => current_time('Y-m-d'),
                'session_time' => current_time('H:i:s'),
                'start_time' => current_time('mysql'),
                'status' => 'in_progress',
                'discord_channel' => $room_link,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($insert_result === false) {
            error_log('Failed to insert session. Error: ' . $wpdb->last_error);
            error_log('SQL Query: ' . $wpdb->last_query);
            return new WP_Error('db_insert_failed', 'Không thể tạo session trong database', ['status' => 500]);
        }
        
        $session_id = $wpdb->insert_id;
        
        // Set teacher status to busy
        update_user_meta($teacher_id, 'dnd_available', 'busy');
        
        error_log('Session created successfully. ID: ' . $session_id . ', Student: ' . $user_id . ', Teacher: ' . $teacher_id . ', Room Link: ' . $room_link);
        error_log('Teacher ' . $teacher_id . ' status set to busy');
        
        return new WP_REST_Response([
            'success' => true,
            'session_id' => $session_id,
            'room_link' => $room_link,
            'message' => 'Phiên học đã được tạo thành công!'
        ], 200);
    }

    /**
     * Teacher Start Session - Start a confirmed session by sending webhook to create Discord room
     */
    public function teacher_start_session($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $session_id = intval($request->get_param('session_id'));
        
        if (!$session_id) {
            return new WP_Error('missing_session_id', 'Session ID is required', ['status' => 400]);
        }
        
        // Get session and verify it belongs to this teacher
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sessions_table WHERE id = %d AND teacher_id = %d AND status = 'confirmed'",
            $session_id, $user_id
        ));
        
        if (!$session) {
            return new WP_Error('session_not_found', 'Session không tồn tại hoặc không thuộc quyền của bạn', ['status' => 404]);
        }
        
        // Get teacher's Discord info
        $teacher_discord_id = get_user_meta($user_id, 'discord_user_id', true);
        $teacher_discord_name = get_user_meta($user_id, 'discord_global_name', true) ?: get_user_meta($user_id, 'discord_username', true);
        
        if (!$teacher_discord_id) {
            return new WP_Error('teacher_discord_not_connected', 'Giáo viên chưa kết nối Discord', ['status' => 400]);
        }
        
        // Get student's Discord info
        $student_discord_id = get_user_meta($session->student_id, 'discord_user_id', true);
        $student_discord_name = get_user_meta($session->student_id, 'discord_global_name', true) ?: get_user_meta($session->student_id, 'discord_username', true);
        
        if (!$student_discord_id) {
            return new WP_Error('student_discord_not_connected', 'Học viên chưa kết nối Discord', ['status' => 400]);
        }
        
        // Send webhook to create Discord room
        $webhook_url = get_option('dnd_discord_webhook');
        if (!$webhook_url) {
            return new WP_Error('webhook_not_configured', 'Webhook chưa được cấu hình', ['status' => 500]);
        }
        
        error_log('TEACHER START SESSION - Sending webhook for session: ' . $session_id);
        error_log('TEACHER START SESSION - Teacher Discord: ' . $teacher_discord_id . ' (' . $teacher_discord_name . ')');
        error_log('TEACHER START SESSION - Student Discord: ' . $student_discord_id . ' (' . $student_discord_name . ')');
        
        $webhook_response = wp_remote_post($webhook_url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'action' => 'teacher_start_session',
                'session_id' => $session_id,
                'teacher_discord_id' => $teacher_discord_id,
                'teacher_discord_name' => $teacher_discord_name,
                'teacher_wp_id' => $user_id,
                'student_discord_id' => $student_discord_id,
                'student_discord_name' => $student_discord_name,
                'student_wp_id' => $session->student_id,
                'server_id' => get_option('dnd_discord_server_id')
            ]),
            'timeout' => 30,
            'blocking' => true
        ]);
        
        if (is_wp_error($webhook_response)) {
            error_log('TEACHER START SESSION - Webhook error: ' . $webhook_response->get_error_message());
            return new WP_Error('webhook_error', 'Không thể kết nối đến server Discord: ' . $webhook_response->get_error_message(), ['status' => 500]);
        }
        
        $response_code = wp_remote_retrieve_response_code($webhook_response);
        $response_body = json_decode(wp_remote_retrieve_body($webhook_response), true);
        
        error_log('TEACHER START SESSION - Webhook response code: ' . $response_code);
        error_log('TEACHER START SESSION - Webhook response body: ' . print_r($response_body, true));
        
        if ($response_code !== 200) {
            return new WP_Error('webhook_failed', 'Server Discord trả về lỗi (Code: ' . $response_code . ')', ['status' => 500]);
        }
        
        if (!isset($response_body['success']) || !$response_body['success']) {
            $error_message = isset($response_body['message']) ? $response_body['message'] : 'Không thể tạo phòng học';
            error_log('TEACHER START SESSION - Webhook returned error: ' . $error_message);
            return new WP_Error('room_creation_failed', $error_message, ['status' => 500]);
        }
        
        // Get room link from webhook response
        $room_id = isset($response_body['channelId']) ? $response_body['channelId'] : '';
        $room_link = isset($response_body['room_link']) ? $response_body['room_link'] : '';
        
        // If no room link provided, construct it from room_id
        if (!$room_link && $room_id) {
            $server_id = get_option('dnd_discord_server_id');
            $room_link = "https://discord.com/channels/{$server_id}/{$room_id}";
        }
        
        if (!$room_link) {
            return new WP_Error('no_room_link', 'Không nhận được link phòng học từ Discord', ['status' => 500]);
        }
        
        // Update session to in_progress with room link
        $update_result = $wpdb->update(
            $sessions_table,
            [
                'status' => 'in_progress',
                'discord_channel' => $room_link,
                'start_time' => current_time('mysql')
            ],
            ['id' => $session_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($update_result === false) {
            error_log('TEACHER START SESSION - Failed to update session. Error: ' . $wpdb->last_error);
            return new WP_Error('db_update_failed', 'Không thể cập nhật session trong database', ['status' => 500]);
        }
        
        // Store room info for teacher
        update_user_meta($user_id, 'discord_voice_channel_id', $room_id);
        update_user_meta($user_id, 'discord_voice_channel_invite', $room_link);
        
        // Set teacher status to busy
        update_user_meta($user_id, 'dnd_available', 'busy');
        
        error_log('TEACHER START SESSION - Session started successfully. ID: ' . $session_id . ', Room Link: ' . $room_link);
        error_log('TEACHER START SESSION - Teacher ' . $user_id . ' status set to busy');
        
        return new WP_REST_Response([
            'success' => true,
            'session_id' => $session_id,
            'room_link' => $room_link,
            'message' => 'Phiên học đã được bắt đầu thành công!'
        ], 200);
    }

    /**
     * Auto-cancel pending sessions that start within 5 minutes and haven't been accepted
     * This function is called by WP Cron every minute
     */
    public static function auto_cancel_unaccepted_sessions() {
        global $wpdb;
        
        // Set timezone to Vietnam
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';
        
        // Find all pending sessions that start within 5 minutes
        $five_minutes_from_now = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $now = date('Y-m-d H:i:s');
        
        $pending_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $sessions_table 
             WHERE status = 'pending' 
             AND start_time <= %s 
             AND start_time > %s",
            $five_minutes_from_now,
            $now
        ));
        
        foreach ($pending_sessions as $session) {
            // Cancel the session
            $wpdb->update(
                $sessions_table,
                [
                    'status' => 'cancelled',
                    'cancelled_at' => current_time('mysql'),
                    'cancelled_by' => 0 // 0 means auto-cancelled by system
                ],
                ['id' => $session->id],
                ['%s', '%s', '%d'],
                ['%d']
            );
            
            // Refund the student's credit
            if (DND_Speaking_Helpers::add_user_credits($session->student_id, 1)) {
                error_log("DND Speaking Auto-Cancel: Refunded 1 credit to student ID {$session->student_id} for session ID {$session->id}");
            }
            
            error_log("DND Speaking Auto-Cancel: Cancelled session ID {$session->id} (teacher didn't accept within time limit)");
        }
        
        if (count($pending_sessions) > 0) {
            error_log("DND Speaking Auto-Cancel: Processed " . count($pending_sessions) . " pending sessions");
        }
    }
}


