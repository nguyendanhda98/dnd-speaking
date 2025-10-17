<?php
/**
 * Gutenberg block for displaying teachers list
 */

class DND_Speaking_Teachers_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/teachers-list', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-teachers-block-editor',
            plugin_dir_url(__FILE__) . 'teachers-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-teachers-block-editor-style',
            plugin_dir_url(__FILE__) . 'teachers-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-teachers-block-style',
            plugin_dir_url(__FILE__) . 'teachers-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/teachers-list')) {
            wp_enqueue_script(
                'dnd-speaking-teachers-block',
                plugin_dir_url(__FILE__) . 'teachers-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-teachers-block', 'dnd_speaking_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('dnd-speaking/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-teachers-block"><p>Vui lòng đăng nhập để xem danh sách giáo viên.</p></div>';
        }

        $output = '<div class="dnd-teachers-block">';
        $output .= '<div class="dnd-teachers-list" id="dnd-teachers-list"></div>';
        $output .= '</div>';

        return $output;
    }
}

// Initialize the block
new DND_Speaking_Teachers_Block();