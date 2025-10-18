<?php
/**
 * Plugin Name: DND Speaking Sessions
 * Description: Basic management for speaking sessions.
 * Version: 1.0.0
 * Author: DND English
 */

if (!defined('ABSPATH')) exit;

// Autoload includes
foreach (glob(plugin_dir_path(__FILE__) . 'includes/*.php') as $file) {
    require_once $file;
}

// Autoload blocks
require_once plugin_dir_path(__FILE__) . 'blocks/credits-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/teachers-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/teacher-header-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/schedule-settings-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/session-history-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/feedback-block.php';

// Autoload blocks
require_once plugin_dir_path(__FILE__) . 'blocks/student-sessions-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/student-session-history-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/discord-connect-block.php';

// Activation / deactivation hooks
register_activation_hook(__FILE__, ['DND_Speaking_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['DND_Speaking_Deactivator', 'deactivate']);

// Initialize main plugin
add_action('plugins_loaded', function() {
    // Update database if needed
    if (class_exists('DND_Speaking_Activator')) {
        DND_Speaking_Activator::update_database_tables();
    }
    
    new DND_Speaking_REST_API();
    new DND_Speaking_Admin();
});

// Enqueue blocks script
// add_action('enqueue_block_editor_assets', function() {
//     wp_enqueue_script(
//         'dnd-speaking-blocks',
//         plugin_dir_url(__FILE__) . 'assets/js/blocks/blocks.js',
//         ['wp-blocks', 'wp-element', 'wp-editor', 'wp-api-fetch'],
//         '1.0.0',
//         true
//     );
// });

// Enqueue frontend styles
// add_action('wp_enqueue_scripts', function() {
//     wp_enqueue_style('dnd-speaking-blocks-style', plugin_dir_url(__FILE__) . 'assets/css/blocks.css');
//     wp_enqueue_script('dnd-speaking-frontend', plugin_dir_url(__FILE__) . 'assets/js/frontend.js', ['jquery'], '1.0.0', true);
//     wp_localize_script('dnd-speaking-frontend', 'dnd_ajax', [
//         'ajaxurl' => admin_url('admin-ajax.php'),
//         'nonce' => wp_create_nonce('dnd_nonce')
//     ]);
// });

// Register dynamic blocks
// add_action('init', function() {
//     register_block_type('dnd-speaking/student-credits', [
//         'render_callback' => 'dnd_render_student_credits_block',
//     ]);
//     register_block_type('dnd-speaking/teachers-list', [
//         'render_callback' => 'dnd_render_teachers_list_block',
//     ]);
// });

// function dnd_render_student_credits_block() {
//     if (!is_user_logged_in()) return '<p>Please log in to view your credits.</p>';

//     $user_id = get_current_user_id();
//     $credits = DND_Speaking_Helpers::get_user_credits($user_id);
//     return '<div class="dnd-student-credits">Remaining Credits: <strong>' . $credits . '</strong></div>';
// }

// function dnd_render_teachers_list_block() {
//     $users = get_users(['role' => 'teacher']);
//     $output = '<div class="dnd-teachers-list"><h3>Available Teachers</h3><ul>';
//     foreach ($users as $user) {
//         $available = get_user_meta($user->ID, 'dnd_available', true) == '1';
//         $output .= '<li>' . $user->display_name;
//         $output .= '<button class="book-now" data-teacher-id="' . $user->ID . '">Book Now</button>';
//         if ($available) {
//             $output .= '<button class="start-now" data-teacher-id="' . $user->ID . '">Start Now</button>';
//         }
//         $output .= '</li>';
//     }
//     $output .= '</ul></div>';
//     return $output;
// }
