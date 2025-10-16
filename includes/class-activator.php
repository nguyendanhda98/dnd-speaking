<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */

class DND_Speaking_Activator {

    public static function activate() {
        self::create_database_tables();
        flush_rewrite_rules();
    }

    private static function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for user credits (remaining sessions)
        $table_credits = $wpdb->prefix . 'dnd_speaking_credits';
        $sql_credits = "CREATE TABLE $table_credits (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            credits int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        // Table for sessions
        $table_sessions = $wpdb->prefix . 'dnd_speaking_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            student_id bigint(20) NOT NULL,
            teacher_id bigint(20) NOT NULL,
            status varchar(50) DEFAULT 'completed',
            start_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_credits);
        dbDelta($sql_sessions);
    }
}
