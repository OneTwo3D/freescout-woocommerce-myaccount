# FreeScout WooCommerce My Account

A WordPress plugin that lets WooCommerce customers read and reply to [FreeScout](https://freescout.net/) support tickets directly from the **My Account** section of your store — no separate helpdesk login required.

---

## Requirements

| Requirement | Minimum version |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 |
| WooCommerce | 7.0 |
| FreeScout | Any version with REST API enabled |

---

## Installation

1. Download or clone this repository into your `wp-content/plugins/` directory.
2. Activate the plugin from **WordPress Admin → Plugins**.
3. Go to **WooCommerce → FreeScout** and enter your API credentials (see [Configuration](#configuration)).
4. Visit **My Account → Support Tickets** as a logged-in customer to verify everything works.

> **Tip:** If the "Support Tickets" menu item does not appear after activation, go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules.

---

## Configuration

All settings live under **WooCommerce → FreeScout** in the WordPress admin.

### API Connection

| Setting | Description |
|---|---|
| **FreeScout URL** | The base URL of your FreeScout installation, e.g. `https://support.example.com`. No trailing slash needed. |
| **API Key** | A FreeScout API key. Generate one in FreeScout under **Your Profile → API Keys**. |

Use the **Test API Connection** button to confirm the credentials are valid. A successful test reports the number of mailboxes found.

### Display Options

| Setting | Default | Description |
|---|---|---|
| **Menu Label** | `Support Tickets` | Text shown in the My Account navigation menu. |
| **Tickets Per Page** | `10` | Number of tickets shown per page in the list view (1–50). |
| **Allow New Tickets** | Enabled | When enabled, customers see a **+ New Ticket** button and can open new support conversations. |

### New Ticket Defaults

| Setting | Description |
|---|---|
| **Default Mailbox ID** | Numeric ID of the FreeScout mailbox that new tickets are sent to. Leave blank to show a department selector to the customer. Find the mailbox ID in FreeScout under **Manage → Mailboxes**. |

---

## Features

### Ticket list
Customers see a paginated table of all their support conversations, sorted by most recently updated. Each row shows the ticket number, subject, current status, and last-updated timestamp.

### Ticket detail
Clicking a ticket opens the full conversation thread. Customer messages are displayed on the right; agent replies on the left. Internal notes are hidden from customers.

### Reply to a ticket
An in-page reply form is shown below the thread for any open ticket. Replies are submitted via AJAX — the new message appears in the thread immediately without a full page reload. The form is hidden for closed or spam tickets.

### New ticket
When enabled, customers can open a new ticket by clicking **+ New Ticket**. They enter a subject and message, and optionally choose a department (mailbox). On success they are redirected to the newly created ticket.

### Security
- All AJAX actions require the user to be logged in and validate a WordPress nonce.
- Ticket detail and reply actions verify that the requested conversation's email address matches the logged-in customer's email — customers cannot view or reply to other customers' tickets.

---

## Template Customisation

All frontend templates can be overridden from your active theme without modifying plugin files. Copy the template you want to customise to:

```
<theme>/fswa/myaccount/<template-name>.php
```

Available templates:

| Plugin path | Purpose |
|---|---|
| `templates/myaccount/tickets.php` | Ticket list |
| `templates/myaccount/tickets-empty.php` | Empty state (no tickets found) |
| `templates/myaccount/ticket-detail.php` | Single ticket / conversation thread |
| `templates/myaccount/ticket-new.php` | New ticket form |

---

## Styling

Frontend styles are loaded from `assets/css/frontend.css` on all My Account pages. The stylesheet uses plain CSS with [BEM](https://getbem.com/)-style class names prefixed with `fswa-`, so it is straightforward to override from your theme:

```css
/* Example: change the customer message bubble colour */
.fswa-message--customer {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
```

Key class names:

| Class | Element |
|---|---|
| `.fswa-tickets` | Ticket list wrapper |
| `.fswa-ticket-row` | Single row in the ticket table |
| `.fswa-status` | Status badge (modifier `--active`, `--pending`, `--closed`, `--spam`) |
| `.fswa-thread` | Conversation thread container |
| `.fswa-message` | Individual message (modifier `--customer` or `--agent`) |
| `.fswa-reply-form` | Reply form wrapper |
| `.fswa-new-ticket` | New ticket form wrapper |
| `.fswa-notice` | Inline notice (modifier `--success`, `--error`, `--info`, `--warning`) |

---

## Hooks & Filters

The plugin is built with extensibility in mind. The following WordPress hooks are available:

### Filters

```php
// Change the number of conversations fetched per API request.
add_filter( 'fswa_per_page', function( $per_page ) {
    return 20;
} );
```

> Additional filters and actions will be documented here as the plugin evolves.

---

## Frequently Asked Questions

**The "Support Tickets" tab is not showing in My Account.**
Go to **Settings → Permalinks** and click **Save Changes** to regenerate rewrite rules. This is a one-time step required after activation.

**Customers see "Support tickets are not available at the moment."**
The API URL or API key is missing or incorrect. Check the settings under **WooCommerce → FreeScout** and use the **Test API Connection** button.

**A customer sees no tickets even though they have open conversations in FreeScout.**
FreeScout looks up the customer by email address. Ensure the email address of the WooCommerce customer exactly matches the email address associated with the conversations in FreeScout.

**Can I use this plugin with a self-hosted FreeScout instance behind a private network?**
Yes, as long as the WordPress server can reach your FreeScout URL over HTTP/HTTPS. The plugin uses `wp_remote_get` / `wp_remote_post`, which respect standard WordPress HTTP settings including proxy configuration.

**Is TLS/HTTPS required?**
Not strictly, but strongly recommended. API keys are transmitted in request headers, so an unencrypted connection would expose them.

---

## Changelog

### 1.0.0
- Initial release.
- Ticket list, detail, and new-ticket views in WooCommerce My Account.
- In-page AJAX reply.
- Admin settings page with connection test.
- Theme-overridable templates.

---

## License

GPL-2.0-or-later. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
