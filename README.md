# Eco CTA Plugin

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://www.php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)](LICENSE)

Lightweight WordPress plugin that injects dynamic CTAs into post content based on post type, taxonomy, or category.

**Zero dependencies. Modular PHP. Under 30KB.**

---

## How It Works

The plugin hooks into `the_content` and inserts a CTA block at the configured position, selecting the right CTA using a priority-based matching engine:

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

| Dependency | Version |
|-----------|---------|
| WordPress | ≥ 6.4 |
| PHP | ≥ 8.1 |

## Installation

```bash
# Via Git
cd /path/to/wp-content/plugins/
git clone https://github.com/ctala/eco-cta-plugin.git

# Or upload the zip from GitHub Releases
```

Activate in **Plugins → Installed Plugins**, then configure at **Settings → Eco CTA**.

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
[eco_cta]                                          <!-- global fallback -->
[eco_cta category="16"]                            <!-- legacy compat -->
[eco_cta post_type="glosario"]                     <!-- by post type -->
[eco_cta taxonomy="product_cat" term="42"]         <!-- by taxonomy -->
```

## Project Structure

```
eco-cta-plugin/
├── eco-cta-plugin.php      ← Entry point, bootstrap
├── includes/
│   ├── helpers.php          ← Defaults, getters, sanitizers
│   ├── admin.php            ← Settings page, CTA row renderer
│   └── frontend.php         ← Content injection, matching, shortcode
├── assets/
│   └── css/                 ← (reserved for future extracted styles)
├── languages/               ← Translation files (.pot/.po/.mo)
├── readme.txt               ← WordPress.org standard readme
├── CHANGELOG.md             ← Keep a Changelog format
└── README.md                ← This file
```

## CSS Customization

```css
/* Override from your theme */
.eco-cta-block {
    --eco-accent: #FF6B35;
}
```

## Development

```bash
# Clone
git clone https://github.com/ctala/eco-cta-plugin.git
cd eco-cta-plugin

# No build step needed — pure PHP
# Test on any WP 6.4+ / PHP 8.1+ environment
```

### Versioning

- Follows [Semantic Versioning 2.0.0](https://semver.org/)
- Changelog follows [Keep a Changelog 1.1.0](https://keepachangelog.com/)
- WordPress readme.txt follows [WordPress Plugin Readme Standard](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)

## Roadmap

- [x] Multi post type + taxonomy support
- [x] Position control (inline / end / both)
- [x] Namespaced modular architecture
- [ ] Click tracking (GA4 event / custom endpoint)
- [ ] A/B variants per CTA
- [ ] Multiple CTAs per post (rotation)
- [ ] Block editor sidebar panel
- [ ] REST API endpoint for headless usage

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
