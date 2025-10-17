<?php
/**
 * Gutenberg block for upcoming sessions
 */

class DND_Speaking_Upcoming_Sessions_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/upcoming-sessions', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-upcoming-sessions-editor',
            plugin_dir_url(__FILE__) . 'upcoming-sessions-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-upcoming-sessions-editor-style',
            plugin_dir_url(__FILE__) . 'upcoming-sessions-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-upcoming-sessions-style',
            plugin_dir_url(__FILE__) . 'upcoming-sessions-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/upcoming-sessions')) {
            wp_enqueue_script(
                'dnd-speaking-upcoming-sessions',
                plugin_dir_url(__FILE__) . 'upcoming-sessions-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-upcoming-sessions', 'dnd_upcoming_sessions_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('upcoming_sessions_nonce'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-upcoming-sessions"><p>Vui lòng đăng nhập để xem upcoming sessions.</p></div>';
        }

        $user_id = get_current_user_id();

        // Get confirmed upcoming sessions
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $upcoming_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as student_name
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
             WHERE s.teacher_id = %d AND s.status = 'confirmed' AND DATE(s.start_time) >= CURDATE()
             ORDER BY s.start_time ASC",
            $user_id
        ));

        $output = '<div class="dnd-upcoming-sessions">';
        $output .= '<h3>Upcoming Sessions</h3>';

        if (empty($upcoming_sessions)) {
            $output .= '<div class="dnd-no-sessions">No upcoming sessions</div>';
        } else {
            $output .= '<div class="dnd-sessions-list">';
            foreach ($upcoming_sessions as $session) {
                $formatted_date = date('M j, Y', strtotime($session->start_time));
                $formatted_time = date('g:i A', strtotime($session->start_time));

                $output .= '<div class="dnd-session-item" data-session-id="' . $session->id . '">';
                $output .= '<div class="dnd-session-info">';
                $output .= '<div class="dnd-student-name">' . esc_html($session->student_name) . '</div>';
                $output .= '<div class="dnd-session-datetime">' . $formatted_date . ' at ' . $formatted_time . '</div>';
                $output .= '</div>';
                $output .= '<div class="dnd-session-actions">';
                $output .= '<button class="dnd-btn dnd-btn-start" data-action="start">Start Session</button>';
                $output .= '<button class="dnd-btn dnd-btn-cancel" data-action="cancel">Cancel</button>';
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
new DND_Speaking_Upcoming_Sessions_Block();