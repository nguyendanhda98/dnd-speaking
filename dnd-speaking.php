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

// Activation / deactivation hooks
register_activation_hook(__FILE__, ['DND_Speaking_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['DND_Speaking_Deactivator', 'deactivate']);

// Initialize main plugin
add_action('plugins_loaded', function() {
    new DND_Speaking_Admin();
});
