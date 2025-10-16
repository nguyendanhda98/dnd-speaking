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
require_once plugin_dir_path(__FILE__) . 'blocks/teacher-requests-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/upcoming-sessions-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/schedule-settings-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/session-history-block.php';
require_once plugin_dir_path(__FILE__) . 'blocks/feedback-block.php';

// Activation / deactivation hooks
register_activation_hook(__FILE__, ['DND_Speaking_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['DND_Speaking_Deactivator', 'deactivate']);

// Initialize main plugin
add_action('plugins_loaded', function() {
    new DND_Speaking_Admin();
    new DND_Speaking_REST_API();
});
