<?php
/**
 * Gutenberg block for teacher requests
 */

class DND_Speaking_Teacher_Requests_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/teacher-requests', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-teacher-requests-editor',
            plugin_dir_url(__FILE__) . 'teacher-requests-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-teacher-requests-editor-style',
            plugin_dir_url(__FILE__) . 'teacher-requests-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-teacher-requests-style',
            plugin_dir_url(__FILE__) . 'teacher-requests-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/teacher-requests')) {
            wp_enqueue_script(
                'dnd-speaking-teacher-requests',
                plugin_dir_url(__FILE__) . 'teacher-requests-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-teacher-requests', 'dnd_teacher_requests_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('teacher_requests_nonce'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-teacher-requests"><p>Vui lòng đăng nhập để xem requests.</p></div>';
        }

        $user_id = get_current_user_id();

        // Get pending requests (sessions that are booked but not yet started)
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as student_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
             WHERE s.teacher_id = %d AND s.status = 'pending'
             ORDER BY s.start_time DESC",
            $user_id
        ));

        $output = '<div class="dnd-teacher-requests">';
        $output .= '<h3>Requests</h3>';

        if (empty($requests)) {
            $output .= '<div class="dnd-no-requests">No pending requests</div>';
        } else {
            $output .= '<div class="dnd-requests-list">';
            foreach ($requests as $request) {
                $output .= '<div class="dnd-request-item" data-session-id="' . $request->id . '">';
                $output .= '<div class="dnd-request-info">';
                $output .= '<div class="dnd-student-name">' . esc_html($request->student_name) . '</div>';
                $output .= '<div class="dnd-request-time">Requested: ' . date('M j, Y g:i A', strtotime($request->start_time)) . '</div>';
                $output .= '</div>';
                $output .= '<div class="dnd-request-actions">';
                $output .= '<button class="dnd-btn dnd-btn-accept" data-action="accept">Accept</button>';
                $output .= '<button class="dnd-btn dnd-btn-decline" data-action="decline">Decline</button>';
                $output .= '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }
}

// Initialize the block
new DND_Speaking_Teacher_Requests_Block();