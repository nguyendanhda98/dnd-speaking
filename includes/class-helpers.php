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
        // Temporarily disabled
        return true;
    }

    public static function get_teacher_sessions_count($teacher_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_sessions';
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE teacher_id = %d", $teacher_id));
    }

    public static function log_action($user_id, $action, $details = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_logs';
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'action' => $action,
            'details' => $details
        ]);
    }
}
