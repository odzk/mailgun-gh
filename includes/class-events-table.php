<?php
namespace MailgunGH;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include WP_List_Table if not already included
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Events list table
 */
class EventsTable extends \WP_List_Table {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'event',
            'plural' => 'events',
            'ajax' => false,
        ));
    }

    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'event_type' => __('Event Type', 'mailgun-groundhogg'),
            'recipient' => __('Recipient', 'mailgun-groundhogg'),
            'timestamp' => __('Timestamp', 'mailgun-groundhogg'),
            'contact' => __('Contact', 'mailgun-groundhogg'),
            'details' => __('Details', 'mailgun-groundhogg'),
        );
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'event_type' => array('event_type', false),
            'recipient' => array('recipient', false),
            'timestamp' => array('timestamp', true), // Default sort
        );
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Delete', 'mailgun-groundhogg'),
        );
    }

    /**
     * Column default
     *
     * @param array $item
     * @param string $column_name
     * @return string
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'event_type':
                return $this->format_event_type($item['event_type']);
                
            case 'recipient':
                return esc_html($item['recipient']);
                
            case 'timestamp':
                return esc_html($item['timestamp']);
                
            case 'contact':
                return $this->format_contact($item['contact_id']);
                
            case 'details':
                return $this->get_details_button($item['id']);
                
            default:
                return print_r($item, true); // Show the whole array for troubleshooting
        }
    }

    /**
     * Format event type
     *
     * @param string $event_type
     * @return string
     */
    private function format_event_type($event_type) {
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
        
        return esc_html($event_type);
    }

    /**
     * Format contact
     *
     * @param int $contact_id
     * @return string
     */
    private function format_contact($contact_id) {
        if (empty($contact_id)) {
            return __('Not found', 'mailgun-groundhogg');
        }
        
        // Check if Groundhogg is active
        if (!function_exists('gh_get_contact')) {
            return '<span title="' . esc_attr(__('Groundhogg not active', 'mailgun-groundhogg')) . '">' . esc_html($contact_id) . '</span>';
        }
        
        $contact = gh_get_contact($contact_id);
        if (!$contact) {
            return __('Not found', 'mailgun-groundhogg');
        }
        
        $url = admin_url('admin.php?page=gh_contacts&action=edit&contact=' . $contact_id);
        return '<a href="' . esc_url($url) . '">' . esc_html($contact->get_email()) . '</a>';
    }

    /**
     * Get details button
     *
     * @param int $id
     * @return string
     */
    private function get_details_button($id) {
        $url = admin_url('admin.php?page=mailgun_gh_events&action=view&id=' . $id);
        return '<a href="' . esc_url($url) . '" class="button button-small">' . __('View Details', 'mailgun-groundhogg') . '</a>';
    }

    /**
     * Column cb
     *
     * @param array $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
        );
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mailgun_gh_events';
        
        // Per page option
        $per_page = 20;
        
        // Set column headers
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Process bulk actions
        $this->process_bulk_action();
        
        // Prepare query params
        $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'timestamp';
        $order = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        // Get current page
        $current_page = $this->get_pagenum();
        
        // Get total items
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Get items
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
                $per_page,
                ($current_page - 1) * $per_page
            ),
            ARRAY_A
        );
        
        // Set pagination args
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ));
    }

    /**
     * Process bulk action
     */
    public function process_bulk_action() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mailgun_gh_events';
        
        // Detect when a bulk action is being triggered
        if ('delete' === $this->current_action()) {
            // Security check
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
                wp_die('Security check failed');
            }
            
            $delete_ids = isset($_POST['bulk-delete']) ? $_POST['bulk-delete'] : array();
            
            // Loop over the array of record IDs and delete them
            foreach ($delete_ids as $id) {
                $wpdb->delete(
                    $table_name,
                    array('id' => $id),
                    array('%d')
                );
            }
            
            wp_redirect(add_query_arg());
            exit;
        }
    }
}