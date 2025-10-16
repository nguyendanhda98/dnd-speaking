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

        flush_rewrite_rules();
    }

    private static function drop_database_tables() {
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'dnd_speaking_credits',
            $wpdb->prefix . 'dnd_speaking_sessions'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
