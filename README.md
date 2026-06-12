# Lean CTAs

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)](LICENSE)
[![Version](https://img.shields.io/badge/version-2.4.0-orange)](CHANGELOG.md)

Lightweight WordPress plugin that dynamically injects Call-to-Action blocks into your post content based on post type, taxonomy, or category. Zero dependencies, works with any theme.

## Features

- **Smart matching** — Most specific CTA wins (type+term → term → type → global)
- **Per-category colors** — Different accent color per CTA
- **Dark mode** — Auto-detects dark themes, no configuration needed
- **Position control** — Inline (after paragraph N), end of post, or both
- **Any post type** — Posts, pages, custom post types
- **Any taxonomy** — Categories, tags, custom taxonomies
- **Shortcode** — `[lean_cta]` for manual placement
- **Zero dependencies** — Pure PHP, no build tools, no external assets
- **Opt-in form mode** — Email capture form integrated with Listmonk (double opt-in, no API credentials needed)

## Installation

> **Note:** This plugin is not yet available on wordpress.org. Install manually using one of the methods below.

### Option 1 — Direct Download (recommended)

1. Download the latest zip: **[lean-ctas.zip](https://assets.cristiantala.com/tools/lean-ctas.zip)**
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Choose the downloaded `lean-ctas.zip` and click **Install Now**
4. Activate the plugin

### Option 2 — From GitHub

1. Go to [Releases](https://github.com/ctala/lean-ctas/releases) and download the latest `.zip`
2. Upload via **Plugins → Add New → Upload Plugin** in wp-admin

### Option 3 — Manual (FTP/SSH)

1. Download and unzip `lean-ctas.zip`
2. Upload the `lean-ctas/` folder to `/wp-content/plugins/`
3. Activate in **Plugins → Installed Plugins**

## Quick Start

1. Go to **Settings → Lean CTAs**
2. Check **Enable CTAs** and select which post types should show CTAs
3. Set your default accent color
4. Click **+ Add CTA** — fill in title, text, button label, and URL
5. Choose position: inline, end of post, or both
6. Save — done

## How Matching Works

| Priority | Condition | Example |
|----------|-----------|---------|
| 1 (highest) | Post type + specific term | Posts in "Technology" category |
| 2 | Specific term (any type) | Any content tagged "startup" |
| 3 | Post type (any term) | All posts |
| 4 (lowest) | Global fallback | Everything else |

One CTA per post — the best match wins.

## Color System

Set a **default accent color** in settings. Each CTA can optionally override it.

```
Default color: #2563EB (blue)
├── Technology CTA → #2196F3 (override)
├── Startups CTA  → #FF6B35 (override)
├── Business CTA  → #4CAF50 (override)
└── Fallback CTA  → uses default #2563EB
```

The accent color controls the left border and button background.

## Dark Mode

**Works automatically.** No configuration, no CSS overrides, no code changes.

The plugin detects dark themes through:

1. `prefers-color-scheme: dark` (OS-level)
2. `.dark` class on `<html>` or `<body>`
3. `data-theme="dark"` or `data-color-scheme="dark"` attributes
4. **Background luminosity** — measures `<body>` background color and adapts if it's dark

| Property | Light Theme | Dark Theme |
|----------|------------|------------|
| Background | `rgba(0,0,0,.04)` | `rgba(255,255,255,.06)` |
| Title | `#111` | `rgba(255,255,255,.92)` |
| Text | `#444` | `rgba(255,255,255,.7)` |
| Button | Accent color, white text | Same |

## Custom CSS

Override with CSS custom properties:

```css
.lean-cta-block {
    --lean-accent: #FF6B35;
    --lean-bg: rgba(0,0,0,.04);
    --lean-title: #111;
    --lean-text: #444;
}
```

## Shortcode

```
[lean_cta]                        <!-- Best match for current post -->
[lean_cta post_type="glosario"]   <!-- Force post type -->
[lean_cta category="131"]         <!-- Force category -->
```

## Opt-in Form Mode (v2.4.0+)

A CTA can render an email capture form connected to [Listmonk](https://listmonk.app/) instead of a button link.

### How it works

1. Set **Button type** to "Opt-in form (Listmonk)" in the CTA row.
2. Enter the **Listmonk List UUID** (Settings → Lean CTAs → CTA row → Listmonk List UUID). Find it in Listmonk → Lists → Edit. **Multiple lists (v2.4.1+):** comma-separate UUIDs (`uuid1,uuid2`) — the subscriber is added to all of them.
3. Set **Listmonk URL** in the global settings (e.g. `https://listmonk.nyx.cristiantala.com`). No user/token needed.
4. Optionally customize the **Success message**.

### Capture webhook routing (v2.5.0+)

Optionally set **Capture webhook URL (n8n)** in global settings. When set, submissions POST there as JSON (`email`, `list_uuids`, `page_url`, GA `client_id`/`session_id` parsed server-side from the visitor's cookies) so an n8n workflow can handle Listmonk + a server-side GA4 `generate_lead` event (Measurement Protocol, with real session attribution) + any extra plumbing (tagging, CRM). **Automatic fallback:** if the webhook fails (non-2xx / 4s timeout) the plugin falls back to the direct Listmonk path — a capture is never lost. The webhook URL is server-side only; the browser never sees it.

### Placement anywhere (v2.4.1+)

A CTA with **Position: Manual** is never auto-injected; render it wherever you want with `[lean_cta optin="1"]` (e.g. inside a sitewide popup in `wp_footer`). On successful JS subscription the plugin dispatches a `leanctas:subscribed` DOM event so your popup can auto-close.

### Double opt-in

The plugin sends to Listmonk's public endpoint (`/api/public/subscription`). If the target list is configured for double opt-in in Listmonk, Listmonk sends the confirmation email automatically. This is intentional — do not change to `/api/subscribers`.

### No-JS fallback

The form works with `<form method="post">` and a full page reload if JavaScript is disabled or unavailable. With JS, a ~1.3 KB vanilla fetch script intercepts the submit and swaps the form for the success message without reload.

### Smoke test (staging)

1. Install and activate plugin on a staging WP instance.
2. Settings → Lean CTAs: set Listmonk URL to your staging Listmonk instance.
3. Add a CTA: Button type = Opt-in form, List UUID = `<your list uuid>`, any title/text.
4. View a post — the form renders server-side (check "View Source", no JS required).
5. Disable JS in DevTools → submit form → page redirects back with `?lc_done=ok` → success message visible.
6. Re-enable JS → submit form → success message swaps in without reload.
7. Check Listmonk → Subscribers → the email appears (status "unconfirmed" if double opt-in is on, pending confirmation email).
8. Confirm the confirmation email → subscriber becomes active.

For Daily Shot (`ecosistemastartup.com`):
- Listmonk URL: `https://listmonk.nyx.cristiantala.com`
- List UUID: `2c6f425d-96d9-47b6-bc7b-761dd04e185f`

## Requirements

- WordPress 6.4+
- PHP 8.1+

## License

GPL-2.0-or-later

---

Made with love from Chile by [cristiantala.com](https://cristiantala.com)
