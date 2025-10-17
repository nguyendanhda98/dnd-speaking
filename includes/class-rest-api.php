<?php

/**
 * REST API for DND Speaking plugin
 */

class DND_Speaking_REST_API {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
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
    }

    public function check_user_logged_in() {
        return is_user_logged_in();
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
        $wpdb->update($table, [
            'status' => 'cancelled'
        ], ['id' => $session_id]);

        return ['success' => true];
    }

    public function book_session($request) {
        $student_id = intval($request->get_param('student_id'));
        $teacher_id = intval($request->get_param('teacher_id'));
        $start_time = sanitize_text_field($request->get_param('start_time'));

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
        $wpdb->insert($table, [
            'student_id' => $student_id,
            'teacher_id' => $teacher_id,
            'start_time' => $start_time,
            'status' => 'pending'
        ]);

        return ['success' => true, 'session_id' => $wpdb->insert_id];
    }

    public function get_teacher_availability($request) {
        $teacher_id = intval($request->get_param('teacher_id'));
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+7 days'));

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

        // Generate available slots: 9 AM to 5 PM, 1 hour slots
        $available_slots = [];
        $current_date = strtotime($start_date);
        $end_date_time = strtotime($end_date . ' 23:59:59');

        while ($current_date <= $end_date_time) {
            $day_of_week = date('N', $current_date); // 1=Monday, 7=Sunday
            if ($day_of_week >= 1 && $day_of_week <= 5) { // Monday to Friday
                for ($hour = 9; $hour < 17; $hour++) {
                    $slot_time = date('Y-m-d H:i:s', strtotime(date('Y-m-d', $current_date) . " {$hour}:00:00"));
                    if (!in_array($slot_time, $booked_times)) {
                        $available_slots[] = [
                            'date' => date('Y-m-d', $current_date),
                            'time' => date('H:i', strtotime($slot_time)),
                            'datetime' => $slot_time,
                        ];
                    }
                }
            }
            $current_date = strtotime('+1 day', $current_date);
        }

        return $available_slots;
    }
}
