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
        $new_credits = $existing + $amount;
        
        // Check if user exists in credits table
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d", $user_id));
        
        if ($exists > 0) {
            $result = $wpdb->update(
                $table, 
                ['credits' => $new_credits], 
                ['user_id' => $user_id],
                ['%d'], // format for credits
                ['%d']  // format for user_id
            );
        } else {
            $result = $wpdb->insert(
                $table, 
                ['user_id' => $user_id, 'credits' => $new_credits],
                ['%d', '%d'] // formats for user_id and credits
            );
        }
        
        if ($result === false) {
            error_log("ADD CREDIT FAILED - Database error for user {$user_id}: " . $wpdb->last_error);
            return false;
        }
        
        // Log the addition
        self::log_action($user_id, 'credit_added', "Added {$amount} credit(s). Balance: {$new_credits}");
        error_log("CREDIT ADDED - User {$user_id}: +{$amount} credit(s), new balance: {$new_credits}");
        
        return true;
    }

    public static function deduct_user_credits($user_id, $amount = 1) {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_credits';
        
        // Use atomic UPDATE with WHERE condition to prevent race condition
        // This will only update if credits are sufficient
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET credits = credits - %d WHERE user_id = %d AND credits >= %d",
            $amount, $user_id, $amount
        ));
        
        // Check if any row was updated
        if ($result === false || $wpdb->rows_affected === 0) {
            $current_credits = self::get_user_credits($user_id);
            error_log("CREDIT DEDUCTION FAILED - User {$user_id} has {$current_credits} credits, needs {$amount}");
            return false;
        }
        
        // Get new balance for logging
        $new_credits = self::get_user_credits($user_id);
        
        // Log the deduction
        self::log_action($user_id, 'credit_deducted', "Deducted {$amount} credit(s). Balance: {$new_credits}");
        error_log("CREDIT DEDUCTED - User {$user_id}: -{$amount} credit(s), new balance: {$new_credits}");
        
        return true;
    }
    
    public static function refund_user_credits($user_id, $amount = 1, $reason = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_credits';
        $current_credits = self::get_user_credits($user_id);
        $new_credits = $current_credits + $amount;
        
        // Check if user exists in credits table
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d", $user_id));
        
        if ($exists > 0) {
            $result = $wpdb->update(
                $table, 
                ['credits' => $new_credits], 
                ['user_id' => $user_id],
                ['%d'], // format for credits
                ['%d']  // format for user_id
            );
        } else {
            $result = $wpdb->insert(
                $table, 
                ['user_id' => $user_id, 'credits' => $new_credits],
                ['%d', '%d'] // formats for user_id and credits
            );
        }
        
        if ($result === false) {
            error_log("CREDIT REFUND FAILED - Database error for user {$user_id}: " . $wpdb->last_error);
            return false;
        }
        
        // Log the refund
        $log_message = "Refunded {$amount} credit(s). Balance: {$new_credits}";
        if ($reason) {
            $log_message .= " Reason: {$reason}";
        }
        self::log_action($user_id, 'credit_refunded', $log_message);
        error_log("CREDIT REFUNDED - User {$user_id}: +{$amount} credit(s), new balance: {$new_credits}. Reason: " . ($reason ?: 'N/A'));
        
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
