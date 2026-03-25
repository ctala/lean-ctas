# Lean CTAs — Agent Reference

*Machine-readable documentation for AI agents configuring this plugin.*

## Identity

- **Slug:** `lean-ctas`
- **Version:** 2.3.1
- **Option Key:** `lean_ctas_settings`
- **Text Domain:** `lean-ctas`
- **Requires:** WordPress 6.4+, PHP 8.1+
- **Entry File:** `lean-ctas.php`
- **Namespace:** `LeanCTAs`
- **Settings Page:** `options-general.php?page=lean-ctas`

## Configuration Schema

The plugin stores all settings in a single `wp_option` with key `lean_ctas_settings`.

```json
{
  "enabled": true,
  "insert_after_paragraph": 3,
  "default_color": "#FF6B35",
  "post_types": ["post", "glosario"],
  "ctas": [
    {
      "post_type": "",
      "taxonomy": "",
      "term_id": 0,
      "title": "CTA Title",
      "text": "CTA body text",
      "button_label": "Click here",
      "button_url": "https://example.com",
      "button_type": "link",
      "accent_color": "",
      "position": "both"
    }
  ]
}

```

### Field Reference — Global Settings

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `enabled` | bool | `true` | Master toggle — enables/disables all CTA injection |
| `insert_after_paragraph` | int | `3` | Paragraph number after which inline CTAs are inserted (min: 1) |
| `default_color` | string (hex) | `#FF6B35` | Global accent color (left border + button background) |
| `post_types` | string[] | `["post"]` | Which post types show CTAs. Must be registered public post types |

### Field Reference — CTA Entry (`ctas[]`)

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `post_type` | string | `""` | Filter: only this post type. Empty = all enabled types |
| `taxonomy` | string | `""` | Filter taxonomy slug (e.g. `category`, `post_tag`). Empty = no filter |
| `term_id` | int | `0` | Filter term ID within the taxonomy. `0` = no filter |
| `title` | string | `""` | CTA heading text |
| `text` | string | `""` | CTA body/description text |
| `button_label` | string | `""` | Button text |
| `button_url` | string | `""` | Button destination URL |
| `button_type` | enum | `"link"` | `link` \| `newsletter` \| `community` (visual hint only, no functional difference) |
| `accent_color` | string (hex) | `""` | Per-CTA color override. Empty = uses `default_color` |
| `position` | enum | `"inline"` | `inline` (after paragraph N) \| `end` (bottom of post) \| `both` |

## Priority Matching

When multiple CTAs could match a post, the most specific wins:

```
Priority 1 (highest): post_type + taxonomy:term_id  → e.g. "post" in category 434
Priority 2:           taxonomy:term_id only          → e.g. any content in category 434
Priority 3:           post_type only                 → e.g. all "post" types
Priority 4 (lowest):  no filters (global fallback)   → everything else
```

**Only one CTA is shown per post** — the highest priority match.

## Programmatic Access

### WP-CLI (recommended for agents)

```bash
# Read current settings
wp option get lean_ctas_settings --format=json

# Full replace (⚠️ replaces entire option)
wp option update lean_ctas_settings '{"enabled":true,"insert_after_paragraph":3,"default_color":"#FF6B35","post_types":["post"],"ctas":[...]}' --format=json

# Check if plugin is active
wp plugin is-active lean-ctas && echo "active" || echo "inactive"

# Activate
wp plugin activate lean-ctas

# Get version
wp plugin get lean-ctas --field=version
```

### WordPress REST API

The option is **not exposed via REST API by default** (`show_in_rest` is not enabled).

To read/write settings programmatically from outside WordPress, use WP-CLI or direct database access:

```bash
# Via WP-CLI remotely (SSH)
ssh user@host "cd /path/to/wordpress && wp option get lean_ctas_settings --format=json"
```

### PHP Direct

```php
// Read
$settings = get_option( 'lean_ctas_settings', [] );

// Write (merging with defaults recommended)
$settings['ctas'][] = [
    'post_type'    => 'post',
    'taxonomy'     => 'category',
    'term_id'      => 434,
    'title'        => 'New CTA',
    'text'         => 'Description here',
    'button_label' => 'Learn more',
    'button_url'   => 'https://example.com',
    'button_type'  => 'link',
    'accent_color' => '#2196F3',
    'position'     => 'both',
];
update_option( 'lean_ctas_settings', $settings );
```

