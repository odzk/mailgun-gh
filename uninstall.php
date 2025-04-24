<?php
/**
 * Uninstall Mailgun for Groundhogg
 *
 * @package MailgunGH
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Option to keep data on uninstall (can be set in settings)
$keep_data = get_option('mailgun_gh_keep_data_on_uninstall', false);

if (!$keep_data) {
    // Delete options
    delete_option('mailgun_gh_api_key');
    delete_option('mailgun_gh_webhook_url');
    delete_option('mailgun_gh_keep_data_on_uninstall');
    
    // Drop custom tables
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mailgun_gh_events");
    
    // Clear any scheduled hooks
    wp_clear_scheduled_hook('mailgun_gh_daily_cleanup');
}