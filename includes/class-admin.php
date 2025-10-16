<?php
/**
 * Admin settings for DND Speaking
 */

class DND_Speaking_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'DND Speaking',
            'DND Speaking',
            'manage_options',
            'dnd-speaking',
            [$this, 'admin_page'],
            'dashicons-microphone',
            30
        );
    }

    public function admin_page() {
        include plugin_dir_path(__FILE__) . '../admin/partials/admin-settings.php';
    }

    public function register_settings() {
        register_setting('dnd_speaking_settings', 'dnd_discord_bot_token');
        register_setting('dnd_speaking_settings', 'dnd_session_duration');

        add_settings_section(
            'dnd_speaking_main',
            'Main Settings',
            null,
            'dnd_speaking_settings'
        );

        add_settings_field(
            'discord_bot_token',
            'Discord Bot Token',
            [$this, 'discord_bot_token_field'],
            'dnd_speaking_settings',
            'dnd_speaking_main'
        );

        add_settings_field(
            'session_duration',
            'Session Duration (minutes)',
            [$this, 'session_duration_field'],
            'dnd_speaking_settings',
            'dnd_speaking_main'
        );
    }

    public function discord_bot_token_field() {
        $value = get_option('dnd_discord_bot_token');
        echo '<input type="password" name="dnd_discord_bot_token" value="' . esc_attr($value) . '" />';
    }

    public function session_duration_field() {
        $value = get_option('dnd_session_duration', 24);
        echo '<input type="number" name="dnd_session_duration" value="' . esc_attr($value) . '" />';
    }
}