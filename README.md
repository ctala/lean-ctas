# Lean CTAs

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)](LICENSE)
[![Version](https://img.shields.io/badge/version-2.3.0-orange)](CHANGELOG.md)

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

## Requirements

- WordPress 6.4+
- PHP 8.1+

## License

GPL-2.0-or-later

---

Made with ❤️ from Chile by [cristiantala.com](https://cristiantala.com)
