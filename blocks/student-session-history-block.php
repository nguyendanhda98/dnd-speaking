<?php
/**
 * Gutenberg block for student session history
 */

class DND_Speaking_Student_Session_History_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/student-session-history', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-student-session-history-editor',
            plugin_dir_url(__FILE__) . 'student-session-history-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-student-session-history-editor-style',
            plugin_dir_url(__FILE__) . 'student-session-history-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-student-session-history-style',
            plugin_dir_url(__FILE__) . 'student-session-history-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/student-session-history')) {
            wp_enqueue_script(
                'dnd-speaking-student-session-history',
                plugin_dir_url(__FILE__) . 'student-session-history-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-student-session-history', 'dnd_student_session_history_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('student_session_history_nonce'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-student-session-history"><p>Vui lòng đăng nhập để xem lịch sử buổi học.</p></div>';
        }

        $user_id = get_current_user_id();
        $page = isset($_GET['student_history_page']) ? intval($_GET['student_history_page']) : 1;
        $per_page = isset($_GET['student_per_page']) ? intval($_GET['student_per_page']) : 10;
        $allowed_per_page = [1, 3, 5, 10];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 10;
        }
        $offset = ($page - 1) * $per_page;

        // Get filter
        $status_filter = isset($_GET['student_status_filter']) ? sanitize_text_field($_GET['student_status_filter']) : 'all';
        $allowed_filters = ['all', 'completed', 'cancelled'];
        if (!in_array($status_filter, $allowed_filters)) {
            $status_filter = 'all';
        }

        // Build WHERE clause based on filter
        $where_clause = "s.student_id = %d";
        $query_params = [$user_id];

        if ($status_filter !== 'all') {
            $where_clause .= " AND s.status = %s";
            $query_params[] = $status_filter;
        } else {
            $where_clause .= " AND s.status IN ('completed', 'cancelled')";
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, t.display_name as teacher_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} t ON s.teacher_id = t.ID
             WHERE $where_clause
             ORDER BY s.session_date DESC, s.session_time DESC
             LIMIT %d OFFSET %d",
            array_merge($query_params, [$per_page, $offset])
        ));

        // Get total count for pagination
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s WHERE $where_clause",
            $query_params
        ));

        $total_pages = ceil($total_sessions / $per_page);

        // Get cancelled_by names if column exists and needed
        $cancelled_by_names = [];
        $columns = $wpdb->get_col("DESCRIBE $sessions_table");
        if (in_array('cancelled_by', $columns) && ($status_filter === 'all' || $status_filter === 'cancelled')) {
            $cancel_where = "s.student_id = %d AND s.status = 'cancelled'";
            $cancel_params = [$user_id];

            $cancelled_sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT s.id, cu.display_name as cancelled_by_name, s.cancelled_at
                 FROM $sessions_table s
                 LEFT JOIN {$wpdb->users} cu ON s.cancelled_by = cu.ID
                 WHERE $cancel_where",
                $cancel_params
            ));
            if ($cancelled_sessions) {
                foreach ($cancelled_sessions as $cs) {
                    $cancelled_by_names[$cs->id] = [
                        'name' => $cs->cancelled_by_name,
                        'at' => $cs->cancelled_at
                    ];
                }
            }
        }

        $output = '<div class="dnd-student-session-history">';
        $output .= '<h3>My Session History</h3>';

        // Add filter form
        $output .= '<div class="dnd-history-filters">';
        $output .= '<form method="GET" class="dnd-filter-form" id="dnd-student-history-filter-form">';
        $output .= '<div class="dnd-filter-row">';
        $output .= '<div class="dnd-filter-group">';
        $output .= '<label for="student_status_filter">Filter by status:</label>';
        $output .= '<select name="student_status_filter" id="student_status_filter">';
        $output .= '<option value="all"' . ($status_filter === 'all' ? ' selected' : '') . '>All Sessions</option>';
        $output .= '<option value="completed"' . ($status_filter === 'completed' ? ' selected' : '') . '>Completed</option>';
        $output .= '<option value="cancelled"' . ($status_filter === 'cancelled' ? ' selected' : '') . '>Cancelled</option>';
        $output .= '</select>';
        $output .= '</div>';
        $output .= '<div class="dnd-filter-group">';
        $output .= '<label for="student_per_page">Items per page:</label>';
        $output .= '<select name="student_per_page" id="student_per_page">';
        foreach ($allowed_per_page as $option) {
            $selected = ($per_page == $option) ? ' selected' : '';
            $output .= '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
        }
        $output .= '</select>';
        $output .= '</div>';
        $output .= '<div class="dnd-filter-group">';
        $output .= '<button type="submit" class="dnd-filter-submit">Apply Filters</button>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</form>';
        $output .= '</div>';

        if (empty($sessions)) {
            $output .= '<div class="dnd-no-history">No session history available</div>';
        } else {
            $output .= '<div class="dnd-history-list">';
            foreach ($sessions as $session) {
                $session_datetime = $session->session_date . ' ' . $session->session_time;
                $formatted_date = date('M j, Y', strtotime($session->session_date));
                $formatted_time = date('g:i A', strtotime($session->session_time));

                $status_class = $session->status === 'completed' ? 'completed' : 'cancelled';
                $status_text = $session->status === 'completed' ? 'Completed' : 'Cancelled';

                $duration = isset($session->duration) ? $session->duration . ' min' : 'N/A';

                $output .= '<div class="dnd-history-item ' . $status_class . '">';
                $output .= '<div class="dnd-history-header">';
                $output .= '<div class="dnd-teacher-name">' . esc_html($session->teacher_name) . '</div>';
                $output .= '<div class="dnd-session-status ' . $status_class . '">' . $status_text . '</div>';
                $output .= '</div>';

                $output .= '<div class="dnd-history-details">';
                $output .= '<div class="dnd-session-datetime">' . $formatted_date . ' at ' . $formatted_time . '</div>';
                $output .= '<div class="dnd-session-duration">Duration: ' . $duration . '</div>';

                if ($session->status === 'cancelled' && isset($cancelled_by_names[$session->id])) {
                    $cancel_info = $cancelled_by_names[$session->id];
                    $cancelled_at = !empty($cancel_info['at']) ? date('M j, Y g:i A', strtotime($cancel_info['at'])) : 'N/A';
                    $output .= '<div class="dnd-session-cancellation">';
                    $output .= '<strong>Cancelled by:</strong> ' . esc_html($cancel_info['name']) . '<br>';
                    $output .= '<strong>Cancelled at:</strong> ' . $cancelled_at;
                    $output .= '</div>';
                }

                $output .= '</div>';

                if ($session->status === 'completed' && !empty($session->feedback)) {
                    $output .= '<div class="dnd-session-feedback">';
                    $output .= '<strong>Feedback:</strong> ' . esc_html($session->feedback);
                    $output .= '</div>';
                }

                $output .= '</div>';
            }
            $output .= '</div>';

            // Pagination
            if ($total_pages > 1) {
                $filter_param = $status_filter !== 'all' ? '&student_status_filter=' . $status_filter : '';
                $per_page_param = '&student_per_page=' . $per_page;
                $output .= '<div class="dnd-pagination">';
                if ($page > 1) {
                    $output .= '<a href="?student_history_page=' . ($page - 1) . $filter_param . $per_page_param . '" class="dnd-page-link">Previous</a>';
                }

                for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                    $active_class = ($i === $page) ? ' active' : '';
                    $output .= '<a href="?student_history_page=' . $i . $filter_param . $per_page_param . '" class="dnd-page-link' . $active_class . '">' . $i . '</a>';
                }

                if ($page < $total_pages) {
                    $output .= '<a href="?student_history_page=' . ($page + 1) . $filter_param . $per_page_param . '" class="dnd-page-link">Next</a>';
                }
                $output .= '</div>';
            }
        }

        $output .= '</div>';

        return $output;
    }
}

// Initialize the block
new DND_Speaking_Student_Session_History_Block();