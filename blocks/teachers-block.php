<?php
/**
 * Gutenberg block for displaying teachers list
 */

class DND_Speaking_Teachers_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/teachers-list', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-teachers-block-editor',
            plugin_dir_url(__FILE__) . 'teachers-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-teachers-block-editor-style',
            plugin_dir_url(__FILE__) . 'teachers-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-teachers-block-style',
            plugin_dir_url(__FILE__) . 'teachers-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/teachers-list')) {
            wp_enqueue_script(
                'dnd-speaking-teachers-block',
                plugin_dir_url(__FILE__) . 'teachers-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-teachers-block', 'dnd_speaking_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('dnd-speaking/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-teachers-block"><p>Vui lòng đăng nhập để xem danh sách giáo viên.</p></div>';
        }

        $output = '<div class="dnd-teachers-block">';
        
        // Add filter section
        $output .= '<div class="dnd-teachers-filter">';
        $output .= '<h3 class="dnd-filter-title">Lọc theo thời gian rảnh</h3>';
        $output .= '<div class="dnd-filter-controls">';
        
        // Single row with all 3 controls
        $output .= '<div class="dnd-filter-row">';
        
        // Day of week dropdown (single selection)
        $output .= '<div class="dnd-filter-item">';
        $output .= '<label class="dnd-filter-label">Thứ:</label>';
        $output .= '<select id="dnd-day-select" class="dnd-select">';
        $output .= '<option value="">Tất cả các thứ</option>';
        $days = [
            'monday' => 'Thứ 2',
            'tuesday' => 'Thứ 3',
            'wednesday' => 'Thứ 4',
            'thursday' => 'Thứ 5',
            'friday' => 'Thứ 6',
            'saturday' => 'Thứ 7',
            'sunday' => 'Chủ nhật'
        ];
        foreach ($days as $value => $label) {
            $output .= '<option value="' . $value . '">' . $label . '</option>';
        }
        $output .= '</select>';
        $output .= '</div>';
        
        // Time range selectors
        $output .= '<div class="dnd-filter-item">';
        $output .= '<label class="dnd-filter-label">Từ giờ:</label>';
        $output .= '<input type="time" id="dnd-start-time" class="dnd-time-input" value="00:00" />';
        $output .= '</div>';
        
        $output .= '<div class="dnd-filter-item">';
        $output .= '<label class="dnd-filter-label">Đến giờ:</label>';
        $output .= '<input type="time" id="dnd-end-time" class="dnd-time-input" value="23:30" />';
        $output .= '</div>';
        
        // Filter buttons
        $output .= '<div class="dnd-filter-item dnd-filter-actions">';
        $output .= '<button type="button" id="dnd-apply-filter" class="dnd-btn dnd-btn-primary">Lọc</button>';
        $output .= '<button type="button" id="dnd-reset-filter" class="dnd-btn dnd-btn-secondary">Xóa</button>';
        $output .= '</div>';
        
        $output .= '</div>'; // .dnd-filter-row
        $output .= '</div>'; // .dnd-filter-controls
        $output .= '</div>'; // .dnd-teachers-filter
        
        $output .= '<div class="dnd-teachers-list" id="dnd-teachers-list"></div>';
        $output .= '</div>';

        return $output;
    }

    private function generate_time_options($default = '00:00') {
        $options = '';
        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $time = sprintf('%02d:%02d', $hour, $minute);
                $selected = ($time === $default) ? ' selected' : '';
                $options .= '<option value="' . $time . '"' . $selected . '>' . $time . '</option>';
            }
        }
        return $options;
    }
}

// Initialize the block
new DND_Speaking_Teachers_Block();