<?php
/**
 * Gutenberg block for session history
 */

class DND_Speaking_Session_History_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/session-history', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-session-history-editor',
            plugin_dir_url(__FILE__) . 'session-history-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-session-history-editor-style',
            plugin_dir_url(__FILE__) . 'session-history-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-session-history-style',
            plugin_dir_url(__FILE__) . 'session-history-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/session-history')) {
            wp_enqueue_script(
                'dnd-speaking-session-history',
                plugin_dir_url(__FILE__) . 'session-history-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-session-history', 'dnd_session_history_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('dnd-speaking/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-session-history"><p>Vui lòng đăng nhập để xem session history.</p></div>';
        }

        $user_id = get_current_user_id();

        global $wpdb;

        $output = '<div class="dnd-session-history">';
        $output .= '<h3>Teacher Session</h3>';

        // Time period filters - Day, Month and Year selection
        $output .= '<div class="dnd-time-period-filters">';
        $output .= '<label>Lọc theo thời gian:</label>';
        
        // Day input
        $output .= '<input type="number" id="filter_day" name="filter_day" placeholder="Ngày" min="1" max="31" step="1" />';
        
        // Month selector
        $output .= '<select id="filter_month" name="filter_month">';
        $output .= '<option value="">Tất cả tháng</option>';
        $months = [
            '01' => 'Tháng 1', '02' => 'Tháng 2', '03' => 'Tháng 3',
            '04' => 'Tháng 4', '05' => 'Tháng 5', '06' => 'Tháng 6',
            '07' => 'Tháng 7', '08' => 'Tháng 8', '09' => 'Tháng 9',
            '10' => 'Tháng 10', '11' => 'Tháng 11', '12' => 'Tháng 12'
        ];
        foreach ($months as $value => $label) {
            $output .= '<option value="' . $value . '">' . $label . '</option>';
        }
        $output .= '</select>';
        
        // Year input
        $current_year = date('Y');
        $output .= '<input type="number" id="filter_year" name="filter_year" placeholder="Năm (VD: ' . $current_year . ')" min="2000" max="2100" step="1" />';
        
        // Apply button
        $output .= '<button type="button" id="apply_time_filter" class="dnd-btn-apply">Áp dụng</button>';
        $output .= '</div>';

        // Filters
        $output .= '<div class="dnd-sessions-filters">';
        $filters = [
            'all' => 'Tất cả',
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã huỷ'
        ];
        foreach ($filters as $key => $label) {
            $count = $this->get_session_count($user_id, $key);
            $active = ($key === 'all') ? ' active' : '';
            $output .= '<button class="dnd-filter-btn' . $active . '" data-filter="' . $key . '">' . $label . ' (' . $count . ')</button>';
        }
        $output .= '</div>';

        // Per page filter
        $output .= '<div class="dnd-per-page-filter">';
        $output .= '<label for="session_history_per_page">Hiển thị:</label>';
        $output .= '<select id="session_history_per_page" name="session_history_per_page">';
        $allowed_per_page = [1, 3, 5, 10];
        foreach ($allowed_per_page as $option) {
            $selected = ($option == 10) ? ' selected' : '';
            $output .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
        }
        $output .= '</select>';
        $output .= '</div>';

        // Content will be loaded via AJAX
        $output .= '<div class="dnd-session-history-list" id="dnd-session-history-list" data-filter="all" data-filter-day="" data-filter-month="" data-filter-year="" data-per-page="10" data-page="1">';
        $output .= '<div class="dnd-loading">Loading...</div>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    private function get_session_count($user_id, $filter) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $where_clause = "teacher_id = %d";
        $query_params = [$user_id];

        switch ($filter) {
            case 'pending':
                $where_clause .= " AND status = 'pending'";
                break;
            case 'confirmed':
                $where_clause .= " AND status IN ('confirmed', 'in_progress')";
                break;
            case 'completed':
                $where_clause .= " AND status = 'completed'";
                break;
            case 'cancelled':
                $where_clause .= " AND status = 'cancelled'";
                break;
            default:
                // All: include all statuses
                break;
        }

        return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sessions_table WHERE $where_clause", $query_params));
    }
}

// Initialize the block
new DND_Speaking_Session_History_Block();