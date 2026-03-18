# FreeScout WooCommerce My Account

A WordPress plugin that lets WooCommerce customers read and reply to [FreeScout](https://freescout.net/) support tickets **and browse the knowledge base** directly from the **My Account** section of your store — no separate helpdesk login required.

---

## Requirements

| Requirement | Minimum version |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 |
| WooCommerce | 7.0 |
| FreeScout | Any version with REST API enabled |
| FreeScout KB API module | Required for the Knowledge Base feature — see [Knowledge Base](#knowledge-base) (optional) |

---

## Installation

1. Download or clone this repository into your `wp-content/plugins/` directory.
2. Activate the plugin from **WordPress Admin → Plugins**.
3. Go to **WooCommerce → FreeScout** and enter your API credentials (see [Configuration](#configuration)).
4. Visit **My Account → Support Tickets** as a logged-in customer to verify everything works.
5. *(Optional)* Enable the Knowledge Base tab and install a FreeScout Knowledge Base API module on your FreeScout instance (see [Knowledge Base](#knowledge-base)).

> **Tip:** If a My Account tab does not appear after activation, go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules.

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

### Knowledge Base

| Setting | Default | Description |
|---|---|---|
| **Enable Knowledge Base** | Enabled | Shows a **Knowledge Base** tab in My Account. Requires a FreeScout KB API module — see [Knowledge Base](#knowledge-base). |
| **KB Mailbox ID** | — | The numeric ID of the FreeScout mailbox whose knowledge base to display. Find the mailbox ID in FreeScout under **Manage → Mailboxes**. |
| **KB API Token** | — | Authentication token for the KB module. Required for the EcomGraduates module — generate it in **FreeScout → Knowledge Base API → Settings**. Leave blank for the jtorvald module (uses the main API Key). |
| **KB Menu Label** | `Knowledge Base` | Text shown in the My Account navigation menu for the KB tab. |
| **Articles Per Page** | `15` | Number of articles shown per page in category and search views (1–50). |
| **Search Bar** | Enabled | Displays a search bar at the top of the KB home page with live autocomplete. |
| **Category Hierarchy** | — | Only needed for older KB modules that return a flat category list with no parent/child information. Updated versions of the EcomGraduates module include native hierarchy support — leave blank if your module version does. For older modules, define the hierarchy here, one parent per line: `parent_id:child_id,child_id,...`. Example: `1:2,3,9` makes categories 2, 3 and 9 appear as subcategories of category 1. |

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

### Knowledge Base
Customers can browse a full knowledge base. This feature requires a **FreeScout Knowledge Base API module** — the core FreeScout REST API does not expose KB content. Two compatible open-source modules are available:

- [**jtorvald/freescout-knowledge-api**](https://github.com/jtorvald/freescout-knowledge-api) — lightweight; exposes category and article-list endpoints.
- [**EcomGraduates/KnowledgeBaseApiModule**](https://github.com/EcomGraduates/KnowledgeBaseApiModule) — full-featured fork; adds single-article, search, and popular-content endpoints. Recommended.

Install one of the above modules in FreeScout, then set the **KB Mailbox ID** in the plugin settings.

- **Home** — a card grid of all KB categories, each showing its name, description, and article count.
- **Category** — a list of articles within a selected category.
- **Article** — the full article body with breadcrumb navigation back to the category. A "Still need help? Open a ticket" link is shown when new tickets are enabled.
- **Search** — a keyword search across all articles, with a live-autocomplete dropdown that fires as the customer types (results appear after 2 characters, keyboard-navigable).

The KB tab is independent of the ticket feature and can be enabled/disabled separately. If the KB module is not installed or the mailbox ID is not configured, the tab shows a friendly message.

### Security
- All AJAX actions require the user to be logged in and validate a WordPress nonce.
- Ticket detail and reply actions verify that the requested conversation's email address matches the logged-in customer's email — customers cannot view or reply to other customers' tickets.
- Knowledge Base content is read-only and publicly sourced from FreeScout; no customer-specific data is exposed.

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
| `templates/myaccount/kb-home.php` | Knowledge Base home (category grid) |
| `templates/myaccount/kb-category.php` | Category article list |
| `templates/myaccount/kb-article.php` | Single article view |
| `templates/myaccount/kb-search.php` | Search results |

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
| `.fswa-kb` | Knowledge Base page wrapper |
| `.fswa-kb-categories` | Category card grid |
| `.fswa-kb-category-card` | Single category card |
| `.fswa-kb-search` | Search bar wrapper |
| `.fswa-kb-search__suggestions` | Autocomplete dropdown |
| `.fswa-kb-article-list` | Article list (category / search views) |
| `.fswa-kb-article__body` | Article content area |

---

## Shortcodes

Five shortcodes let you embed the ticket form or links to My Account pages anywhere in WordPress (pages, posts, widgets, block editor HTML blocks, etc.).

| Shortcode | Attributes | Description |
|---|---|---|
| `[fswa_new_ticket_form]` | — | Full support ticket submission form. Works for guests **and** logged-in users. |
| `[fswa_tickets_link]` | `text`, `class` | Renders an `<a>` link to the Support Tickets My Account page. |
| `[fswa_kb_link]` | `text`, `class` | Renders an `<a>` link to the Knowledge Base My Account page. |
| `[fswa_tickets_url]` | — | Outputs only the raw URL of the Support Tickets page. |
| `[fswa_kb_url]` | — | Outputs only the raw URL of the Knowledge Base page. |

### `[fswa_kb]`

Renders the full Knowledge Base browser on any WordPress page — no login required. Visitors can browse categories, read articles, and use the live-search autocomplete.

Navigation stays on the same page using `?fswa_kb=` query parameters, so no extra rewrite rules or permalink changes are needed.

**Attributes:**

| Attribute | Default | Description |
|---|---|---|
| `ticket_url` | My Account new-ticket URL | URL for the "Still need help? Open a ticket" button on article pages. Set this to a page that contains `[fswa_new_ticket_form]` so guests can submit without being redirected to login. |

**Examples:**

```
[fswa_kb]

[fswa_kb ticket_url="/contact/support/"]
```

The shortcode respects the **KB Enabled**, **KB Mailbox ID**, and **Search Bar** settings from the plugin admin page.

### `[fswa_new_ticket_form]`

Renders a complete ticket submission form directly on any WordPress page. No attributes are required.

**Guest behaviour:** If the visitor is not logged in, the form shows **Name** and **Email Address** fields (both required). On success the form slides away and a confirmation is shown: *"Your ticket has been submitted. We'll get back to you at you@example.com shortly."*

**Logged-in behaviour:** Name and email are taken from the WordPress account automatically. On success the user is redirected to the new ticket inside My Account.

The form respects the **Allow new tickets** and **Default mailbox** settings from the plugin admin page.

```
[fswa_new_ticket_form]
```

### Link shortcodes

**Attributes** (for `[fswa_tickets_link]` and `[fswa_kb_link]`):

| Attribute | Default | Description |
|---|---|---|
| `text` | Configured menu label | The visible link text. |
| `class` | *(none)* | One or more CSS classes to add to the `<a>` tag. |

**Examples:**

```
[fswa_tickets_link]
[fswa_tickets_link text="View my tickets"]
[fswa_tickets_link text="Open a ticket" class="button"]

[fswa_kb_link]
[fswa_kb_link text="Browse help articles" class="button"]

<!-- URL-only shortcodes are useful inside custom HTML: -->
<a href="[fswa_tickets_url]" class="my-custom-class">Support Tickets</a>
<a href="[fswa_kb_url]">Knowledge Base</a>
```

The `[fswa_tickets_url]` and `[fswa_kb_url]` shortcodes output a plain escaped URL with no surrounding HTML, so they can be used inside your own markup or as `href` values in custom blocks.

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

**The Knowledge Base tab shows an unavailability message or "Method Not Allowed".**
The KB feature requires a separate FreeScout Knowledge Base API module — the core FreeScout REST API does not include KB endpoints. Install either [jtorvald/freescout-knowledge-api](https://github.com/jtorvald/freescout-knowledge-api), [EcomGraduates/KnowledgeBaseApiModule](https://github.com/EcomGraduates/KnowledgeBaseApiModule) (supports nested categories) or [onetwo3D/KnowledgeBaseApiModule](https://github.com/OneTwo3D/KnowledgeBaseApiModule) (supports nested categories, plugin tested with this version) on your FreeScout instance, then set the **KB Mailbox ID** under **WooCommerce → FreeScout → Knowledge Base**.

**The KB search autocomplete does not appear.**
Autocomplete requires at least 2 characters. Also verify the **Search Bar** option is enabled in **WooCommerce → FreeScout → Knowledge Base** and that JavaScript is not blocked on your site.

---

## Changelog

### 1.1.57
- **Fix:** The "Still need help? Open a ticket" button on KB article pages is now hidden when `[fswa_new_ticket_form]` is also present on the same page. This prevents the button from redirecting to the login-required My Account form when the guest-friendly form is already visible on the page.

### 1.1.56
- **Removed:** Knowledge Base API Debugger section from the admin settings page (UI and dead `run_kb_debug()` method).

### 1.1.55
- **Improvement:** Replaced automatic Turnstile detection with a simple **Enable Turnstile** checkbox in the Spam Protection settings section. Enable it to load the Turnstile script and show the widget; disable it when another plugin already handles Turnstile site-wide. Removes the per-request transient write introduced in 1.1.54.

### 1.1.54
- **Improvement:** Turnstile detection is now plugin-independent. `FSWA_MyAccount` hooks into `wp_enqueue_scripts` at priority 999 and checks whether the `cf-turnstile` script handle is already registered by any plugin, caching the result as a one-hour transient. The admin settings page reads that transient to decide whether to show the Spam Protection section. The `[fswa_new_ticket_form]` shortcode uses the same `wp_script_is` check at render time and only enqueues the Turnstile script itself when no other plugin has registered it.
- **Removed:** hardcoded plugin-slug and constant detection in favour of the runtime script check above.

### 1.1.53
- **Improvement:** Turnstile plugin detection now also recognises the XootiX Easy Login — Security addon (`easy-login-addon-security`). Because the addon is a private paid plugin with no public main-file slug, detection uses a folder-prefix match.

### 1.1.52
- **Improvement:** The **Spam Protection** settings section (Turnstile Site Key / Secret Key) is now hidden when a standalone Cloudflare Turnstile plugin is already active (detected via known constants and plugin slugs). This avoids duplicate key management when Turnstile is already handled site-wide.
- **Removed:** **Category Hierarchy** admin setting. Native parent/child hierarchy support in current KB module versions makes this manual mapping unnecessary.

### 1.1.51
- **Fix:** Removed the plugin's own `wp_enqueue_script` call for the Cloudflare Turnstile script. The `<div class="cf-turnstile">` widget is still rendered; Turnstile's globally-loaded script picks it up automatically, eliminating the duplicate script load.

### 1.1.50
- **Style:** Cloudflare Turnstile widget on the `[fswa_new_ticket_form]` is now positioned at the bottom-right of the form, inline with the Submit button via a flex row. On narrow screens (< 480 px) it stacks below the button.

### 1.1.49
- **Fix:** Ticket detail view now correctly loads the conversation thread. The plugin was calling a non-existent `GET /api/conversations/{id}/threads` endpoint; threads are already embedded in the single-conversation response by default (`?embed=threads`). Switching to use that embedded data eliminates the extra API call and fixes the "No messages yet." false-empty state.

### 1.1.48
- **Fix:** Thread content field corrected from `body` to `text` in both `post_reply()` and `create_conversation()`. FreeScout's REST API requires the `text` key for thread content; the previous `body` key was silently ignored, causing every new conversation and reply to be created with an empty message body and FreeScout to return HTTP 400.

### 1.1.47
- **Fix:** New ticket form submit button now restores its original label (e.g. "Submit Ticket") on error instead of always showing "Send Reply".
- **Fix:** AJAX `.fail()` handlers for both the reply form and new-ticket form now surface the actual server-side error message from `response.data.message` rather than a generic fallback. Previously, because `wp_send_json_error()` sets a non-2xx HTTP status, jQuery always routed errors to `.fail()` where the message was discarded.

### 1.1.46
- **Improvement:** API error messages now include the HTTP status code (e.g. "FreeScout API error (HTTP 400): …") to aid debugging.

### 1.1.45
- **Fix:** `create_conversation()` no longer sends empty `firstName` / `lastName` strings in the customer object. FreeScout treats empty string name fields as invalid; name keys are now omitted entirely when the values are blank.

### 1.1.41
- **Fix:** KB category cards now show the correct article count for modules that return `article_count` (snake_case) instead of `articlesCount` (camelCase). Both field names are checked.

### 1.1.40
- **Fix:** Raw SQL/server error messages from FreeScout are no longer shown to regular users. A 500 response (e.g. from a missing DB column after a module update without running its migration) now shows a generic "temporarily unavailable" message to customers, with the actual error detail and a migration hint shown only to admins (`manage_woocommerce`). All other unexpected API errors are similarly gated.

### 1.1.39
- **Fix:** Search result article links now correctly resolve the category ID when the API returns it as a `categories` array (`[{id, name}]`) rather than a flat `categoryId` field. Previously these articles always fell back to the slower `art-{id}` URL scheme that requires an extra server-side API lookup.
- **Improvement:** `render_article()` now uses the `category` object embedded in the article endpoint response (added in the updated EcomGraduates module) for the breadcrumb, eliminating a second `get_kb_categories()` call per article page load. A categories-list fallback is still made only when the article endpoint returns no category data.

### 1.1.37
- **New:** The plugin now uses the native `parent_id` field returned by the updated EcomGraduates KB module (v2+) to determine category hierarchy automatically — the **Category Hierarchy** admin setting is no longer required for users on the updated module.
- **Improvement:** `render_category()` now checks for a `subcategories` array embedded in the category endpoint response before falling back to a second `get_kb_categories()` API call. On updated module versions this eliminates one round-trip per category page load.

### 1.1.35
- **Fix:** KB search no longer redirects to the WordPress blog search page. The search form now submits via `?fswa_q=` instead of `?s=`, which previously triggered WordPress's `redirect_canonical()` and sent users away from the KB.
- **Fix:** KB articles were not rendering when the API wraps each article inside an extra `article` key (`{"article": {...}}`). The article extraction logic now unwraps this envelope before reading title, body, and related fields.
- **New:** KB search result extraction now checks additional top-level keys (`results`, `hits`, `items`) in addition to `articles` and `docs`, improving compatibility with more API module versions.
- **New:** **Category Hierarchy** setting (WooCommerce → FreeScout → Knowledge Base). The EcomGraduates KB API module returns a flat category list with no parent/child information. This new textarea field lets you define the hierarchy manually (`parent_id:child_id,child_id,...`, one parent per line). Child categories are automatically hidden from the KB home page and shown as subcategories when their parent category is opened.
- **Fix:** Category hierarchy mapping was ignored when the API returned a `parentId` (or similar) field set to `null` or `0`. The parent-ID lookup now falls through to the manual hierarchy map whenever the API field is absent or zero, so configured subcategories are correctly hidden from the home page and shown under their parent.
- **New:** Inline admin debug panel on the KB search results page (visible to shop managers only, collapsed by default) that displays the raw API response when a search returns no results — aids diagnosing API compatibility issues without leaving the frontend.

### 1.1.23
- **Fix:** Articles were missing from KB category pages when the API module returns articles nested inside the category object (`data.category.articles`) rather than at the top level (`data.articles`). The extraction logic now checks both locations.
- **Fix:** The same nested-articles issue caused "Article not found" errors when navigating to an article via the jtorvald-module fallback path.
- **Fix:** KB search result links went to the KB home page when the API returned category information as an object (`article.category.id`) instead of a flat integer (`article.categoryId`). Both the full-page search results and the live-search autocomplete now resolve the category ID correctly.
- **Fix:** URL query parameters were encoded with `+` for spaces (RFC1738) instead of `%20` (RFC3986). Some FreeScout installations rejected these requests, causing search and other parameterised API calls to fail silently.

### 1.1.21
- **New:** File attachments on ticket submission forms. Both the My Account form (logged-in) and the `[fswa_new_ticket_form]` shortcode form accept PDF, JPG, PNG, GIF, WebP, and TXT files. Up to 5 files, 10 MB each. Files are sent to FreeScout as base64-encoded thread attachments.
- **New:** Cloudflare Turnstile captcha on the public `[fswa_new_ticket_form]` shortcode form. Configure your Site Key and Secret Key under **WooCommerce → FreeScout → Spam Protection**. The captcha is skipped for logged-in users submitting via My Account. Leave the keys blank to disable.

### 1.1.19
- **Fix:** KB articles were not loading for some categories because the FreeScout Docs module can return articles under the key `docs` rather than `articles`. All article extraction points now check both keys.
- **Fix:** KB live-search AJAX handler was calling a non-existent `get_kb_articles()` API method, causing a PHP fatal error on every autocomplete request. Now correctly uses `search_kb()`.
- **Fix:** KB live-search AJAX handler was calling `article_url()` with only one argument (article ID) instead of the required two (category ID + article ID). Article links in autocomplete suggestions now resolve correctly.

### 1.1.17
- **Style:** Knowledge Base category card icons now render in red (`#dc2626`) to match the KB search button.

### 1.1.16
- **Style:** KB search submit button is now solid red (`#dc2626`), full-height, with a white icon. Hover darkens to `#b91c1c`.

### 1.1.15
- **Fix:** WooCommerce My Account nav icons now use a flex layout on the `<a>` element for reliable vertical alignment across themes (replaces the `vertical-align` + `align-self` approach that broke inside flex containers).

### 1.1.13
- **New:** `[fswa_kb]` shortcode — renders the full Knowledge Base browser (home, categories, articles, search) on any WordPress page without requiring login. Navigation uses `?fswa_kb=` query parameters. Accepts an optional `ticket_url` attribute to point the "Still need help?" button at a custom page (e.g. one containing `[fswa_new_ticket_form]`). KB live-search autocomplete also works for guests.

### 1.1.12
- **New:** `[fswa_new_ticket_form]` shortcode — renders a full ticket submission form on any WordPress page. Guests (non-logged-in visitors) must supply their name and email address; logged-in users are identified automatically. On guest success the form slides away and a confirmation including the guest's email is shown. Logged-in users are redirected to the new ticket in My Account as usual.

### 1.1.11
- **Fix:** Nav icon vertical alignment corrected to `vertical-align: middle` (matches WooCommerce theme convention); added `align-self: center` for flex-based themes.

### 1.1.9
- **New:** SVG icons added to the Support Tickets and Knowledge Base entries in the My Account navigation. Icons use the same Feather icon style as the rest of the plugin (message-square for tickets, book-open for KB). Implemented via CSS `mask-image` so the icon colour automatically inherits the theme's link text colour.
- **New:** Four shortcodes for embedding links to My Account pages anywhere on the site:
  - `[fswa_tickets_link]` / `[fswa_kb_link]` — rendered `<a>` elements with optional `text` and `class` attributes.
  - `[fswa_tickets_url]` / `[fswa_kb_url]` — raw URL output for use inside custom HTML.

### 1.1.7
- **New setting:** **KB API Token** — optional token passed as `?token=...` on all Knowledge Base API requests. Required for the EcomGraduates/KnowledgeBaseApiModule (generate it in FreeScout → Knowledge Base API → Settings). Leave blank when using the jtorvald module, which authenticates via the main API Key header instead.
- Updated KB section description in admin settings to link directly to both supported modules.

### 1.1.6
- **Fix:** Knowledge Base API endpoints corrected from `/api/docs/...` to `/api/knowledgebase/{mailbox_id}/...`. The core FreeScout REST API does not expose KB content; a separate module is required. The old paths collided with internal FreeScout admin routes (POST-only), causing an HTTP 405 error every time the KB section was opened.
- **New setting:** **KB Mailbox ID** — required to construct the correct API URL for the configured mailbox.
- **Compatibility:** KB integration now supports both [jtorvald/freescout-knowledge-api](https://github.com/jtorvald/freescout-knowledge-api) (2-endpoint) and [EcomGraduates/KnowledgeBaseApiModule](https://github.com/EcomGraduates/KnowledgeBaseApiModule) (full-featured). When the single-article endpoint is absent (jtorvald module), the plugin falls back to fetching the parent category and locating the article within the list.
- **Response handling:** added `unwrap()` helper to support both the EcomGraduates `{success, data:{…}}` envelope and plain-array responses from the jtorvald module.
- **Article URLs** updated to `cat-{categoryId}-art-{articleId}` format so the category ID required by the API is preserved in the URL. Existing bookmarked article URLs will redirect to the KB home page.
- **Improved error messages** for HTTP 404 and 405 responses in the KB section, with guidance on which module to install.
- Updated KB documentation in README to reflect the correct module requirements.

### 1.1.0
- Knowledge Base integration powered by the FreeScout Docs module.
  - Category card grid on KB home page.
  - Paginated article lists per category.
  - Full article view with breadcrumb navigation.
  - Full-page keyword search with paginated results.
  - Live-search autocomplete with keyboard navigation.
- New admin settings: KB enable/disable, menu label, articles per page, search bar toggle.

### 1.0.0
- Initial release.
- Ticket list, detail, and new-ticket views in WooCommerce My Account.
- In-page AJAX reply.
- Admin settings page with connection test.
- Theme-overridable templates.

---

## License

GPL-2.0-or-later. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
