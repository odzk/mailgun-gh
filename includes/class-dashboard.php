<?php
namespace MailgunGH;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard widget
 */
class Dashboard {
    /**
     * Instance of this class
     *
     * @var Dashboard
     */
    private static $instance;

    /**
     * Get the single instance of this class
     *
     * @return Dashboard
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
        // Add dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        
        // Add to Groundhogg dashboard
        add_action('groundhogg_after_dashboard_widgets', array($this, 'add_groundhogg_dashboard_widget'));
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        // Only add for users with permissions
        if (!current_user_can('view_contacts')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'mailgun_gh_dashboard_widget',
            __('Mailgun Email Events', 'mailgun-groundhogg'),
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * Add Groundhogg dashboard widget
     */
    public function add_groundhogg_dashboard_widget() {
        // Only add for users with permissions
        if (!current_user_can('view_contacts')) {
            return;
        }
        
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2><?php _e('Mailgun Email Events', 'mailgun-groundhogg'); ?></h2>
            </div>
            <div class="inside">
                <?php $this->render_dashboard_widget(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mailgun_gh_events';
        
        // Get event counts
        $total_events = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $bounce_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE event_type = 'bounced'");
        $complaint_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE event_type = 'complained'");
        $unsubscribe_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE event_type = 'unsubscribed'");
        
        // Get recent events
        $recent_events = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 5",
            ARRAY_A
        );
        
        ?>
        <div class="mailgun-gh-dashboard-widget">
            <div class="mailgun-gh-dashboard-widget-summary">
                <div class="mailgun-gh-dashboard-stat bounce">
                    <div class="mailgun-gh-dashboard-stat-count"><?php echo esc_html($bounce_count); ?></div>
                    <div class="mailgun-gh-dashboard-stat-label"><?php _e('Bounces', 'mailgun-groundhogg'); ?></div>
                </div>
                
                <div class="mailgun-gh-dashboard-stat complaint">
                    <div class="mailgun-gh-dashboard-stat-count"><?php echo esc_html($complaint_count); ?></div>
                    <div class="mailgun-gh-dashboard-stat-label"><?php _e('Complaints', 'mailgun-groundhogg'); ?></div>
                </div>
                
                <div class="mailgun-gh-dashboard-stat unsubscribe">
                    <div class="mailgun-gh-dashboard-stat-count"><?php echo esc_html($unsubscribe_count); ?></div>
                    <div class="mailgun-gh-dashboard-stat-label"><?php _e('Unsubscribes', 'mailgun-groundhogg'); ?></div>
                </div>
                
                <div class="mailgun-gh-dashboard-stat">
                    <div class="mailgun-gh-dashboard-stat-count"><?php echo esc_html($total_events); ?></div>
                    <div class="mailgun-gh-dashboard-stat-label"><?php _e('Total Events', 'mailgun-groundhogg'); ?></div>
                </div>
            </div>
            
            <?php if ($recent_events) : ?>
                <div class="mailgun-gh-dashboard-recent-events">
                    <h3><?php _e('Recent Events', 'mailgun-groundhogg'); ?></h3>
                    <table>
                        <thead>
                            <tr>
                                <th><?php _e('Event', 'mailgun-groundhogg'); ?></th>
                                <th><?php _e('Recipient', 'mailgun-groundhogg'); ?></th>
                                <th><?php _e('Date', 'mailgun-groundhogg'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_events as $event) : ?>
                                <tr>
                                    <td><?php echo esc_html($this->get_event_type_label($event['event_type'])); ?></td>
                                    <td><?php echo esc_html($event['recipient']); ?></td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($event['timestamp']), current_time('timestamp')) . ' ' . __('ago', 'mailgun-groundhogg')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p><?php _e('No events recorded yet.', 'mailgun-groundhogg'); ?></p>
            <?php endif; ?>
            
            <div class="mailgun-gh-dashboard-view-all">
                <a href="<?php echo esc_url(admin_url('admin.php?page=mailgun_gh_events')); ?>" class="button button-secondary">
                    <?php _e('View All Events', 'mailgun-groundhogg'); ?>
                </a>
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
}