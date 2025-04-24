<?php
namespace MailgunGH;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings page
 */
class Settings {
    /**
     * Instance of this class
     *
     * @var Settings
     */
    private static $instance;

    /**
     * Get the single instance of this class
     *
     * @return Settings
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
        add_action('admin_init', array($this, 'register_settings'));
        
        // Debug log to check if constructor is being called
        if (WP_DEBUG) {
            error_log('[Mailgun GH] Settings class initialized');
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // API Key
        register_setting(
            'mailgun_gh_settings',
            'mailgun_gh_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
        
        // Retention period
        register_setting(
            'mailgun_gh_settings',
            'mailgun_gh_retention_days',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 90,
            )
        );
        
        // Keep data on uninstall
        register_setting(
            'mailgun_gh_settings',
            'mailgun_gh_keep_data_on_uninstall',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );
        
        // Main settings section
        add_settings_section(
            'mailgun_gh_settings_section',
            __('Mailgun Settings', 'mailgun-groundhogg'),
            array($this, 'settings_section_callback'),
            'mailgun_gh_settings'
        );
        
        add_settings_field(
            'mailgun_gh_api_key',
            __('API Key', 'mailgun-groundhogg'),
            array($this, 'api_key_field_callback'),
            'mailgun_gh_settings',
            'mailgun_gh_settings_section'
        );
        
        add_settings_field(
            'mailgun_gh_webhook_url',
            __('Webhook URL', 'mailgun-groundhogg'),
            array($this, 'webhook_url_field_callback'),
            'mailgun_gh_settings',
            'mailgun_gh_settings_section'
        );
        
        // Data management section
        add_settings_section(
            'mailgun_gh_data_section',
            __('Data Management', 'mailgun-groundhogg'),
            array($this, 'data_section_callback'),
            'mailgun_gh_settings'
        );
        
        add_settings_field(
            'mailgun_gh_retention_days',
            __('Data Retention Period', 'mailgun-groundhogg'),
            array($this, 'retention_days_field_callback'),
            'mailgun_gh_settings',
            'mailgun_gh_data_section'
        );
        
        add_settings_field(
            'mailgun_gh_keep_data_on_uninstall',
            __('Keep Data on Uninstall', 'mailgun-groundhogg'),
            array($this, 'keep_data_field_callback'),
            'mailgun_gh_settings',
            'mailgun_gh_data_section'
        );
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure your Mailgun webhook settings here. You\'ll need to add the webhook URL to your Mailgun account.', 'mailgun-groundhogg') . '</p>';
    }

    /**
     * API key field callback
     */
    public function api_key_field_callback() {
        $api_key = get_option('mailgun_gh_api_key', '');
        ?>
        <input type="text" id="mailgun_gh_api_key" name="mailgun_gh_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
        <p class="description"><?php _e('Enter your Mailgun API key here. This is used to verify webhook requests.', 'mailgun-groundhogg'); ?></p>
        <?php
    }

    /**
     * Webhook URL field callback
     */
    public function webhook_url_field_callback() {
        $webhook_url = MailgunGH::get_webhook_url();
        ?>
        <input type="text" id="mailgun_gh_webhook_url_display" value="<?php echo esc_attr($webhook_url); ?>" class="regular-text" readonly>
        <button type="button" class="button" onclick="copyWebhookUrl()"><?php _e('Copy', 'mailgun-groundhogg'); ?></button>
        <p class="description"><?php _e('Add this URL to your Mailgun webhooks configuration.', 'mailgun-groundhogg'); ?></p>
        <script>
            function copyWebhookUrl() {
                var copyText = document.getElementById("mailgun_gh_webhook_url_display");
                copyText.select();
                document.execCommand("copy");
                alert("<?php _e('Webhook URL copied to clipboard', 'mailgun-groundhogg'); ?>");
            }
        </script>
        <?php
    }
    
    /**
     * Data section callback
     */
    public function data_section_callback() {
        echo '<p>' . __('Configure how email event data is managed and retained.', 'mailgun-groundhogg') . '</p>';
    }
    
    /**
     * Retention days field callback
     */
    public function retention_days_field_callback() {
        $retention_days = get_option('mailgun_gh_retention_days', 90);
        ?>
        <input type="number" id="mailgun_gh_retention_days" name="mailgun_gh_retention_days" value="<?php echo esc_attr($retention_days); ?>" min="1" max="365" class="small-text">
        <p class="description"><?php _e('Number of days to keep email events. Events older than this will be automatically deleted. (Default: 90 days)', 'mailgun-groundhogg'); ?></p>
        <?php
    }
    
