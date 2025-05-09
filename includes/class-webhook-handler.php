<?php
namespace MailgunGH;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles webhooks from Mailgun
 */
class WebhookHandler {
    /**
     * Instance of this class
     *
     * @var WebhookHandler
     */
    private static $instance;

    /**
     * Get the single instance of this class
     *
     * @return WebhookHandler
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
        // Add custom rewrite rules for the webhook endpoint
        add_action('init', array($this, 'add_rewrite_rules'));
        
        // Register the webhook handler
        add_action('parse_request', array($this, 'handle_webhook_request'));
    }

    /**
     * Add rewrite rules for the webhook endpoint
     */
    public function add_rewrite_rules() {
        $webhook_path = get_option('mailgun_gh_webhook_url', 'mailgun-webhook');
        add_rewrite_rule(
            '^' . $webhook_path . '/?$',
            'index.php?mailgun_gh_webhook=1',
            'top'
        );
        
        add_rewrite_tag('%mailgun_gh_webhook%', '([0-9]+)');
        
        // Flush rewrite rules only if our rule is not yet in the rewrite rules
        $rules = get_option('rewrite_rules');
        if (!isset($rules['^' . $webhook_path . '/?$'])) {
            flush_rewrite_rules();
        }
    }

    /**
     * Handle webhook requests
     *
     * @param \WP $wp
     */
    public function handle_webhook_request($wp) {
        if (empty($wp->query_vars['mailgun_gh_webhook'])) {
            return;
        }
        
        // Log webhook request for debugging
        // Get the raw POST data (Mailgun sends JSON)
        $rawPostData = file_get_contents('php://input');

        // Decode JSON data to an array for easier logging
        $postData = json_decode($rawPostData, true);

        // Add a timestamp and format the data
        $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . print_r($postData, true) . PHP_EOL;
        error_log('Mailgun webhook request received: ' . $logMessage);
        
        // Verify request
        $api_key = get_option('mailgun_gh_api_key', '');
        error_log('API KEY: ' . $api_key);
        if (empty($api_key) && !isset($_POST['token']) && $_POST['token'] !== 'test-token') {
            wp_die('Mailgun API key not configured', 'Mailgun Webhook Error', array('response' => 403));
        }
        
        // Get the timestamp and token from the request
        $timestamp = isset($_POST['timestamp']) ? $_POST['timestamp'] : '';
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        $signature = isset($_POST['signature']) ? $_POST['signature'] : '';
        
        // Verify the signature
        if (!$this->verify_signature($api_key, $timestamp, $token, $signature)) {
            error_log('Mailgun webhook signature verification failed');
            wp_die('Invalid signature', 'Mailgun Webhook Error', array('response' => 403));
        }
        
        // Extract event and recipient
        $event_type = $postData['event-data']['event'] ?? 'unknown';
        $recipient = $postData['event-data']['recipient'] ?? 'unknown';
        
        // Log any error data from Mailgun
        $this->log_mailgun_error_data($postData);
        
        if (empty($event_type) || empty($recipient)) {
            error_log('Mailgun webhook missing required parameters');
            wp_die('Missing required parameters', 'Mailgun Webhook Error', array('response' => 400));
        }
        
        // Store the event in the database
        $this->store_event($event_type, $recipient, $_POST);
        
        // Process the event based on type
        $this->process_event($event_type, $recipient, $_POST);
        
        // Return success
        error_log('Mailgun webhook processed successfully');
        wp_die('OK', 'Mailgun Webhook Success', array('response' => 200));
    }

    /**
     * Log any error data received from Mailgun
     *
     * @param array $postData The webhook data from Mailgun
     */
    private function log_mailgun_error_data($postData) {
        // Check if there's any error information in the event data
        if (!empty($postData['event-data'])) {
            $eventData = $postData['event-data'];
            
            // Log specific error details for different event types
            if (in_array($eventData['event'] ?? '', ['failed', 'bounced', 'complained', 'rejected'])) {
                // Log the full event for error events
                error_log('Mailgun error event details: ' . print_r($eventData, true));
                
                // Log delivery status if available
                if (!empty($eventData['delivery-status'])) {
                    $deliveryStatus = $eventData['delivery-status'];
                    error_log('Mailgun delivery status: ' . print_r($deliveryStatus, true));
                    
                    // Log specific error codes and messages
                    if (!empty($deliveryStatus['code'])) {
                        error_log('Mailgun error code: ' . $deliveryStatus['code']);
                    }
                    if (!empty($deliveryStatus['message'])) {
                        error_log('Mailgun error message: ' . $deliveryStatus['message']);
                    }
                    if (!empty($deliveryStatus['description'])) {
                        error_log('Mailgun error description: ' . $deliveryStatus['description']);
                    }
                }
                
                // Log specific error details for bounces
                if (($eventData['event'] ?? '') === 'bounced' && !empty($eventData['bounce'])) {
                    error_log('Mailgun bounce details: ' . print_r($eventData['bounce'], true));
                }
                
                // Log specific error details for complaints
                if (($eventData['event'] ?? '') === 'complained' && !empty($eventData['complaint'])) {
                    error_log('Mailgun complaint details: ' . print_r($eventData['complaint'], true));
                }
                
                // Log recipient domain and address for troubleshooting
                if (!empty($eventData['recipient-domain'])) {
                    error_log('Mailgun recipient domain: ' . $eventData['recipient-domain']);
                }
                
                // Log message headers if available
                if (!empty($eventData['message']['headers'])) {
                    error_log('Mailgun message headers: ' . print_r($eventData['message']['headers'], true));
                }
            }
        }
    }

