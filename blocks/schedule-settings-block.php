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
                'monday' => ['enabled' => false, 'time_slots' => [['start' => '09:00', 'end' => '17:00']]],
                'tuesday' => ['enabled' => false, 'time_slots' => [['start' => '09:00', 'end' => '17:00']]],
                'wednesday' => ['enabled' => false, 'time_slots' => [['start' => '09:00', 'end' => '17:00']]],
                'thursday' => ['enabled' => false, 'time_slots' => [['start' => '09:00', 'end' => '17:00']]],
                'friday' => ['enabled' => false, 'time_slots' => [['start' => '09:00', 'end' => '17:00']]],
                'saturday' => ['enabled' => false, 'time_slots' => [['start' => '09:00', 'end' => '17:00']]],
                'sunday' => ['enabled' => false, 'time_slots' => [['start' => '09:00', 'end' => '17:00']]],
            ];
        } else {
            // Migrate old format (start/end) to new format (time_slots)
            foreach ($weekly_schedule as $day => $data) {
                if (isset($data['start']) && isset($data['end'])) {
                    $weekly_schedule[$day]['time_slots'] = [['start' => $data['start'], 'end' => $data['end']]];
                    unset($weekly_schedule[$day]['start'], $weekly_schedule[$day]['end']);
                } elseif (!isset($data['time_slots'])) {
                    $weekly_schedule[$day]['time_slots'] = [['start' => '09:00', 'end' => '17:00']];
                }
            }
        }

        $output = '<div class="dnd-schedule-settings">';
        $output .= '<h3>Schedule Settings</h3>';
        $output .= '<form id="dnd-schedule-form">';

        // Calculate dates for the current week
        $today = mktime(0, 0, 0, wp_date('m'), wp_date('d'), wp_date('Y'));
        $current_day_of_week = date('N', $today); // 1=Monday, 7=Sunday
        
        $days = [
            'monday' => ['name' => 'Monday', 'num' => 1],
            'tuesday' => ['name' => 'Tuesday', 'num' => 2],
            'wednesday' => ['name' => 'Wednesday', 'num' => 3],
            'thursday' => ['name' => 'Thursday', 'num' => 4],
            'friday' => ['name' => 'Friday', 'num' => 5],
            'saturday' => ['name' => 'Saturday', 'num' => 6],
            'sunday' => ['name' => 'Sunday', 'num' => 7]
        ];

        foreach ($days as $day_key => $day_info) {
            $day_num = $day_info['num'];
            $day_name = $day_info['name'];
            
            // Calculate the date for this day of the week
            if ($day_num == $current_day_of_week) {
                // Today
                $days_ahead = 0;
            } elseif ($day_num > $current_day_of_week) {
                // This day is later in the same week
                $days_ahead = $day_num - $current_day_of_week;
            } else {
                // This day is in the next week
                $days_ahead = 7 - $current_day_of_week + $day_num;
            }
            
            $day_date = strtotime("+{$days_ahead} days", $today);
            $formatted_date = date('M j', $day_date);
            $is_today = ($days_ahead === 0);
            
            $day_data = $weekly_schedule[$day_key] ?? ['enabled' => false, 'time_slots' => [['start' => '09:00', 'end' => '17:00']]];
            $checked = $day_data['enabled'] ? 'checked' : '';

            $day_class = 'dnd-day-setting';
            if ($is_today) {
                $day_class .= ' dnd-today';
            }

            $output .= '<div class="' . $day_class . '">';
            $output .= '<label class="dnd-day-toggle">';
            $output .= '<input type="checkbox" name="days[' . $day_key . '][enabled]" ' . $checked . ' />';
            $output .= '<span class="dnd-toggle-slider"></span>';
            $output .= '<span class="dnd-day-name">' . $day_name . ', ' . $formatted_date . '</span>';
            $output .= '</label>';

            $output .= '<div class="dnd-time-settings" style="' . ($day_data['enabled'] ? '' : 'display: none;') . '">';
            $output .= '<div class="dnd-time-slots">';

            $time_slots = $day_data['time_slots'] ?? [['start' => '09:00', 'end' => '17:00']];
            
            // Sort time slots by start time for display
            usort($time_slots, function($a, $b) {
                return strtotime($a['start']) - strtotime($b['start']);
            });
            
            foreach ($time_slots as $index => $slot) {
                $output .= '<div class="dnd-time-slot" data-slot-index="' . $index . '">';
                $output .= '<div class="dnd-time-inputs">';
                $output .= '<label>Start: <input type="time" name="days[' . $day_key . '][time_slots][' . $index . '][start]" value="' . esc_attr($slot['start']) . '" /></label>';
                $output .= '<label>End: <input type="time" name="days[' . $day_key . '][time_slots][' . $index . '][end]" value="' . esc_attr($slot['end']) . '" /></label>';
                $output .= '<button type="button" class="dnd-remove-slot" ' . (count($time_slots) > 1 ? '' : 'style="display: none;"') . '>Remove</button>';
                $output .= '</div>';
                $output .= '</div>';
            }

            $output .= '<button type="button" class="dnd-add-slot">Add Time Slot</button>';
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