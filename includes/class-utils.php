<?php
namespace MailgunGH;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utility functions
 */
class Utils {
    /**
     * Format date in site's timezone
     *
     * @param string $date
     * @param string $format
     * @return string
     */
    public static function format_date($date, $format = '') {
        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }
        
        $timezone = new \DateTimeZone(wp_timezone_string());
        $datetime = new \DateTime($date, $timezone);
        
        return $datetime->format($format);
    }
    
    /**
     * Format event type with proper label
     *
     * @param string $event_type
     * @return string
     */
    public static function format_event_type($event_type) {
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
        
        return ucfirst($event_type);
    }
    
    /**
     * Get event type CSS class
     *
     * @param string $event_type
     * @return string
     */
    public static function get_event_type_class($event_type) {
        $classes = array(
            'bounced' => 'error',
            'complained' => 'warning',
            'unsubscribed' => 'info',
            'delivered' => 'success',
            'opened' => 'success',
            'clicked' => 'success',
            'failed' => 'warning',
        );
        
        if (isset($classes[$event_type])) {
            return $classes[$event_type];
        }
        
        return 'default';
    }
    
    /**
     * Clean up old events
     *
     * @param int $days
     * @return int
     */
    public static function cleanup_old_events($days = 90) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mailgun_gh_events';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE timestamp < %s",
                $cutoff_date
            )
        );
        
        return $result;
    }
    
    /**
     * Export events to CSV
     *
     * @param array $filters
     * @return string
     */
    public static function export_events_to_csv($filters = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mailgun_gh_events';
        
        // Build query
        $query = "SELECT * FROM {$table_name} WHERE 1=1";
        $query_args = array();
        
        // Apply filters
        if (!empty($filters['event_type'])) {
            $query .= " AND event_type = %s";
            $query_args[] = $filters['event_type'];
        }
        
        if (!empty($filters['recipient'])) {
            $query .= " AND recipient LIKE %s";
            $query_args[] = '%' . $wpdb->esc_like($filters['recipient']) . '%';
        }
        
        if (!empty($filters['start_date'])) {
            $query .= " AND timestamp >= %s";
            $query_args[] = $filters['start_date'] . ' 00:00:00';
        }
        
        if (!empty($filters['end_date'])) {
            $query .= " AND timestamp <= %s";
            $query_args[] = $filters['end_date'] . ' 23:59:59';
        }
        
        // Order by timestamp
        $query .= " ORDER BY timestamp DESC";
        
        // Prepare and execute query
        if (!empty($query_args)) {
            $query = $wpdb->prepare($query, $query_args);
        }
        
        $events = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($events)) {
            return '';
        }
        
        // Start output buffering
        ob_start();
        
        // Create a file pointer
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 compatibility
        fputs($output, "\xEF\xBB\xBF");
        
        // Set column headers
        fputcsv($output, array(
            __('ID', 'mailgun-groundhogg'),
            __('Event Type', 'mailgun-groundhogg'),
            __('Recipient', 'mailgun-groundhogg'),
            __('Timestamp', 'mailgun-groundhogg'),
            __('Contact ID', 'mailgun-groundhogg'),
            __('Event Data', 'mailgun-groundhogg'),
        ));
        
        // Add rows
        foreach ($events as $event) {
            fputcsv($output, array(
                $event['id'],
                self::format_event_type($event['event_type']),
                $event['recipient'],
                $event['timestamp'],
                $event['contact_id'],
                $event['event_data'],
            ));
        }
        
        // Close the file pointer
        fclose($output);
        
        // Get the contents of the output buffer
        $csv_data = ob_get_clean();
        
        return $csv_data;
    }
    
    /**
     * Get event stats for a given period
     *
     * @param string $period
     * @return array
     */
    public static function get_event_stats($period = '30days') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mailgun_gh_events';
        
        // Determine date range
        $end_date = current_time('mysql');
        
        switch ($period) {
            case '7days':
                $start_date = date('Y-m-d H:i:s', strtotime('-7 days', strtotime($end_date)));
                break;
            case '30days':
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days', strtotime($end_date)));
                break;
            case '90days':
                $start_date = date('Y-m-d H:i:s', strtotime('-90 days', strtotime($end_date)));
                break;
            case 'year':
                $start_date = date('Y-m-d H:i:s', strtotime('-1 year', strtotime($end_date)));
                break;
            default:
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days', strtotime($end_date)));
        }
        
        // Get counts for each event type
        $stats = array();
        
        $event_types = array(
            'bounced',
            'complained',
            'unsubscribed',
            'delivered',
            'opened',
            'clicked',
            'failed',
        );
        
        foreach ($event_types as $event_type) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE event_type = %s AND timestamp BETWEEN %s AND %s",
                    $event_type,
                    $start_date,
                    $end_date
                )
            );
            
            $stats[$event_type] = (int) $count;
        }
        
        // Get total count
        $stats['total'] = array_sum($stats);
        
        // Calculate percentages if we have any events
        if ($stats['total'] > 0) {
            foreach ($event_types as $event_type) {
                $stats[$event_type . '_percent'] = round(($stats[$event_type] / $stats['total']) * 100, 2);
            }
        }
        
        return $stats;
    }
}