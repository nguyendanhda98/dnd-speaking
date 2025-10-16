<?php
/**
 * Helper functions for DND Speaking plugin
 */

class DND_Speaking_Helpers {

    public static function get_user_credits($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_credits';
        $credits = $wpdb->get_var($wpdb->prepare("SELECT credits FROM $table WHERE user_id = %d", $user_id));
        return $credits ? (int)$credits : 0;
    }

    public static function add_user_credits($user_id, $amount) {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_credits';
        $existing = self::get_user_credits($user_id);
        if ($existing > 0) {
            $wpdb->update($table, ['credits' => $existing + $amount], ['user_id' => $user_id]);
        } else {
            $wpdb->insert($table, ['user_id' => $user_id, 'credits' => $amount]);
        }
    }

    public static function deduct_user_credits($user_id, $amount = 1) {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_credits';
        $existing = self::get_user_credits($user_id);
        if ($existing >= $amount) {
            $wpdb->update($table, ['credits' => $existing - $amount], ['user_id' => $user_id]);
            return true;
        }
        return false;
    }

    public static function get_online_teachers() {
        $args = [
            'role' => 'teacher',
            'meta_query' => [
                [
                    'key' => 'dnd_available',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];
        return get_users($args);
    }

    public static function is_teacher_available($teacher_id) {
        return get_user_meta($teacher_id, 'dnd_available', true) == '1';
    }

    public static function set_teacher_availability($teacher_id, $available) {
        update_user_meta($teacher_id, 'dnd_available', $available ? '1' : '0');
    }
}
