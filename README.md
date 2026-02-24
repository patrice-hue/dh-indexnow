# DH IndexNow

> Instant URL submission to Bing (IndexNow) and Google (Indexing API) — built for enterprise WordPress environments.

[![PHP CI](https://github.com/DigitalHitmen/dh-indexnow/actions/workflows/ci.yml/badge.svg)](https://github.com/DigitalHitmen/dh-indexnow/actions)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## Overview

DH IndexNow is a lightweight, zero-dependency WordPress plugin that notifies search engines the moment URLs are published, updated, or deleted — without waiting for the next crawl cycle.

Supports:
- **Bing / IndexNow protocol** — bulk submission up to 100 URLs per request
- **Google Indexing API** — per-URL submission via Service Account OAuth 2.0

Designed for high-output WordPress sites where crawl budget and index freshness directly impact commercial performance.

---

## Features

| Feature | Detail |
|---|---|
| Auto-submit on publish/update | Hooks into `transition_post_status`, queued via WP-Cron to avoid blocking saves |
| Auto-submit on delete | Sends `URL_DELETED` to Google Indexing API |
| Manual bulk submit | Paste URLs or submit entire post types from the admin UI |
| Bing IndexNow | Batched POST to `api.indexnow.org`, key file auto-managed |
| Google Indexing API | JWT auth via Service Account JSON — no plugins or libraries required |
| Submission queue | DB-backed queue with retry logic (3 attempts), status tracking, and CSV export |
| Submission logs | Filterable WP_List_Table showing URL, engine, HTTP status, and response |
| Post type filtering | Select which post types trigger auto-submission |
| URL exclusions | Exclude specific URLs from all submission engines |

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- OpenSSL extension (for Google JWT signing)
- HTTPS on the domain (required by both APIs)
- Google Cloud Service Account with Indexing API enabled
- Bing Webmaster Tools account (to verify submissions)

---

## Installation

### Manual

1. Download the latest `.zip` from [Releases](https://github.com/DigitalHitmen/dh-indexnow/releases)
2. Upload via **Plugins > Add New > Upload Plugin**
3. Activate — the plugin will auto-generate your IndexNow key and create the verification file at your site root

### Via WP-CLI

```bash
wp plugin install https://github.com/DigitalHitmen/dh-indexnow/releases/latest/download/dh-indexnow.zip --activate
```

---

## Configuration

### Step 1 — IndexNow (Bing)

1. Go to **Settings > DH IndexNow > General**
2. Your API key is auto-generated on activation
3. Verify the key file is reachable: the settings page shows a live status check
4. Submit a test URL and confirm receipt in [Bing Webmaster Tools](https://www.bing.com/webmasters)

### Step 2 — Google Indexing API

1. In [Google Cloud Console](https://console.cloud.google.com/):
   - Create a project
   - Enable the **Web Search Indexing API**
   - Create a **Service Account** → generate a JSON key
2. In [Google Search Console](https://search.google.com/search-console):
   - Add the service account email as an **Owner** for your property
3. In **Settings > DH IndexNow > General**:
   - Paste the full Service Account JSON into the credentials field
   - Save — the plugin validates the JSON format on save

### Step 3 — Configure Triggers

- Select which post types should trigger auto-submission
- Add any URLs to the exclusion list
- Confirm "Enable Auto-Submit" is toggled on

---

## Manual Submission

Go to **Settings > DH IndexNow > Manual Submit**:

- **URL list** — paste one URL per line
- **Bulk by post type** — select a post type and submit all published URLs (large sets are processed via WP-Cron in batches)
- Results appear inline showing per-engine HTTP response codes

---

## Logs & Queue

**Settings > DH IndexNow > Logs** shows all queued and processed submissions:

- Filter by: Status (pending / done / failed) · Engine (Bing / Google)
- Export filtered results as CSV
- Clear logs (truncates the queue table)

---

## WP-Cron Schedule

| Event | Interval | Action |
|---|---|---|
| `dh_indexnow_process_queue` | Every 5 minutes | Processes up to 200 pending queue items |

To verify cron is running on your server:

```bash
wp cron event list | grep dh_indexnow
```

If you're using a server-side cron (recommended for production), disable WP-Cron in `wp-config.php`:

```php
define('DISABLE_WP_CRON', true);
```

And add to crontab:

```
*/5 * * * * curl -s https://yoursite.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

---

## Security

- Google Service Account JSON is AES-256 encrypted at rest using `openssl_encrypt` with WordPress `AUTH_KEY`
- All AJAX endpoints require `manage_options` capability + nonce verification
- All DB queries use `$wpdb->prepare()`
- The IndexNow key verification file is served with `X-Robots-Tag: noindex`

---

## Hooks & Filters

```php
// Modify the URL list before submission to Bing
add_filter('dh_indexnow_urls_before_bing', function($urls) {
    return array_filter($urls, fn($url) => str_contains($url, '/products/'));
});

// Modify the URL list before submission to Google
add_filter('dh_indexnow_urls_before_google', function($urls) {
    return $urls;
});

// Add custom post types to auto-trigger
add_filter('dh_indexnow_auto_submit_post_types', function($types) {
    $types[] = 'product';
    return $types;
});
```

---

## Changelog

### v1.0.0
- Initial release
- Bing IndexNow bulk submission
- Google Indexing API via Service Account JWT
- Auto-trigger on publish / update / delete
- DB-backed queue with retry logic
- Manual submission UI + logs

---

## Contributing

1. Fork the repo and create a branch from `develop`
2. Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
3. Run `phpcs --standard=WordPress .` before submitting a PR
4. Open a PR into `develop` with a clear description of the change

---

## License

MIT © [Digital Hitmen](https://digitalhitmen.com.au)
