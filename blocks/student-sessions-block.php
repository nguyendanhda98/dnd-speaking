<?php
/**
 * Gutenberg block for displaying student sessions
 */

class DND_Speaking_Student_Sessions_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/student-sessions', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-student-sessions-block-editor',
            plugin_dir_url(__FILE__) . 'student-sessions-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-student-sessions-block-editor-style',
            plugin_dir_url(__FILE__) . 'student-sessions-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-student-sessions-block-style',
            plugin_dir_url(__FILE__) . 'student-sessions-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/student-sessions')) {
            wp_enqueue_script(
                'dnd-speaking-student-sessions-block',
                plugin_dir_url(__FILE__) . 'student-sessions-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-student-sessions-block', 'dnd_speaking_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('dnd-speaking/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-student-sessions-block"><p>Vui lòng đăng nhập để xem lịch học của bạn.</p></div>';
        }

        $user_id = get_current_user_id();
        $filter = isset($_GET['student_sessions_filter']) ? sanitize_text_field($_GET['student_sessions_filter']) : 'all';
        $per_page = isset($_GET['student_sessions_per_page']) ? intval($_GET['student_sessions_per_page']) : 10;
        $allowed_per_page = [1, 3, 5, 10];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 10;
        }
        $page = isset($_GET['student_sessions_page']) ? intval($_GET['student_sessions_page']) : 1;
        $offset = ($page - 1) * $per_page;

        global $wpdb;

        // Calculate total hours for completed sessions
        $total_hours = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(s.duration) / 60.0 FROM {$wpdb->prefix}dnd_speaking_sessions s WHERE s.student_id = %d AND s.status = 'completed'",
            $user_id
        )) ?: 0;

        $output = '<div class="dnd-student-sessions-block">';
        $output .= '<h3>Student Sessions</h3>';

        // Total hours at top
        $output .= '<div class="dnd-total-hours">Số giờ đã học: ' . number_format($total_hours, 1) . 'h</div>';

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
            $active = ($filter === $key) ? ' active' : '';
            $output .= '<button class="dnd-filter-btn' . $active . '" data-filter="' . $key . '">' . $label . ' (' . $count . ')</button>';
        }
        $output .= '</div>';

        // Per page filter
        $output .= '<div class="dnd-per-page-filter">';
        $output .= '<label for="student_sessions_per_page">Hiển thị:</label>';
        $output .= '<select id="student_sessions_per_page" name="student_sessions_per_page">';
        foreach ($allowed_per_page as $option) {
            $selected = ($per_page == $option) ? ' selected' : '';
            $output .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
        }
        $output .= '</select>';
        $output .= '</div>';

        // Build WHERE clause based on filter
        $where_clause = "s.student_id = %d";
        $query_params = [$user_id];

        switch ($filter) {
            case 'pending':
                $where_clause .= " AND s.status = 'pending'";
                break;
            case 'confirmed':
                $where_clause .= " AND s.status = 'confirmed'";
                break;
            case 'completed':
                $where_clause .= " AND s.status = 'completed'";
                break;
            case 'cancelled':
                $where_clause .= " AND s.status = 'cancelled'";
                break;
            default:
                // All: include all statuses
                break;
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, t.display_name as teacher_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} t ON s.teacher_id = t.ID
             WHERE $where_clause
             ORDER BY 
                 CASE 
                     WHEN s.status IN ('pending', 'confirmed', 'in_progress') THEN 1
                     WHEN s.status = 'completed' THEN 2
                     ELSE 3
                 END,
                 s.session_date DESC, s.session_time DESC
             LIMIT %d OFFSET %d",
            array_merge($query_params, [$per_page, $offset])
        ));

        // Get total count for pagination
        $total_sessions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sessions_table WHERE $where_clause", $query_params));
        $total_pages = ceil($total_sessions / $per_page);

        $output .= '<div class="dnd-student-sessions-list" id="dnd-student-sessions-list" data-filter="all" data-per-page="10" data-page="1">';
        $output .= '<div class="dnd-loading">Loading...</div>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    private function get_session_count($user_id, $filter) {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $where_clause = "student_id = %d";
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
                // All
                break;
        }

        return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sessions_table WHERE $where_clause", $query_params));
    }

    private function render_session_card($session) {
        $status_text = '';
        $status_class = '';
        $actions = '';

        switch ($session->status) {
            case 'pending':
                $status_text = 'Chờ xác nhận';
                $status_class = 'pending';
                $actions = '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy</button>';
                break;
            case 'confirmed':
                $status_text = 'Đã xác nhận';
                $status_class = 'confirmed';
                $actions = '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy</button>';
                // Check if within 15 minutes before start
                if (!empty($session->session_date) && !empty($session->session_time)) {
                    $session_datetime = strtotime($session->session_date . ' ' . $session->session_time);
                    if (time() >= ($session_datetime - 15 * 60) && time() < $session_datetime) {
                        $actions .= '<button class="dnd-btn dnd-btn-join" data-session-id="' . $session->id . '">Tham gia ngay</button>';
                    }
                }
                break;
            case 'completed':
                $status_text = 'Hoàn thành';
                $status_class = 'completed';
                // Check if rated
                $actions = '<button class="dnd-btn dnd-btn-rate" data-session-id="' . $session->id . '">Đánh giá</button>';
                $actions .= '<button class="dnd-btn dnd-btn-feedback" data-session-id="' . $session->id . '">Giáo viên phản hồi</button>';
                break;
            case 'cancelled':
                $status_text = 'Đã huỷ';
                $status_class = 'cancelled';
                $actions = ''; // No actions for cancelled
                break;
        }

        $scheduled_time = 'N/A';
        if (!empty($session->session_date) && !empty($session->session_time)) {
            $scheduled_time = date('d/m/Y H:i', strtotime($session->session_date . ' ' . $session->session_time));
        }

        return '
            <div class="dnd-session-card" data-session-id="' . $session->id . '">
                <div class="dnd-session-teacher">Giáo viên: ' . esc_html($session->teacher_name ?: 'N/A') . '</div>
                <div class="dnd-session-status ' . $status_class . '">Trạng thái: ' . $status_text . '</div>
                <div class="dnd-session-time">Thời gian: ' . $scheduled_time . '</div>
                <div class="dnd-session-actions">' . $actions . '</div>
            </div>
        ';
    }
}

// Initialize the block
new DND_Speaking_Student_Sessions_Block();