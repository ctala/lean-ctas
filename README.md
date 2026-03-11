# Lean CTAs

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://www.php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)](LICENSE)

Lightweight WordPress plugin that injects dynamic CTAs into post content based on post type, taxonomy, or category.

**Zero dependencies. Modular PHP. Under 30KB.**

---

## How It Works

Hooks into `the_content` and inserts a CTA block at the configured position, selecting the right CTA using a priority-based matching engine:

```
[Paragraph 1]
[Paragraph 2]
[Paragraph 3]
┌─────────────────────────────────────────┐
│  🚀 Looking for funding?               │
│  We share open calls every week.        │
│  [ 📧 Subscribe free ]                 │
└─────────────────────────────────────────┘
[Paragraph 4...]
```

## Use Cases

| Scenario | Configuration |
|----------|---------------|
| News blog | Different CTA per category (Tech → lead magnet, Finance → newsletter) |
| Glossary (CPT) | Community CTA on every glossary entry |
| WooCommerce | CTA by product category |
| Any categorized site | Global fallback + specific overrides |

## Requirements

| Dependency | Minimum |
|-----------|---------|
| WordPress | 6.4 |
| PHP | 8.1 |

## Installation

```bash
cd /path/to/wp-content/plugins/
git clone https://github.com/ctala/lean-ctas.git
```

Activate in **Plugins → Installed Plugins**, then configure at **Settings → Lean CTAs**.

## Configuration

### Global Settings

- **Enabled** — Master on/off
- **Post Types** — Which post types the plugin runs on
- **Inline position** — After which paragraph to inject

### Per CTA

| Field | Description |
|-------|-------------|
| Post Type | Filter by specific type or "All enabled" |
| Taxonomy/Term | Filter by any public taxonomy term |
| Position | Inline (paragraph N) / End of post / Both |
| Accent color | Border + button color |
| Title, text, button, URL | CTA content |

### Matching Priority

1. **Post type + term** → most specific match
2. **Term only** → cross-type taxonomy match
3. **Post type only** → generic type CTA
4. **Global fallback** → no filters set

### Shortcode

```
[lean_cta]                                         <!-- global fallback -->
[lean_cta category="16"]                           <!-- by WP category -->
[lean_cta post_type="glosario"]                    <!-- by post type -->
[lean_cta taxonomy="product_cat" term="42"]        <!-- by taxonomy -->
```

Legacy `[eco_cta]` shortcode is still supported.

## Project Structure

```
lean-ctas/
├── lean-ctas.php            ← Entry point, bootstrap
├── includes/
│   ├── helpers.php          ← Defaults, getters, sanitizers
│   ├── admin.php            ← Settings page, CTA row renderer
│   └── frontend.php         ← Content injection, matching, shortcode
├── assets/css/              ← (reserved for future extracted styles)
├── languages/               ← Translation files
├── readme.txt               ← WordPress.org standard readme
├── CHANGELOG.md             ← Keep a Changelog format
└── README.md                ← This file
```

## CSS Customization

```css
.lean-cta-block {
    --lean-accent: #FF6B35;
}
```

## Upgrading from Eco CTA Plugin

Lean CTAs v2.0.0 auto-migrates settings from `eco_cta_settings` on activation. The `[eco_cta]` shortcode still works.

## Roadmap

- [x] Multi post type + taxonomy support
- [x] Position control (inline / end / both)
- [x] Namespaced modular architecture
- [ ] Click tracking (GA4 / custom endpoint)
- [ ] A/B variants per CTA
- [ ] Multiple CTAs per post (rotation)
- [ ] Block editor sidebar panel
- [ ] REST API endpoint for headless

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
