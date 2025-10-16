<?php
/**
 * Gutenberg block for teacher dashboard header
 */

class DND_Speaking_Teacher_Header_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/teacher-header', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-teacher-header-editor',
            plugin_dir_url(__FILE__) . 'teacher-header-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-teacher-header-editor-style',
            plugin_dir_url(__FILE__) . 'teacher-header-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-teacher-header-style',
            plugin_dir_url(__FILE__) . 'teacher-header-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/teacher-header')) {
            wp_enqueue_script(
                'dnd-speaking-teacher-header',
                plugin_dir_url(__FILE__) . 'teacher-header-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-teacher-header', 'dnd_teacher_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('dnd-speaking/v1/'),
                'nonce' => wp_create_nonce('update_teacher_availability_nonce'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-teacher-header"><p>Vui lòng đăng nhập để xem dashboard.</p></div>';
        }

        $user_id = get_current_user_id();
        $available = get_user_meta($user_id, 'dnd_available', true) == '1';

        // Get teacher stats
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table WHERE teacher_id = %d AND status = 'completed'",
            $user_id
        ));

        $upcoming_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table WHERE teacher_id = %d AND status = 'active'",
            $user_id
        ));

        $output = '<div class="dnd-teacher-header">';
        $output .= '<div class="dnd-teacher-header-content">';

        // Availability Toggle
        $output .= '<div class="dnd-availability-toggle">';
        $output .= '<label class="dnd-toggle-label">';
        $output .= '<input type="checkbox" id="dnd-teacher-available" ' . ($available ? 'checked' : '') . '>';
        $output .= '<span class="dnd-toggle-slider"></span>';
        $output .= '</label>';
        $output .= '<span class="dnd-toggle-text">I\'m available</span>';
        $output .= '</div>';

        // Stats
        $output .= '<div class="dnd-teacher-stats">';
        $output .= '<div class="dnd-stat-item">';
        $output .= '<span class="dnd-stat-number">' . $total_sessions . '</span>';
        $output .= '<span class="dnd-stat-label">Total Sessions</span>';
        $output .= '</div>';
        $output .= '<div class="dnd-stat-item">';
        $output .= '<span class="dnd-stat-number">' . $upcoming_sessions . '</span>';
        $output .= '<span class="dnd-stat-label">Upcoming</span>';
        $output .= '</div>';
        $output .= '</div>';

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }
}

// Initialize the block
new DND_Speaking_Teacher_Header_Block();