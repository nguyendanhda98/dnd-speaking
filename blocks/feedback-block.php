<?php
/**
 * Gutenberg block for teacher feedback
 */

class DND_Speaking_Feedback_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/feedback', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-feedback-editor',
            plugin_dir_url(__FILE__) . 'feedback-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-feedback-editor-style',
            plugin_dir_url(__FILE__) . 'feedback-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-feedback-style',
            plugin_dir_url(__FILE__) . 'feedback-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/feedback')) {
            wp_enqueue_script(
                'dnd-speaking-feedback',
                plugin_dir_url(__FILE__) . 'feedback-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-feedback', 'dnd_feedback_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('feedback_nonce'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-feedback"><p>Vui lòng đăng nhập để xem feedback.</p></div>';
        }

        $user_id = get_current_user_id();

        // Get feedback from completed sessions
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $feedback_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as student_name, s.feedback, s.rating
             FROM $sessions_table s
             LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
             WHERE s.teacher_id = %d AND s.status = 'completed' AND s.feedback IS NOT NULL AND s.feedback != ''
             ORDER BY s.session_date DESC, s.session_time DESC
             LIMIT 20",
            $user_id
        ));

        $output = '<div class="dnd-feedback">';
        $output .= '<h3>Student Feedback</h3>';

        if (empty($feedback_sessions)) {
            $output .= '<div class="dnd-no-feedback">No feedback available yet</div>';
        } else {
            // Calculate average rating
            $total_rating = 0;
            $rating_count = 0;
            foreach ($feedback_sessions as $session) {
                if ($session->rating) {
                    $total_rating += intval($session->rating);
                    $rating_count++;
                }
            }

            $average_rating = $rating_count > 0 ? round($total_rating / $rating_count, 1) : 0;

            $output .= '<div class="dnd-feedback-summary">';
            $output .= '<div class="dnd-average-rating">';
            $output .= '<span class="dnd-rating-number">' . $average_rating . '</span>';
            $output .= '<span class="dnd-rating-stars">' . $this->render_stars($average_rating) . '</span>';
            $output .= '<span class="dnd-rating-count">(' . $rating_count . ' reviews)</span>';
            $output .= '</div>';
            $output .= '</div>';

            $output .= '<div class="dnd-feedback-list">';
            foreach ($feedback_sessions as $session) {
                $formatted_date = date('M j, Y', strtotime($session->session_date));

                $output .= '<div class="dnd-feedback-item">';
                $output .= '<div class="dnd-feedback-header">';
                $output .= '<div class="dnd-student-name">' . esc_html($session->student_name) . '</div>';
                $output .= '<div class="dnd-feedback-date">' . $formatted_date . '</div>';
                $output .= '</div>';

                if ($session->rating) {
                    $output .= '<div class="dnd-feedback-rating">';
                    $output .= $this->render_stars($session->rating);
                    $output .= '<span class="dnd-rating-text">' . $session->rating . '/5</span>';
                    $output .= '</div>';
                }

                $output .= '<div class="dnd-feedback-content">';
                $output .= '<p>' . esc_html($session->feedback) . '</p>';
                $output .= '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    private function render_stars($rating) {
        $stars = '';
        $full_stars = floor($rating);
        $has_half_star = ($rating - $full_stars) >= 0.5;

        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full_stars) {
                $stars .= '★';
            } elseif ($i === $full_stars + 1 && $has_half_star) {
                $stars .= '☆';
            } else {
                $stars .= '☆';
            }
        }

        return $stars;
    }
}

// Initialize the block
new DND_Speaking_Feedback_Block();