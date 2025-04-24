# Mailgun for Groundhogg

A WordPress plugin that integrates Mailgun webhooks with Groundhogg CRM to track email events like bounces, spam complaints, and unsubscribes.

## Features

- Accepts and processes webhooks from Mailgun
- Stores email events in a custom database table
- Displays events in an admin dashboard
- Provides a settings page to configure the integration
- Automatically tags contacts in Groundhogg based on email events
- Shows a summary dashboard widget

## Installation

1. Download the plugin zip file
2. Go to WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload the zip file and activate the plugin
4. Make sure Groundhogg is active

## Configuration

1. Navigate to Groundhogg > Mailgun Settings
2. Enter your Mailgun API key
3. Copy the webhook URL
4. Log in to your Mailgun account
5. Go to Sending > Webhooks
6. Add the webhook URL for each event type you want to track:
   - Bounced
   - Complained (Spam)
   - Unsubscribed
   - (Optional) Delivered, Opened, Clicked, Failed

## Usage

Once configured, the plugin will automatically:

1. Receive webhook events from Mailgun
2. Store events in the database
3. Tag contacts in Groundhogg based on events:
   - `email-bounced` for bounced emails
   - `spam-complaint` for spam complaints
   - `unsubscribed-via-mailgun` for unsubscribes

You can view all events by going to Groundhogg > Mailgun Events.

## Dashboard

The plugin adds a dashboard widget that shows:
- Count of bounces, complaints, and unsubscribes
- Recent email events
- Link to view all events

## File Structure

```
mailgun-groundhogg/
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── includes/
│   ├── class-mailgun-gh.php
│   ├── class-webhook-handler.php
│   ├── class-events-table.php
│   ├── class-settings.php
│   ├── class-admin.php
│   └── class-dashboard.php
├── languages/
│   └── mailgun-groundhogg.pot
└── mailgun-groundhogg.php
```

## Developers

### Hooks

The plugin provides the following action hook for developers:

```php
do_action('mailgun_gh_process_event', $event_type, $recipient, $data, $contact);
```

This is triggered whenever a Mailgun event is processed, allowing you to add custom logic.

### Example: Custom Event Processing

```php
add_action('mailgun_gh_process_event', 'my_custom_mailgun_processing', 10, 4);

function my_custom_mailgun_processing($event_type, $recipient, $data, $contact) {
    // Your custom logic here
    if ($event_type === 'bounced' && $contact) {
        // Do something with the contact
        $contact->update_meta('last_bounce_date', current_time('mysql'));
    }
}
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Groundhogg plugin

## License

GPL v2 or later

## Support

For support, please contact the plugin author.