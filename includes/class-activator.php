<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */

class DND_Speaking_Activator {

    public static function activate() {
        self::create_database_tables();
        self::update_database_tables();
        self::setup_cron_jobs();
        flush_rewrite_rules();
    }

    /**
     * Setup cron jobs for auto-cancelling unaccepted sessions
     */
    private static function setup_cron_jobs() {
        // Schedule the auto-cancel cron job to run every minute
        if (!wp_next_scheduled('dnd_speaking_auto_cancel_sessions')) {
            wp_schedule_event(time(), 'every_minute', 'dnd_speaking_auto_cancel_sessions');
        }
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
            end_time datetime DEFAULT NULL,
            duration int(11) DEFAULT 0,
            cancelled_by bigint(20) DEFAULT NULL,
            cancelled_at datetime DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Table for logs
        $table_logs = $wpdb->prefix . 'dnd_speaking_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(255) NOT NULL,
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_credits);
        dbDelta($sql_sessions);
        dbDelta($sql_logs);
    }

    public static function update_database_tables() {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'dnd_speaking_sessions';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_sessions'") != $table_sessions) {
            return; // Table doesn't exist yet
        }

        // Check if columns exist and add if not
        $columns = $wpdb->get_col("DESCRIBE $table_sessions");

        if (!in_array('session_date', $columns)) {
            $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN session_date date DEFAULT NULL");
        }

        if (!in_array('session_time', $columns)) {
            $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN session_time time DEFAULT NULL");
        }

        if (!in_array('cancelled_by', $columns)) {
            $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN cancelled_by bigint(20) DEFAULT NULL");
        }

        if (!in_array('cancelled_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN cancelled_at datetime DEFAULT NULL");
        }

        if (!in_array('discord_channel', $columns)) {
            $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN discord_channel varchar(255) DEFAULT NULL");
        }

        if (!in_array('created_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP");
        }

        if (!in_array('feedback', $columns)) {
            $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN feedback text DEFAULT NULL");
        }
    }
}
