=== Lean CTAs ===
Contributors: ctala
Tags: cta, call-to-action, inline-cta, dynamic-cta, content-marketing
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.3.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight dynamic CTAs injected into post content by post type, taxonomy, or category. Zero dependencies.

== Description ==

Lean CTAs automatically inserts Call-to-Action blocks inside your content based on the post type, taxonomy, or category. Configure multiple CTAs with different targets and let the priority-based matching engine pick the best one.

**Key Features:**

* **Multi post type** — Posts, pages, any Custom Post Type
* **Taxonomy-aware** — Categories, tags, custom taxonomies
* **Priority matching** — Most specific CTA wins (type+term → term → type → global)
* **Position control** — Inline, end of post, or both
* **Per-category colors** — Different accent color per CTA
* **Dark mode support** — Auto-detects dark themes, no configuration needed
* **Zero dependencies** — Pure PHP, no build tools
* **Mobile-first** — Responsive CSS with custom properties
* **Shortcode** — `[lean_cta]` for manual placement

== Installation ==

1. Upload `lean-ctas/` to `/wp-content/plugins/`
2. Activate in **Plugins → Installed Plugins**
3. Configure at **Settings → Lean CTAs**

== Quick Start ==

After activating:

1. Go to **Settings → Lean CTAs**
2. Check **Enable CTAs**
3. Select which **post types** should show CTAs (e.g., Posts, Pages)
4. Pick a **default accent color** using the color picker
5. Set the **paragraph number** for inline insertion (default: after paragraph 3)
6. Click **+ Add CTA** to create your first CTA
7. Fill in the title, text, button label, and URL
8. Choose the **position**: inline, end, or both
9. Save Changes — done!

== How It Works ==

= Priority Matching =

When a post has multiple possible CTAs, the most specific one wins:

1. **Type + Term** — CTA targets post type AND a specific category/term (highest priority)
2. **Term only** — CTA targets any post in a specific category/term
3. **Type only** — CTA targets all posts of a given type
4. **Global** — CTA with no post type or term set (fallback)

Only one CTA is shown per post — the best match.

= Color System =

Each CTA uses an **accent color** for its left border and button background.

* **Default color** — Set once in Settings. All CTAs use this unless overridden.
* **Per-CTA override** — Each CTA has an optional color picker. Set it to use a different color for that specific CTA.

Example setup with different colors per category:

* Technology posts → blue `#2196F3`
* Startups posts → orange `#FF6B35`
* Business posts → green `#4CAF50`
* AI posts → purple `#9C27B0`

= Dark Mode =

**No configuration needed.** The plugin automatically adapts to your theme:

* Detects `prefers-color-scheme: dark` (OS-level dark mode)
* Detects common dark theme classes: `.dark`, `[data-theme="dark"]`, `[data-color-scheme="dark"]`
* **Auto-detects any dark theme** by measuring the background color luminosity — if your theme has a dark background, CTAs adapt automatically

On dark themes:
* Background becomes semi-transparent white (6% opacity) instead of light gray
* Title text becomes white (92% opacity)
* Body text becomes white (70% opacity)
* Button keeps the accent color with white text

This works with any WordPress theme — GeneratePress, Astra, Flavor, Flavor, custom themes, etc. No CSS overrides or code changes required.

= Shortcode =

Use `[lean_cta]` anywhere to manually place a CTA:

* `[lean_cta]` — Shows the best matching CTA for the current post
* `[lean_cta post_type="glosario"]` — Force a specific post type CTA
* `[lean_cta category="131"]` — Force a specific category CTA

Legacy shortcode `[eco_cta]` is also supported.

== Configuration Examples ==

= Single Global CTA =

One CTA for all posts:

1. Add a CTA, leave Post Type and Taxonomy empty
2. Fill in your title, text, button
3. Choose position: "both" for inline + end of post

= Per-Category CTAs =

Different messages per category (e.g., a blog with Tech, Business, Personal sections):

1. Add a CTA for each category:
   * Post type: Post | Taxonomy: category | Term: (select your category)
2. Add one fallback CTA with no post type or term (catches everything else)
3. Each CTA can have its own accent color

= Custom Post Type =

Works with any registered post type:

1. The post type appears automatically in the checkboxes
2. Add a CTA targeting that post type
3. If the CPT has custom taxonomies, those appear in the taxonomy dropdown too

== Frequently Asked Questions ==

= Does it work with my theme? =

Yes. Lean CTAs uses CSS custom properties and inline styles that work with any theme. Dark themes are detected and handled automatically.

= Can I have multiple CTAs on one post? =

Currently, one CTA per post (the best match wins). Multiple CTAs per post is planned for a future version.

= Does it affect performance? =

Minimal impact. Zero JavaScript dependencies, one tiny inline `<style>` block, and a small dark-mode detection script (~150 bytes). No external CSS or JS files loaded.

= Can I style it with custom CSS? =

Yes. Override the CSS custom properties:

`
.lean-cta-block {
    --lean-accent: #FF6B35;  /* border + button color */
    --lean-bg: rgba(0,0,0,.04);  /* background */
    --lean-title: #111;  /* title color */
    --lean-text: #444;  /* body text color */
}
`

= Where is the settings page? =

**Settings → Lean CTAs** in your WordPress admin.

== Screenshots ==

1. Settings page — configure CTAs with color pickers and taxonomy targeting
2. CTA on a light theme — clean inline block with accent border
3. CTA on a dark theme — auto-adapted colors, seamless integration

== Upgrade Notice ==

= 2.3.2 =
Fix: CTAs now appear correctly when LeanAutoLinks plugin is active.

== Changelog ==

= 2.3.2 =
* Fixed CTAs disappearing when LeanAutoLinks plugin was active. Moved content filter to priority 1001 to run after AutoLinks cache layer (priority 999).

= 2.3.1 =
* Fixed prefers-color-scheme media query overriding theme detection on light themes with OS dark mode

= 2.3.0 =
* Added dark mode support with automatic theme detection
* CSS custom properties for theme-aware colors (--lean-bg, --lean-title, --lean-text)
* Supports prefers-color-scheme, .dark class, data-theme attribute, and luminosity-based auto-detection

= 2.2.0 =
* Restored PHP 8.1 minimum with proper match expressions and union types

= 2.1.0 =
* Global default accent color setting (no more hardcoded colors)
* Per-CTA color is now optional (falls back to global default)

= 2.0.1 =
* Added ABSPATH direct access protection
* Fixed function name collision with WordPress core

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
