<?php<?php

/**/**

 * REST API for frontend blocks * REST API for Discord integration

 */ */



class DND_Speaking_REST_API {class DND_Speaking_REST_API {



    public function __construct() {    public function __construct() {

        add_action('rest_api_init', [$this, 'register_routes']);        add_action('rest_api_init', [$this, 'register_routes']);

    }    }



    public function register_routes() {    public function register_routes() {

        register_rest_route('dnd-speaking/v1', '/credits', [        register_rest_route('dnd-speaking/v1', '/start-session', [

            'methods' => 'GET',            'methods' => 'POST',

            'callback' => [$this, 'get_credits'],            'callback' => [$this, 'start_session'],

            'permission_callback' => [$this, 'check_user_logged_in'],            'permission_callback' => '__return_true', // Add proper auth

        ]);        ]);



        register_rest_route('dnd-speaking/v1', '/teachers', [        register_rest_route('dnd-speaking/v1', '/end-session', [

            'methods' => 'GET',            'methods' => 'POST',

            'callback' => [$this, 'get_teachers'],            'callback' => [$this, 'end_session'],

            'permission_callback' => '__return_true',            'permission_callback' => '__return_true',

        ]);        ]);

    }    }



    public function check_user_logged_in() {    public function start_session($request) {

        return is_user_logged_in();        $student_id = intval($request->get_param('student_id'));

    }        $teacher_id = intval($request->get_param('teacher_id'));

        $discord_channel = sanitize_text_field($request->get_param('discord_channel'));

    public function get_credits() {

        $user_id = get_current_user_id();        // Check credits

        $credits = DND_Speaking_Helpers::get_user_credits($user_id);        if (!DND_Speaking_Helpers::deduct_user_credits($student_id)) {

        return ['credits' => $credits];            return new WP_Error('insufficient_credits', 'Not enough credits', ['status' => 400]);

    }        }



    public function get_teachers() {        // Create session

        $users = get_users(['role' => 'teacher']);        global $wpdb;

        $teachers = [];        $table = $wpdb->prefix . 'dnd_speaking_sessions';

        foreach ($users as $user) {        $wpdb->insert($table, [

            $available = get_user_meta($user->ID, 'dnd_available', true) == '1';            'student_id' => $student_id,

            $teachers[] = [            'teacher_id' => $teacher_id,

                'id' => $user->ID,            'discord_channel_id' => $discord_channel,

                'name' => $user->display_name,            'status' => 'active'

                'available' => $available,        ]);

            ];

        }        return ['success' => true, 'session_id' => $wpdb->insert_id];

        return $teachers;    }

    }

}    public function end_session($request) {
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