### WordPress Application Passwords (HTTP API)

```bash
# Read settings via WP REST + custom endpoint (if added)
# Currently requires WP-CLI. REST endpoint planned for future version.

# Alternative: use WordPress Application Password for wp-json auth
curl -u "username:app-password" \
  "https://site.com/wp-json/wp/v2/settings" \
  -H "Content-Type: application/json"
```

## Shortcodes

| Shortcode | Attributes | Description |
|-----------|-----------|-------------|
| `[lean_cta]` | none | Best matching CTA for current post |
| `[lean_cta post_type="glosario"]` | `post_type` | Force CTA match for specific post type |
| `[lean_cta category="131"]` | `category` (term ID) | Force CTA match for specific category |
| `[eco_cta]` | same as `[lean_cta]` | Legacy shortcode (backward compat from v1.x) |

## Hooks & Filters

### Filters

| Filter | Parameters | Description |
|--------|-----------|-------------|
| `pre_update_option_lean_ctas_settings` | `$value` | Parses combo `taxonomy:term_id` values from admin form before save |

### Actions

| Action | When |
|--------|------|
| `admin_menu` | Registers settings page under Settings |
| `admin_init` | Registers setting with sanitization |

## CSS Customization

```css
/* Override CTA appearance */
.lean-cta-block {
    --lean-accent: #FF6B35;           /* border + button color */
    --lean-bg: rgba(0,0,0,.04);       /* background */
    --lean-title: #111;               /* title text */
    --lean-text: #444;                /* body text */
}
```

Dark mode adapts automatically via:
1. `prefers-color-scheme: dark`
2. `.dark` class on `<html>` or `<body>`
3. `data-theme="dark"` / `data-color-scheme="dark"` attributes
4. Background luminosity auto-detection

## Common Agent Tasks

### Task: Deploy to a new WordPress site

```bash
# 1. Download
wget https://assets.cristiantala.com/tools/lean-ctas.zip -O /tmp/lean-ctas.zip

# 2. Install + activate
wp plugin install /tmp/lean-ctas.zip --activate

# 3. Configure (example: single global CTA)
wp option update lean_ctas_settings '{
  "enabled": true,
  "insert_after_paragraph": 3,
  "default_color": "#2563EB",
  "post_types": ["post"],
  "ctas": [{
    "post_type": "",
    "taxonomy": "",
    "term_id": 0,
    "title": "Join our community",
    "text": "Get weekly insights on startups and technology.",
    "button_label": "Join free →",
    "button_url": "https://www.skool.com/cagala-aprende-repite/about",
    "button_type": "community",
    "accent_color": "",
    "position": "both"
  }]
}' --format=json
```

### Task: Add a CTA for a specific category

```bash
# 1. Get current settings
CURRENT=$(wp option get lean_ctas_settings --format=json)

# 2. Add new CTA entry via jq (or manually)
echo "$CURRENT" | jq '.ctas += [{
  "post_type": "post",
  "taxonomy": "category",
  "term_id": 434,
  "title": "Tech Resources",
  "text": "Deep dives into automation and AI tools.",
  "button_label": "Explore →",
  "button_url": "https://example.com/tech",
  "button_type": "link",
  "accent_color": "#2196F3",
  "position": "inline"
}]' | wp option update lean_ctas_settings --format=json
```

### Task: Disable all CTAs temporarily

```bash
wp option patch update lean_ctas_settings enabled 0
# Note: wp option patch works for simple top-level fields
```

### Task: Check current configuration

```bash
wp option get lean_ctas_settings --format=json | jq .
```

## File Structure

```
lean-ctas/
├── lean-ctas.php          # Entry point, constants, bootstrap
├── includes/
│   ├── admin.php          # Settings page, form rendering
│   ├── frontend.php       # Content filter, CTA injection, shortcode
│   └── helpers.php        # Defaults, sanitization, utilities
├── assets/                # (reserved for future static assets)
├── languages/             # i18n .pot/.po/.mo files
├── AGENT.md               # This file (agent reference)
├── README.md              # Human documentation
├── readme.txt             # WordPress.org format
├── CHANGELOG.md           # Version history
└── LICENSE                # GPL-2.0-or-later
```

---

*Last updated: 2026-03-12 — v2.3.1*
