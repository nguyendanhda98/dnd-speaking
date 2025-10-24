<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */

class DND_Speaking_Deactivator {

    public static function deactivate() {
        // Optionally drop tables (commented out for safety)
        // self::drop_database_tables();

        // Clear scheduled cron jobs
        self::clear_cron_jobs();

        flush_rewrite_rules();
    }

    /**
     * Clear scheduled cron jobs
     */
    private static function clear_cron_jobs() {
        $timestamp = wp_next_scheduled('dnd_speaking_auto_cancel_sessions');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dnd_speaking_auto_cancel_sessions');
        }
    }

    private static function drop_database_tables() {
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'dnd_speaking_credits',
            $wpdb->prefix . 'dnd_speaking_sessions',
            $wpdb->prefix . 'dnd_speaking_logs'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
