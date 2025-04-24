<?php
namespace MailgunGH;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class MailgunGH {
    /**
     * Instance of this class
     *
     * @var MailgunGH
     */
    private static $instance;

    /**
     * Get the single instance of this class
     *
     * @return MailgunGH
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        // Load core classes
        require_once MAILGUN_GH_PLUGIN_PATH . 'includes/class-utils.php';
        require_once MAILGUN_GH_PLUGIN_PATH . 'includes/class-webhook-handler.php';
        require_once MAILGUN_GH_PLUGIN_PATH . 'includes/class-events-table.php';
        require_once MAILGUN_GH_PLUGIN_PATH . 'includes/class-settings.php';
        require_once MAILGUN_GH_PLUGIN_PATH . 'includes/class-admin.php';
        require_once MAILGUN_GH_PLUGIN_PATH . 'includes/class-dashboard.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize webhook handler
        WebhookHandler::get_instance();
        
        // Initialize admin
        Admin::get_instance();
        
        // Initialize dashboard widget
        Dashboard::get_instance();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create the database table for storing events
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mailgun_gh_events';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            recipient varchar(255) NOT NULL,
            timestamp datetime NOT NULL,
            event_data longtext NOT NULL,
            contact_id bigint(20),
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Set default options
        if (!get_option('mailgun_gh_api_key')) {
            update_option('mailgun_gh_api_key', '');
        }
        
        if (!get_option('mailgun_gh_webhook_url')) {
            // Generate a unique webhook URL path
            $webhook_path = 'mailgun-webhook-' . wp_generate_password(12, false);
            update_option('mailgun_gh_webhook_url', $webhook_path);
        }
    }

    /**
     * Get the webhook URL
     *
     * @return string
     */
    public static function get_webhook_url() {
        $webhook_path = get_option('mailgun_gh_webhook_url', 'mailgun-webhook');
        return site_url('/' . $webhook_path);
    }
}