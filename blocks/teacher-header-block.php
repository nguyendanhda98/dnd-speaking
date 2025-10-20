<?php
/**
 * Gutenberg block for teacher status
 */

class DND_Speaking_Teacher_Status_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/teacher-status', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-teacher-status-editor',
            plugin_dir_url(__FILE__) . 'teacher-header-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-teacher-status-editor-style',
            plugin_dir_url(__FILE__) . 'teacher-header-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-teacher-status-style',
            plugin_dir_url(__FILE__) . 'teacher-header-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/teacher-status')) {
            wp_enqueue_script(
                'dnd-speaking-teacher-status',
                plugin_dir_url(__FILE__) . 'teacher-header-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-teacher-status', 'dnd_teacher_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('dnd-speaking/v1/'),
                'nonce' => wp_create_nonce('update_teacher_availability_nonce'),
                'rest_nonce' => wp_create_nonce('wp_rest'),
                'discord_auth_url' => get_option('dnd_discord_generated_url'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-teacher-status"><p>Vui lòng đăng nhập để xem dashboard.</p></div>';
        }

        $user_id = get_current_user_id();
        $status = get_user_meta($user_id, 'dnd_available', true);
        $invite_link = get_user_meta($user_id, 'discord_voice_channel_invite', true);
        
        // If status is 'busy', show as offline but keep room link
        // If status is '1', show as online
        // Otherwise (0 or empty), show as offline with no room
        $is_available = ($status == '1');
        $is_busy = ($status === 'busy');
        
        $room_link = $invite_link ?: '#';
        $room_text = $invite_link ? 'Tham gia phòng' : 'Link room';

        $output = '<div class="dnd-teacher-status">';
        
        // Phần 1: Trạng thái (show Offline when busy)
        $output .= '<div class="status-section">';
        $output .= '<span class="status-label">Trạng thái:</span>';
        $output .= '<div class="status-toggle-container">';
        $output .= '<span class="status-text offline">Offline</span>';
        $output .= '<label class="status-toggle-label">';
        $output .= '<input type="checkbox" id="teacher-status-toggle" ' . ($is_available ? 'checked' : '') . ($is_busy ? ' disabled' : '') . '>';
        $output .= '<span class="status-toggle-slider"></span>';
        $output .= '</label>';
        $output .= '<span class="status-text online">Online</span>';
        $output .= '</div>';
        if ($is_busy) {
            $output .= '<div class="status-info" style="color: #ff9800; font-size: 12px; margin-top: 5px;">Bạn đang trong buổi học</div>';
        }
        $output .= '<div id="discord-connect-message" class="discord-connect-message" style="display: none;"></div>';
        $output .= '</div>';
        
        // Phần 2: Room
        $output .= '<div class="room-section">';
        $output .= '<span class="room-label">Room:</span>';
        $output .= '<a href="' . esc_url($room_link) . '" class="room-link" target="_blank">' . esc_html($room_text) . '</a>';
        $output .= '</div>';
        
        $output .= '</div>';

        return $output;
    }
}

// Initialize the block
new DND_Speaking_Teacher_Status_Block();