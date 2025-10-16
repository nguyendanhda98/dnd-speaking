<?php
/**
 * REST API for Discord integration
 */

class DND_Speaking_REST_API {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
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
            'discord_channel_id' => $discord_channel,
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
}
