<?php
/**
 * Gutenberg block for Discord connection
 */

class DND_Speaking_Discord_Connect_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/discord-connect', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-discord-connect-block-editor',
            plugin_dir_url(__FILE__) . 'discord-connect-block.js',
            ['wp-blocks', 'wp-element'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-discord-connect-block-editor-style',
            plugin_dir_url(__FILE__) . 'discord-connect-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-discord-connect-block-style',
            plugin_dir_url(__FILE__) . 'discord-connect-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/discord-connect')) {
            wp_enqueue_script(
                'dnd-speaking-discord-connect-block',
                plugin_dir_url(__FILE__) . 'discord-connect-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-discord-connect-block', 'dnd_discord_data', [
                'user_id' => get_current_user_id(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('dnd-speaking/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }

    public function render_block($attributes) {
        if (!is_user_logged_in()) {
            return '<div class="dnd-discord-connect-block"><p>Vui lòng đăng nhập để kết nối Discord.</p></div>';
        }

        $discord_connected = get_user_meta(get_current_user_id(), 'discord_connected', true);
        $button_text = $discord_connected ? 'Disconnect from Discord' : 'Connect to Discord';
        $button_class = $discord_connected ? 'dnd-btn-disconnect' : 'dnd-btn-connect';

        $output = '<div class="dnd-discord-connect-block">';
        $output .= '<button class="dnd-btn dnd-btn-discord ' . $button_class . '" id="dnd-discord-connect-btn">' . $button_text . '</button>';
        $output .= '<div id="dnd-discord-status"></div>';
        $output .= '</div>';

        return $output;
    }
}

// Initialize the block
new DND_Speaking_Discord_Connect_Block();