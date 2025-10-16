<?php
/**
 * Gutenberg block for teacher schedule settings
 */

class DND_Speaking_Schedule_Settings_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/schedule-settings', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-schedule-settings-editor',
            plugin_dir_url(__FILE__) . 'schedule-settings-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-schedule-settings-editor-style',
            plugin_dir_url(__FILE__) . 'schedule-settings-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-schedule-settings-style',
            plugin_dir_url(__FILE__) . 'schedule-settings-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/schedule-settings')) {
            wp_enqueue_script(
                'dnd-speaking-schedule-settings',
                plugin_dir_url(__FILE__) . 'schedule-settings-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-schedule-settings', 'dnd_schedule_settings_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('schedule_settings_nonce'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-schedule-settings"><p>Vui lòng đăng nhập để cấu hình schedule.</p></div>';
        }

        $user_id = get_current_user_id();

        // Get current schedule settings
        $weekly_schedule = get_user_meta($user_id, 'dnd_weekly_schedule', true);
        if (!$weekly_schedule) {
            $weekly_schedule = [
                'monday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
                'tuesday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
                'wednesday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
                'thursday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
                'friday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
                'saturday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
                'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '17:00'],
            ];
        }

        $output = '<div class="dnd-schedule-settings">';
        $output .= '<h3>Schedule Settings</h3>';
        $output .= '<form id="dnd-schedule-form">';

        $days = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday'
        ];

        foreach ($days as $day_key => $day_name) {
            $day_data = $weekly_schedule[$day_key] ?? ['enabled' => false, 'start' => '09:00', 'end' => '17:00'];
            $checked = $day_data['enabled'] ? 'checked' : '';

            $output .= '<div class="dnd-day-setting">';
            $output .= '<label class="dnd-day-toggle">';
            $output .= '<input type="checkbox" name="days[' . $day_key . '][enabled]" ' . $checked . ' />';
            $output .= '<span class="dnd-toggle-slider"></span>';
            $output .= '<span class="dnd-day-name">' . $day_name . '</span>';
            $output .= '</label>';

            $output .= '<div class="dnd-time-settings" style="' . ($day_data['enabled'] ? '' : 'display: none;') . '">';
            $output .= '<div class="dnd-time-inputs">';
            $output .= '<label>Start: <input type="time" name="days[' . $day_key . '][start]" value="' . esc_attr($day_data['start']) . '" /></label>';
            $output .= '<label>End: <input type="time" name="days[' . $day_key . '][end]" value="' . esc_attr($day_data['end']) . '" /></label>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';
        }

        $output .= '<div class="dnd-form-actions">';
        $output .= '<button type="submit" class="dnd-btn dnd-btn-save">Save Schedule</button>';
        $output .= '</div>';

        $output .= '<div id="dnd-schedule-message"></div>';

        $output .= '</form>';
        $output .= '</div>';

        return $output;
    }
}

// Initialize the block
new DND_Speaking_Schedule_Settings_Block();