    /**
     * Keep data field callback
     */
    public function keep_data_field_callback() {
        $keep_data = get_option('mailgun_gh_keep_data_on_uninstall', false);
        ?>
        <label>
            <input type="checkbox" id="mailgun_gh_keep_data_on_uninstall" name="mailgun_gh_keep_data_on_uninstall" value="1" <?php checked($keep_data); ?>>
            <?php _e('Keep plugin data when uninstalling', 'mailgun-groundhogg'); ?>
        </label>
        <p class="description"><?php _e('If checked, plugin data (events and settings) will not be deleted when the plugin is uninstalled.', 'mailgun-groundhogg'); ?></p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Save settings if form is submitted
        if (isset($_POST['mailgun_gh_save_settings']) && check_admin_referer('mailgun_gh_settings_nonce')) {
            // Save API key
            if (isset($_POST['mailgun_gh_api_key'])) {
                update_option('mailgun_gh_api_key', sanitize_text_field($_POST['mailgun_gh_api_key']));
            }
            
            // Save retention days
            if (isset($_POST['mailgun_gh_retention_days'])) {
                update_option('mailgun_gh_retention_days', intval($_POST['mailgun_gh_retention_days']));
            }
            
            // Save keep data setting
            $keep_data = isset($_POST['mailgun_gh_keep_data_on_uninstall']) ? 1 : 0;
            update_option('mailgun_gh_keep_data_on_uninstall', $keep_data);
            
            // Show success message
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'mailgun-groundhogg') . '</p></div>';
        }
        
        // Get current values
        $api_key = get_option('mailgun_gh_api_key', '');
        $retention_days = get_option('mailgun_gh_retention_days', 90);
        $keep_data = get_option('mailgun_gh_keep_data_on_uninstall', false);
        $webhook_url = \MailgunGH\MailgunGH::get_webhook_url();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('mailgun_gh_settings_nonce'); ?>
                <input type="hidden" name="mailgun_gh_save_settings" value="1">
                
                <h2><?php _e('Mailgun Settings', 'mailgun-groundhogg'); ?></h2>
                <p><?php _e('Configure your Mailgun webhook settings here. You\'ll need to add the webhook URL to your Mailgun account.', 'mailgun-groundhogg'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mailgun_gh_api_key"><?php _e('API Key', 'mailgun-groundhogg'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="mailgun_gh_api_key" name="mailgun_gh_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                            <p class="description"><?php _e('Enter your Mailgun API key here. This is used to verify webhook requests.', 'mailgun-groundhogg'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="mailgun_gh_webhook_url"><?php _e('Webhook URL', 'mailgun-groundhogg'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="mailgun_gh_webhook_url" value="<?php echo esc_attr($webhook_url); ?>" class="regular-text" readonly>
                            <button type="button" class="button" onclick="copyWebhookUrl()"><?php _e('Copy', 'mailgun-groundhogg'); ?></button>
                            <p class="description"><?php _e('Add this URL to your Mailgun webhooks configuration.', 'mailgun-groundhogg'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Data Management', 'mailgun-groundhogg'); ?></h2>
                <p><?php _e('Configure how email event data is managed and retained.', 'mailgun-groundhogg'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="mailgun_gh_retention_days"><?php _e('Data Retention Period', 'mailgun-groundhogg'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="mailgun_gh_retention_days" name="mailgun_gh_retention_days" value="<?php echo esc_attr($retention_days); ?>" min="1" max="365" class="small-text">
                            <p class="description"><?php _e('Number of days to keep email events. Events older than this will be automatically deleted. (Default: 90 days)', 'mailgun-groundhogg'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php _e('Keep Data on Uninstall', 'mailgun-groundhogg'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="mailgun_gh_keep_data_on_uninstall" name="mailgun_gh_keep_data_on_uninstall" value="1" <?php checked($keep_data); ?>>
                                <?php _e('Keep plugin data when uninstalling', 'mailgun-groundhogg'); ?>
                            </label>
                            <p class="description"><?php _e('If checked, plugin data (events and settings) will not be deleted when the plugin is uninstalled.', 'mailgun-groundhogg'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <script>
                function copyWebhookUrl() {
                    var copyText = document.getElementById("mailgun_gh_webhook_url");
                    copyText.select();
                    document.execCommand("copy");
                    alert("<?php _e('Webhook URL copied to clipboard', 'mailgun-groundhogg'); ?>");
                }
            </script>
            
            <div class="mailgun-gh-settings-help">
                <h2><?php _e('How to Configure Mailgun Webhooks', 'mailgun-groundhogg'); ?></h2>
                <ol>
                    <li><?php _e('Copy the webhook URL above.', 'mailgun-groundhogg'); ?></li>
                    <li><?php _e('Log in to your Mailgun account.', 'mailgun-groundhogg'); ?></li>
                    <li><?php _e('Navigate to Sending > Webhooks.', 'mailgun-groundhogg'); ?></li>
                    <li><?php _e('Add a new webhook for each event type:', 'mailgun-groundhogg'); ?>
                        <ul>
                            <li><?php _e('Bounced', 'mailgun-groundhogg'); ?></li>
                            <li><?php _e('Complained (Spam)', 'mailgun-groundhogg'); ?></li>
                            <li><?php _e('Unsubscribed', 'mailgun-groundhogg'); ?></li>
                            <li><?php _e('And any other events you want to track', 'mailgun-groundhogg'); ?></li>
                        </ul>
                    </li>
                    <li><?php _e('Paste the webhook URL for each event.', 'mailgun-groundhogg'); ?></li>
                    <li><?php _e('Save your changes.', 'mailgun-groundhogg'); ?></li>
                </ol>
                <p><?php _e('Mailgun will now send event data to your WordPress site, and the plugin will process and store it.', 'mailgun-groundhogg'); ?></p>
            </div>
        </div>
        <?php
    }
}