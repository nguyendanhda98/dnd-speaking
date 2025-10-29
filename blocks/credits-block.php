<?php
/**
 * Gutenberg block for displaying user credits
 */

class DND_Speaking_Credits_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
    }

    public function register_block() {
        // Register editor script and style
        wp_register_script(
            'dnd-speaking-credits-block-editor',
            plugin_dir_url(__FILE__) . 'credits-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor'],
            '1.0.0'
        );

        wp_register_style(
            'dnd-speaking-credits-block-editor-style',
            plugin_dir_url(__FILE__) . 'credits-block.css',
            [],
            '1.0.0'
        );

        wp_register_style(
            'dnd-speaking-credits-block-style',
            plugin_dir_url(__FILE__) . 'credits-block.css',
            [],
            '1.0.0'
        );

        register_block_type('dnd-speaking/credits-display', [
            'editor_script' => 'dnd-speaking-credits-block-editor',
            'editor_style' => 'dnd-speaking-credits-block-editor-style',
            'style' => 'dnd-speaking-credits-block-style',
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-credits-display">Vui lòng đăng nhập để xem số buổi học.</div>';
        }

        $user_id = get_current_user_id();
        $lessons = DND_Speaking_Helpers::get_user_lessons($user_id);

        return '<div class="dnd-credits-display">Số buổi học hiện có: <strong>' . $lessons . '</strong></div>';
    }
}

// Initialize the block
new DND_Speaking_Credits_Block();