<?php
/**
 * Plugin Name: Mailgun for Groundhogg
 * Plugin URI: https://example.com/mailgun-groundhogg
 * Description: Integration between Mailgun and Groundhogg for tracking email events
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: mailgun-groundhogg
 * Domain Path: /languages
 * Requires Plugins: groundhogg
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAILGUN_GH_VERSION', '1.0.0');
define('MAILGUN_GH_PLUGIN_FILE', __FILE__);
define('MAILGUN_GH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MAILGUN_GH_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include files
require_once MAILGUN_GH_PLUGIN_PATH . 'includes/class-mailgun-gh.php';

// Initialize plugin
function mailgun_gh_init() {
    // Initialize the plugin
    \MailgunGH\MailgunGH::get_instance();
    
    // More reliable check for Groundhogg
    $groundhogg_active = false;
    
    // Check if the main Groundhogg function exists
    if (function_exists('gh_get_contact_by')) {
        $groundhogg_active = true;
    }
    
    // Check if Groundhogg class exists
    if (class_exists('\Groundhogg\Contact')) {
        $groundhogg_active = true;
    }
    
    // Check if the constant is defined
    if (defined('GROUNDHOGG_VERSION')) {
        $groundhogg_active = true;
    }
    
    // Show admin notice if Groundhogg appears to not be active
    if (!$groundhogg_active) {
        add_action('admin_notices', 'mailgun_gh_missing_groundhogg_notice');
    }
    
    // Set a global to track Groundhogg status
    if (!defined('MAILGUN_GH_GROUNDHOGG_ACTIVE')) {
        define('MAILGUN_GH_GROUNDHOGG_ACTIVE', $groundhogg_active);
    }
}
add_action('plugins_loaded', 'mailgun_gh_init', 20); // Higher priority to ensure Groundhogg loads first

// Admin notice for missing Groundhogg
function mailgun_gh_missing_groundhogg_notice() {
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><?php _e('Mailgun for Groundhogg is active but Groundhogg plugin is not installed or activated. Webhook events will be recorded but contacts will not be updated until Groundhogg is active.', 'mailgun-groundhogg'); ?></p>
        <p><?php _e('Install and activate Groundhogg for full functionality.', 'mailgun-groundhogg'); ?></p>
    </div>
    <?php
}

// Activation hook
function mailgun_gh_activate() {
    // Create necessary tables and options
    require_once MAILGUN_GH_PLUGIN_PATH . 'includes/class-mailgun-gh.php';
    \MailgunGH\MailgunGH::get_instance()->activate();
    
    // Clear permalinks to ensure webhook URL works
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'mailgun_gh_activate');

// Deactivation hook
function mailgun_gh_deactivate() {
    // Clean up if needed
}
register_deactivation_hook(__FILE__, 'mailgun_gh_deactivate');