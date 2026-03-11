=== Lean CTAs ===
Contributors: ctala
Tags: cta, call-to-action, inline-cta, dynamic-cta, content-marketing
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight dynamic CTAs injected into post content by post type, taxonomy, or category. Zero dependencies.

== Description ==

Lean CTAs automatically inserts Call-to-Action blocks inside your content based on the post type, taxonomy, or category. Configure multiple CTAs with different targets and let the priority-based matching engine pick the best one.

**Key Features:**

* **Multi post type** — Posts, pages, any Custom Post Type
* **Taxonomy-aware** — Categories, tags, custom taxonomies
* **Priority matching** — Most specific CTA wins
* **Position control** — Inline, end of post, or both
* **Zero dependencies** — Pure PHP, no build tools
* **Mobile-first** — Responsive CSS with custom properties
* **Shortcode** — `[lean_cta]` for manual placement

== Installation ==

1. Upload `lean-ctas/` to `/wp-content/plugins/`
2. Activate in **Plugins → Installed Plugins**
3. Configure at **Settings → Lean CTAs**

== Changelog ==

= 2.0.0 =
* Renamed from "Eco CTA Plugin" to "Lean CTAs"
* Auto-migration from eco_cta_settings
* New shortcode: [lean_cta] (legacy [eco_cta] still works)

= 1.3.0 =
* Namespaced architecture, strict types, i18n

= 1.2.0 =
* Multi post type + generic taxonomy support

= 1.1.0 =
* Per-CTA position selector

= 1.0.0 =
* Initial release
