<?php
namespace MailgunGH;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class Admin {
    /**
     * Instance of this class
     *
     * @var Admin
     */
    private static $instance;

    /**
     * Get the single instance of this class
     *
     * @return Admin
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'process_export'));
        
        // Add cleanup
        add_action('mailgun_gh_daily_cleanup', array($this, 'daily_cleanup'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('mailgun_gh_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'mailgun_gh_daily_cleanup');
        }
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Check if user has sufficient permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Create a dedicated top-level menu for Mailgun
        add_menu_page(
            __('Mailgun Events', 'mailgun-groundhogg'),
            __('Mailgun', 'mailgun-groundhogg'),
            'manage_options',
            'mailgun_gh_events',
            array($this, 'render_events_page'),
            'dashicons-email-alt'
        );
        
        // Add submenu pages
        add_submenu_page(
            'mailgun_gh_events',
            __('Mailgun Events', 'mailgun-groundhogg'),
            __('Events', 'mailgun-groundhogg'),
            'manage_options',
            'mailgun_gh_events',
            array($this, 'render_events_page')
        );
        
        add_submenu_page(
            'mailgun_gh_events',
            __('Mailgun Settings', 'mailgun-groundhogg'),
            __('Settings', 'mailgun-groundhogg'),
            'manage_options',
            'mailgun_gh_settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        // Check if we're on a Mailgun plugin page
        if (strpos($hook, 'mailgun_gh') === false) {
            return;
        }
        
        // Debug log which page we're on
        error_log('Loading Mailgun styles for hook: ' . $hook);
        
        // Create directories if they don't exist
        $css_dir = MAILGUN_GH_PLUGIN_PATH . 'assets/css';
        $js_dir = MAILGUN_GH_PLUGIN_PATH . 'assets/js';
        
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        
        if (!file_exists($js_dir)) {
            wp_mkdir_p($js_dir);
        }
        
        // Enqueue with non-cached version for development
        $version = WP_DEBUG ? time() : MAILGUN_GH_VERSION;
        
        wp_enqueue_style(
            'mailgun-gh-admin',
            MAILGUN_GH_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $version
        );
        
        wp_enqueue_script(
            'mailgun-gh-admin',
            MAILGUN_GH_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $version,
            true
        );
    }

    /**
     * Render events page
     */
    public function render_events_page() {
        // Check if viewing a single event
        if (isset($_GET['action']) && 'view' === $_GET['action'] && isset($_GET['id'])) {
            $this->render_event_details_page(intval($_GET['id']));
            return;
        }
        
        // Check for Groundhogg
        $groundhogg_active = $this->is_groundhogg_active();
        
        // List all events
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Mailgun Events', 'mailgun-groundhogg'); ?></h1>
            
            <?php if (!$groundhogg_active): ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Groundhogg detection issue: The plugin is having trouble detecting your Groundhogg installation.', 'mailgun-groundhogg'); ?>
                    <?php _e('This may affect contact status updates.', 'mailgun-groundhogg'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="mailgun-gh-filters">
                <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                    <input type="hidden" name="page" value="mailgun_gh_events">
                    
                    <div class="mailgun-gh-filter-row">
                        <select name="event_type">
                            <option value=""><?php _e('All Event Types', 'mailgun-groundhogg'); ?></option>
                            <option value="bounced" <?php selected(isset($_GET['event_type']) && $_GET['event_type'] === 'bounced'); ?>><?php _e('Bounced', 'mailgun-groundhogg'); ?></option>
                            <option value="complained" <?php selected(isset($_GET['event_type']) && $_GET['event_type'] === 'complained'); ?>><?php _e('Spam Complaint', 'mailgun-groundhogg'); ?></option>
                            <option value="unsubscribed" <?php selected(isset($_GET['event_type']) && $_GET['event_type'] === 'unsubscribed'); ?>><?php _e('Unsubscribed', 'mailgun-groundhogg'); ?></option>
                            <option value="delivered" <?php selected(isset($_GET['event_type']) && $_GET['event_type'] === 'delivered'); ?>><?php _e('Delivered', 'mailgun-groundhogg'); ?></option>
                            <option value="opened" <?php selected(isset($_GET['event_type']) && $_GET['event_type'] === 'opened'); ?>><?php _e('Opened', 'mailgun-groundhogg'); ?></option>
                            <option value="clicked" <?php selected(isset($_GET['event_type']) && $_GET['event_type'] === 'clicked'); ?>><?php _e('Clicked', 'mailgun-groundhogg'); ?></option>
                            <option value="failed" <?php selected(isset($_GET['event_type']) && $_GET['event_type'] === 'failed'); ?>><?php _e('Failed', 'mailgun-groundhogg'); ?></option>
                        </select>
                        
                        <input type="text" name="recipient" placeholder="<?php _e('Recipient Email', 'mailgun-groundhogg'); ?>" value="<?php echo isset($_GET['recipient']) ? esc_attr($_GET['recipient']) : ''; ?>">
                        
                        <input type="submit" class="button" value="<?php _e('Filter', 'mailgun-groundhogg'); ?>">
                        
                        <?php if (isset($_GET['event_type']) || isset($_GET['recipient'])): ?>
                            <a href="<?php echo admin_url('admin.php?page=mailgun_gh_events'); ?>" class="button"><?php _e('Reset', 'mailgun-groundhogg'); ?></a>
                        <?php endif; ?>
                        
                        <?php 
                        // Export button with current filters
                        $export_url = add_query_arg(
                            array(
                                'action' => 'export_mailgun_events',
                                'nonce' => wp_create_nonce('export_mailgun_events'),
                                'event_type' => isset($_GET['event_type']) ? $_GET['event_type'] : '',
                                'recipient' => isset($_GET['recipient']) ? $_GET['recipient'] : '',
                            ),
                            admin_url('admin.php')
                        );
                        ?>
                        
                        <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary"><?php _e('Export to CSV', 'mailgun-groundhogg'); ?></a>
                    </div>
                </form>
            </div>
            
            <?php
            // Get event stats
            $stats = Utils::get_event_stats('30days');
            if ($stats['total'] > 0):
            ?>
            <div class="mailgun-gh-stats">
                <h2><?php _e('Last 30 Days Stats', 'mailgun-groundhogg'); ?></h2>
                
                <div class="mailgun-gh-stats-grid">
                    <div class="mailgun-gh-stat-card bounce">
                        <div class="mailgun-gh-stat-count"><?php echo esc_html($stats['bounced']); ?></div>
                        <div class="mailgun-gh-stat-label"><?php _e('Bounces', 'mailgun-groundhogg'); ?></div>
                        <div class="mailgun-gh-stat-percent">
                            <?php 
                            if ($stats['total'] < 5) {
                                echo "&mdash;"; // Show dash for low sample sizes
                            } else {
                                echo esc_html($stats['bounced_percent']) . '%';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="mailgun-gh-stat-card complaint">
                        <div class="mailgun-gh-stat-count"><?php echo esc_html($stats['complained']); ?></div>
                        <div class="mailgun-gh-stat-label"><?php _e('Complaints', 'mailgun-groundhogg'); ?></div>
                        <div class="mailgun-gh-stat-percent">
                            <?php 
                            if ($stats['total'] < 5) {
                                echo "&mdash;"; // Show dash for low sample sizes
                            } else {
                                echo esc_html($stats['complained_percent']) . '%';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="mailgun-gh-stat-card unsubscribe">
                        <div class="mailgun-gh-stat-count"><?php echo esc_html($stats['unsubscribed']); ?></div>
                        <div class="mailgun-gh-stat-label"><?php _e('Unsubscribes', 'mailgun-groundhogg'); ?></div>
                        <div class="mailgun-gh-stat-percent">
                            <?php 
                            if ($stats['total'] < 5) {
                                echo "&mdash;"; // Show dash for low sample sizes
                            } else {
                                echo esc_html($stats['unsubscribed_percent']) . '%';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="mailgun-gh-stat-card total">
                        <div class="mailgun-gh-stat-count"><?php echo esc_html($stats['total']); ?></div>
                        <div class="mailgun-gh-stat-label"><?php _e('Total Events', 'mailgun-groundhogg'); ?></div>
                    </div>
                </div>
                
                <?php if ($stats['total'] < 5): ?>
                <p class="mailgun-gh-stats-note">
                    <em><?php _e('Note: Percentages are not shown for low event counts (less than 5).', 'mailgun-groundhogg'); ?></em>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <form method="post">
                <?php
                $events_table = new EventsTable();
                $events_table->prepare_items();
                $events_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render event details page
     *
     * @param int $id
     */
    public function render_event_details_page($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mailgun_gh_events';
        
        $event = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        
        if (!$event) {
            wp_die(__('Event not found', 'mailgun-groundhogg'));
        }
        
        $event_data = json_decode($event['event_data'], true);
        ?>
        <div class="wrap">
            <h1><?php _e('Event Details', 'mailgun-groundhogg'); ?></h1>
            
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mailgun_gh_events')); ?>" class="button">
                    <?php _e('← Back to Events List', 'mailgun-groundhogg'); ?>
                </a>
            </p>
            
            <div class="mailgun-gh-event-details">
                <h2><?php echo esc_html($this->get_event_type_label($event['event_type'])); ?></h2>
                
                <table class="widefat striped">
                    <tr>
                        <th><?php _e('Event Type', 'mailgun-groundhogg'); ?></th>
                        <td><?php echo esc_html($this->get_event_type_label($event['event_type'])); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Recipient', 'mailgun-groundhogg'); ?></th>
                        <td><?php echo esc_html($event['recipient']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Timestamp', 'mailgun-groundhogg'); ?></th>
                        <td><?php echo esc_html($event['timestamp']); ?></td>
                    </tr>
                    <?php if (!empty($event['contact_id'])): ?>
                        <tr>
                            <th><?php _e('Contact', 'mailgun-groundhogg'); ?></th>
                            <td>
                                <?php if (function_exists('gh_get_contact')): ?>
                                    <?php $contact = gh_get_contact($event['contact_id']); ?>
                                    <?php if ($contact): ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=gh_contacts&action=edit&contact=' . $event['contact_id'])); ?>">
                                            <?php echo esc_html($contact->get_email()); ?>
                                        </a>
                                        
                                        <?php 
                                        // Show contact status if available
                                        $optin_status = $contact->get_meta('optin_status');
                                        $status_labels = array(
                                            '1' => __('Unconfirmed', 'mailgun-groundhogg'),
                                            '2' => __('Confirmed', 'mailgun-groundhogg'),
                                            '3' => __('Unsubscribed', 'mailgun-groundhogg'),
                                            '4' => __('Subscribed Weekly', 'mailgun-groundhogg'),
                                            '5' => __('Subscribed Monthly', 'mailgun-groundhogg'),
                                            '6' => __('Bounced', 'mailgun-groundhogg'),
                                            '7' => __('Spam', 'mailgun-groundhogg'),
                                            '8' => __('Complained', 'mailgun-groundhogg')
                                        );
                                        
                                        $status_label = isset($status_labels[$optin_status]) ? $status_labels[$optin_status] : $optin_status;
                                        ?>
                                        <div class="contact-status">
                                            <?php _e('Status:', 'mailgun-groundhogg'); ?> 
                                            <strong><?php echo esc_html($status_label); ?></strong>
                                        </div>
                                    <?php else: ?>
                                        <?php _e('Contact not found', 'mailgun-groundhogg'); ?> (ID: <?php echo esc_html($event['contact_id']); ?>)
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php _e('Contact ID:', 'mailgun-groundhogg'); ?> <?php echo esc_html($event['contact_id']); ?>
                                    <p class="description"><?php _e('Groundhogg is not active. Install and activate Groundhogg to view contact details.', 'mailgun-groundhogg'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
                
                <h3><?php _e('Event Data', 'mailgun-groundhogg'); ?></h3>
                
                <table class="widefat striped">
                    <?php foreach ($event_data as $key => $value) : ?>
                        <tr>
                            <th><?php echo esc_html($key); ?></th>
                            <td>
                                <?php if (is_array($value)) : ?>
                                    <pre><?php echo esc_html(json_encode($value, JSON_PRETTY_PRINT)); ?></pre>
                                <?php else : ?>
                                    <?php echo esc_html($value); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get event type label
     *
     * @param string $event_type
     * @return string
     */
    private function get_event_type_label($event_type) {
        $event_types = array(
            'bounced' => __('Bounced', 'mailgun-groundhogg'),
            'complained' => __('Spam Complaint', 'mailgun-groundhogg'),
            'unsubscribed' => __('Unsubscribed', 'mailgun-groundhogg'),
            'delivered' => __('Delivered', 'mailgun-groundhogg'),
            'opened' => __('Opened', 'mailgun-groundhogg'),
            'clicked' => __('Clicked', 'mailgun-groundhogg'),
            'failed' => __('Failed', 'mailgun-groundhogg'),
        );
        
        if (isset($event_types[$event_type])) {
            return $event_types[$event_type];
        }
        
        return $event_type;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        Settings::get_instance()->render_settings_page();
    }
    
    /**
     * Process export request
     */
    public function process_export() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'export_mailgun_events') {
            return;
        }
        
        // Check nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'export_mailgun_events')) {
            wp_die(__('Security check failed', 'mailgun-groundhogg'));
        }
        
        // Check permissions
        if (!current_user_can('view_contacts')) {
            wp_die(__('You do not have permission to export events', 'mailgun-groundhogg'));
        }
        
        // Get filters
        $filters = array();
        
        if (!empty($_GET['event_type'])) {
            $filters['event_type'] = sanitize_text_field($_GET['event_type']);
        }
        
        if (!empty($_GET['recipient'])) {
            $filters['recipient'] = sanitize_email($_GET['recipient']);
        }
        
        if (!empty($_GET['start_date'])) {
            $filters['start_date'] = sanitize_text_field($_GET['start_date']);
        }
        
        if (!empty($_GET['end_date'])) {
            $filters['end_date'] = sanitize_text_field($_GET['end_date']);
        }
        
        // Generate CSV
        $csv_data = Utils::export_events_to_csv($filters);
        
        if (empty($csv_data)) {
            wp_die(__('No events found to export', 'mailgun-groundhogg'));
        }
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=mailgun-events-' . date('Y-m-d') . '.csv');
        
        // Output CSV data
        echo $csv_data;
        exit;
    }
    
    /**
     * Daily cleanup of old events
     */
    public function daily_cleanup() {
        // Get retention period in days (default 90)
        $retention_days = get_option('mailgun_gh_retention_days', 90);
        
        // Clean up events older than retention period
        Utils::cleanup_old_events($retention_days);
    }

    /**
     * Check if Groundhogg is active
     *
     * @return bool
     */
    private function is_groundhogg_active() {
        // Use our constant if defined
        if (defined('MAILGUN_GH_GROUNDHOGG_ACTIVE')) {
            return MAILGUN_GH_GROUNDHOGG_ACTIVE;
        }
        
        // Additional checks for Groundhogg
        if (function_exists('gh_get_contact_by')) {
            return true;
        }
        
        if (class_exists('\Groundhogg\Contact')) {
            return true;
        }
        
        if (defined('GROUNDHOGG_VERSION')) {
            return true;
        }
        
        // Last resort - check for Groundhogg plugin directory
        if (file_exists(WP_PLUGIN_DIR . '/groundhogg/groundhogg.php')) {
            // Check if it's active
            if (in_array('groundhogg/groundhogg.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                return true;
            }
        }
        
        return false;
    }
}