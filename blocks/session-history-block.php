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
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        // Get completed sessions
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $completed_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as student_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
             WHERE s.teacher_id = %d AND s.status IN ('completed', 'cancelled')
             ORDER BY s.session_date DESC, s.session_time DESC
             LIMIT %d OFFSET %d",
            $user_id, $per_page, $offset
        ));

        // Get total count for pagination
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table
             WHERE teacher_id = %d AND status IN ('completed', 'cancelled')",
            $user_id
        ));

        $total_pages = ceil($total_sessions / $per_page);

        $output = '<div class="dnd-session-history">';
        $output .= '<h3>Session History</h3>';

        if (empty($completed_sessions)) {
            $output .= '<div class="dnd-no-history">No session history available</div>';
        } else {
            $output .= '<div class="dnd-history-list">';
            foreach ($completed_sessions as $session) {
                $session_datetime = $session->session_date . ' ' . $session->session_time;
                $formatted_date = date('M j, Y', strtotime($session->session_date));
                $formatted_time = date('g:i A', strtotime($session->session_time));

                $status_class = $session->status === 'completed' ? 'completed' : 'cancelled';
                $status_text = $session->status === 'completed' ? 'Completed' : 'Cancelled';

                $duration = isset($session->duration) ? $session->duration . ' min' : 'N/A';

                $output .= '<div class="dnd-history-item ' . $status_class . '">';
                $output .= '<div class="dnd-history-header">';
                $output .= '<div class="dnd-student-name">' . esc_html($session->student_name) . '</div>';
                $output .= '<div class="dnd-session-status ' . $status_class . '">' . $status_text . '</div>';
                $output .= '</div>';

                $output .= '<div class="dnd-history-details">';
                $output .= '<div class="dnd-session-datetime">' . $formatted_date . ' at ' . $formatted_time . '</div>';
                $output .= '<div class="dnd-session-duration">Duration: ' . $duration . '</div>';
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
                $output .= '<div class="dnd-pagination">';
                if ($page > 1) {
                    $output .= '<a href="?history_page=' . ($page - 1) . '" class="dnd-page-link">Previous</a>';
                }

                for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                    $active_class = ($i === $page) ? ' active' : '';
                    $output .= '<a href="?history_page=' . $i . '" class="dnd-page-link' . $active_class . '">' . $i . '</a>';
                }

                if ($page < $total_pages) {
                    $output .= '<a href="?history_page=' . ($page + 1) . '" class="dnd-page-link">Next</a>';
                }
                $output .= '</div>';
            }
        }

        $output .= '</div>';

        return $output;
    }
}

// Initialize the block
new DND_Speaking_Session_History_Block();