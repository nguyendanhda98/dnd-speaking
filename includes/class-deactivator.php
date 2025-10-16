<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */

class DND_Speaking_Deactivator {

    public static function deactivate() {
        // Remove custom roles
        remove_role('teacher');
        remove_role('student');

        // Optionally drop tables (commented out for safety)
        // self::drop_database_tables();

        flush_rewrite_rules();
    }

    private static function drop_database_tables() {
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'dnd_speaking_credits',
            $wpdb->prefix . 'dnd_speaking_sessions',
            $wpdb->prefix . 'dnd_speaking_bookings'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
