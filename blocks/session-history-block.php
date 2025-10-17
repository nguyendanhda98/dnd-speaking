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
                'rest_url' => rest_url(),
                'nonce' => wp_create_nonce('session_history_nonce'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-session-history"><p>Vui lòng đăng nhập để xem session history.</p></div>';
        }

        $user_id = get_current_user_id();
        $page = isset($_GET['history_page']) ? intval($_GET['history_page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
        $allowed_per_page = [1, 3, 5, 10];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 10;
        }
        $offset = ($page - 1) * $per_page;
        
        // Get filter
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $allowed_filters = ['all', 'completed', 'cancelled'];
        if (!in_array($status_filter, $allowed_filters)) {
            $status_filter = 'all';
        }

        // Get completed sessions
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        // Build WHERE clause based on filter
        $where_clause = "s.teacher_id = %d";
        $query_params = [$user_id];
        
        if ($status_filter !== 'all') {
            $where_clause .= " AND s.status = %s";
            $query_params[] = $status_filter;
        } else {
            $where_clause .= " AND s.status IN ('completed', 'cancelled')";
        }

        $completed_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as student_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
             WHERE $where_clause
             ORDER BY s.session_date DESC, s.session_time DESC
             LIMIT %d OFFSET %d",
            array_merge($query_params, [$per_page, $offset])
        ));

        // Get cancelled_by names if column exists and needed
        $cancelled_by_names = [];
        $columns = $wpdb->get_col("DESCRIBE $sessions_table");
        if (in_array('cancelled_by', $columns) && ($status_filter === 'all' || $status_filter === 'cancelled')) {
            $cancel_where = "s.teacher_id = %d AND s.status = 'cancelled'";
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

        // Get total count for pagination
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table s WHERE $where_clause",
            $query_params
        ));

        $total_pages = ceil($total_sessions / $per_page);

        $output = '<div class="dnd-session-history">';
        $output .= '<h3>Session History</h3>';
        
        // Add filter form
        $output .= '<div class="dnd-history-filters">';
        $output .= '<form method="GET" class="dnd-filter-form" id="dnd-history-filter-form">';
        $output .= '<div class="dnd-filter-row">';
        $output .= '<div class="dnd-filter-group">';
        $output .= '<label for="status_filter">Filter by status:</label>';
        $output .= '<select name="status_filter" id="status_filter">';
        $output .= '<option value="all"' . ($status_filter === 'all' ? ' selected' : '') . '>All Sessions</option>';
        $output .= '<option value="completed"' . ($status_filter === 'completed' ? ' selected' : '') . '>Completed</option>';
        $output .= '<option value="cancelled"' . ($status_filter === 'cancelled' ? ' selected' : '') . '>Cancelled</option>';
        $output .= '</select>';
        $output .= '</div>';
        $output .= '<div class="dnd-filter-group">';
        $output .= '<label for="per_page">Items per page:</label>';
        $output .= '<select name="per_page" id="per_page">';
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

        // Content will be loaded via AJAX
        $output .= '<div class="dnd-history-content">';
        $output .= '<div class="dnd-loading" style="text-align: center; padding: 40px;">Loading session history...</div>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }
}

// Initialize the block
new DND_Speaking_Session_History_Block();