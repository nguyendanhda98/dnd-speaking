<?php
/**
 * Plugin Name: DND Speaking Sessions
 * Description: Manage speaking sessions between students and teachers (integrated with Discord + BuddyBoss).
 * Version: 1.0.0
 * Author: DND English
 */

if (!defined('ABSPATH')) exit;

// Autoload includes
foreach (glob(plugin_dir_path(__FILE__) . 'includes/*.php') as $file) {
    require_once $file;
}

// Activation / deactivation hooks
register_activation_hook(__FILE__, ['DND_Speaking_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['DND_Speaking_Deactivator', 'deactivate']);

// Initialize main plugin
add_action('plugins_loaded', function() {
    new DND_Speaking_REST_API();
    new DND_Speaking_Teacher_Status();
    new DND_Speaking_Session_Manager();
    new DND_Speaking_Student_Dashboard();
    new DND_Speaking_Payment_Integration();
    new DND_Speaking_Admin();
});

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('dnd-speaking-style', plugin_dir_url(__FILE__) . 'public/css/style.css');
    wp_enqueue_script('dnd-speaking-script', plugin_dir_url(__FILE__) . 'public/js/main.js', ['jquery'], '1.0.0', true);
    wp_localize_script('dnd-speaking-script', 'dnd_ajax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('dnd_nonce')
    ]);
});

// Shortcodes
add_shortcode('dnd_speaking_dashboard', function() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/shortcode-speaking-dashboard.php';
    return ob_get_clean();
});