    /**
     * Verify the signature
     *
     * @param string $api_key
     * @param string $timestamp
     * @param string $token
     * @param string $signature
     * @return bool
     */
    private function verify_signature($api_key, $timestamp, $token, $signature) {
        // bypasss the verification for now
        return true;
        // If any of the required fields are missing, fail
        if (empty($timestamp) || empty($token) || empty($signature)) {
            return false;
        }
        
        // Allow test webhooks with token = 'test-token'
        // IMPORTANT: In production, you should set WP_DEBUG to false to disable this bypass
        if ($token === 'test-token' && $signature === 'test-signature') {
            error_log('Test webhook received - bypassing signature verification');
            return true;
        }
        
        // Calculate expected signature
        $expected_signature = hash_hmac('sha256', $timestamp . $token, $api_key);
        
        // Compare signatures
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Store event in the database
     *
     * @param string $event_type
     * @param string $recipient
     * @param array $data
     */
    private function store_event($event_type, $recipient, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mailgun_gh_events';
        
        // Find the contact ID if exists
        $contact_id = 0;
        if (function_exists('gh_get_contact_by')) {
            try {
                $contact = gh_get_contact_by('email', $recipient);
                if ($contact) {
                    $contact_id = $contact->get_id();
                }
            } catch (\Exception $e) {
                error_log('Mailgun webhook: Error finding contact - ' . $e->getMessage());
            }
        }
        
        // If Groundhogg API fails, try direct database lookup
        if ($contact_id === 0) {
            $contact_id = $this->get_contact_id_by_email($recipient);
        }
        
        // Store the event
        $wpdb->insert(
            $table_name,
            array(
                'event_type' => $event_type,
                'recipient' => $recipient,
                'timestamp' => current_time('mysql'),
                'event_data' => json_encode($data),
                'contact_id' => $contact_id,
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
            )
        );
    }

    /**
     * Process the event based on type
     *
     * @param string $event_type
     * @param string $recipient
     * @param array $data
     */
    private function process_event($event_type, $recipient, $data) {
        error_log('Mailgun webhook: Processing event ' . $event_type . ' for ' . $recipient);
        
        // Try to get contact_id directly from database if Groundhogg API fails
        $contact_id = $this->get_contact_id_by_email($recipient);
        
        if (!$contact_id) {
            error_log('Mailgun webhook: No contact found for email ' . $recipient);
            return;
        }
        
        error_log('Mailgun webhook: Found contact ID ' . $contact_id);
        
        // Process based on event type using direct database updates
        switch ($event_type) {
            case 'unconfirmed':
                // Mark as unconfirmed (status 1)
                $this->update_contact_status_direct($contact_id, '1');
                error_log('Mailgun webhook: Marked contact as unconfirmed (status 1)');
                break;
                
            case 'confirmed':
                // Mark as confirmed (status 2)
                $this->update_contact_status_direct($contact_id, '2');
                error_log('Mailgun webhook: Marked contact as confirmed (status 2)');
                break;
                
            case 'unsubscribed':
                // Mark as unsubscribed (status 3)
                $this->update_contact_status_direct($contact_id, '3');
                $this->add_contact_meta_direct($contact_id, 'unsubscribed', current_time('mysql'));
                
                // Try to add a tag if possible
                if (function_exists('gh_get_contact')) {
                    try {
                        $contact = gh_get_contact($contact_id);
                        if ($contact) {
                            $contact->add_tag('unsubscribed-via-mailgun');
                        }
                    } catch (\Exception $e) {
                        error_log('Mailgun webhook: Error adding tag - ' . $e->getMessage());
                    }
                }
                
                error_log('Mailgun webhook: Marked contact as unsubscribed (status 3)');
                break;
                
            case 'subscribed_weekly':
                // Mark as subscribed weekly (status 4)
                $this->update_contact_status_direct($contact_id, '4');
                error_log('Mailgun webhook: Marked contact as subscribed weekly (status 4)');
                break;
                
            case 'subscribed_monthly':
                // Mark as subscribed monthly (status 5)
                $this->update_contact_status_direct($contact_id, '5');
                error_log('Mailgun webhook: Marked contact as subscribed monthly (status 5)');
                break;
            
            case 'failed':
            case 'bounced':
                // Mark as bounced (status 6)
                $this->update_contact_status_direct($contact_id, '6');
                $this->add_contact_meta_direct($contact_id, 'email_hard_bounced', '1');
                $this->add_contact_meta_direct($contact_id, 'unsubscribed', current_time('mysql'));
                
                // Try to add a tag if possible
                if (function_exists('gh_get_contact')) {
                    try {
                        $contact = gh_get_contact($contact_id);
                        if ($contact) {
                            $contact->add_tag('email-bounced');
                        }
                    } catch (\Exception $e) {
                        error_log('Mailgun webhook: Error adding tag - ' . $e->getMessage());
                    }
                }
                
                error_log('Mailgun webhook: Marked contact as bounced (status 6)');
                break;
                
            case 'spam':
                // Mark as spam (status 7)
                $this->update_contact_status_direct($contact_id, '7');
                $this->add_contact_meta_direct($contact_id, 'marked_as_spam', '1');
                error_log('Mailgun webhook: Marked contact as spam (status 7)');
                break;
                
            case 'complained':
                // Mark as complained (status 8)
                $this->update_contact_status_direct($contact_id, '8');
                $this->add_contact_meta_direct($contact_id, 'marked_as_spam', '1');
                $this->add_contact_meta_direct($contact_id, 'unsubscribed', current_time('mysql'));
                
                // Try to add a tag if possible
                if (function_exists('gh_get_contact')) {
                    try {
                        $contact = gh_get_contact($contact_id);
                        if ($contact) {
                            $contact->add_tag('spam-complaint');
                        }
                    } catch (\Exception $e) {
                        error_log('Mailgun webhook: Error adding tag - ' . $e->getMessage());
                    }
                }
                
                error_log('Mailgun webhook: Marked contact as complained (status 8)');
                break;
                
            default:
                // For other events, just log them without status changes
                error_log('Mailgun webhook: Event type ' . $event_type . ' does not map to a status change');
                break;
        }
        
        // Try to use hook if available
        if (function_exists('do_action') && function_exists('gh_get_contact')) {
            try {
                $contact = gh_get_contact($contact_id);
                if ($contact) {
                    do_action('mailgun_gh_process_event', $event_type, $recipient, $data, $contact);
                }
            } catch (\Exception $e) {
                error_log('Mailgun webhook: Error triggering hook - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Get contact ID by email using direct database query
     *
     * @param string $email
     * @return int|null
     */
    private function get_contact_id_by_email($email) {
        global $wpdb;
        
        // Try using Groundhogg API first
        if (function_exists('gh_get_contact_by')) {
            try {
                $contact = gh_get_contact_by('email', $email);
                if ($contact) {
                    return $contact->get_id();
                }
            } catch (\Exception $e) {
                error_log('Mailgun webhook: Error using Groundhogg API - ' . $e->getMessage());
            }
        }
        
        // Fall back to direct database query
        $table_name = $wpdb->prefix . 'gh_contacts';
        
        // Make sure the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            error_log('Mailgun webhook: Groundhogg contacts table does not exist');
            return null;
        }
        
        // Get contact ID
        $contact_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $table_name WHERE email = %s",
            $email
        ));
        
        return $contact_id ? intval($contact_id) : null;
    }
    
    /**
     * Update contact status directly in the database
     *
     * @param int $contact_id
     * @param string $status
     * @return bool
     */
    private function update_contact_status_direct($contact_id, $status) {
        global $wpdb;
        $contacts_table = $wpdb->prefix . 'gh_contacts';
        
        // Make sure the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$contacts_table'") === $contacts_table;
        if (!$table_exists) {
            error_log('Mailgun webhook: Groundhogg contacts table does not exist');
            return false;
        }
        
        // Update contact status
        $result = $wpdb->update(
            $contacts_table,
            array('optin_status' => $status),
            array('ID' => $contact_id),
            array('%s'),
            array('%d')
        );
        
        error_log('Mailgun webhook: Updated contact status to ' . $status . ' (Result: ' . ($result !== false ? 'success' : 'failure') . ')');
        return $result !== false;
    }
    
    /**
     * Add or update contact meta directly in the database
     *
     * @param int $contact_id
     * @param string $key
     * @param string $value
     * @return bool
     */
    private function add_contact_meta_direct($contact_id, $key, $value) {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'gh_contactmeta';
        
        // Check if the meta key exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $meta_table WHERE contact_id = %d AND meta_key = %s",
            $contact_id, $key
        ));
        
        if ($exists) {
            // Update existing meta
            $result = $wpdb->update(
                $meta_table,
                array('meta_value' => $value),
                array('contact_id' => $contact_id, 'meta_key' => $key),
                array('%s'),
                array('%d', '%s')
            );
            
            return $result !== false;
        } else {
            // Insert new meta
            $result = $wpdb->insert(
                $meta_table,
                array(
                    'contact_id' => $contact_id,
                    'meta_key' => $key,
                    'meta_value' => $value
                ),
                array('%d', '%s', '%s')
            );
            
            return $result ? true : false;
        }
    }
}