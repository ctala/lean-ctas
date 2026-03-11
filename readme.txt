=== Eco CTA Plugin ===
Contributors: ctala
Tags: cta, call-to-action, inline-cta, dynamic-cta, content-marketing
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight dynamic CTAs injected into post content by post type, taxonomy, or category. Zero dependencies. One plugin file.

== Description ==

Eco CTA Plugin automatically inserts Call-to-Action blocks inside your post content based on the post type, taxonomy, or category. Configure multiple CTAs with different targets and let the matching engine pick the best one for each piece of content.

**Key Features:**

* **Multi post type support** — Works with posts, pages, and any Custom Post Type (CPT)
* **Taxonomy-aware matching** — Target CTAs by category, tag, or any custom taxonomy
* **Priority-based matching** — Most specific CTA wins (type+term → term → type → global)
* **Position control** — Inline (after paragraph N), end of post, or both
* **Zero dependencies** — Pure PHP, no JavaScript frameworks, no build tools
* **Mobile-first CSS** — Responsive design with CSS custom properties
* **Shortcode support** — `[eco_cta]` for manual placement anywhere
* **Lightweight** — Under 30KB total, minimal database queries

== Installation ==

1. Upload the `eco-cta-plugin` folder to `/wp-content/plugins/`
2. Activate through **Plugins → Installed Plugins**
3. Configure at **Settings → Eco CTA**

== Configuration ==

= Global Settings =

* **Enabled** — Master on/off switch
* **Post Types** — Checkboxes for which post types the plugin runs on
* **Inline position** — After which paragraph to inject the inline CTA

= Per CTA Settings =

* **Post Type filter** — Restrict to a specific post type or leave blank for all
* **Taxonomy/Term filter** — Restrict to a specific taxonomy term or leave blank
* **Position** — Inline / End / Both
* **Accent color** — Border and button color
* **Title, text, button label, URL** — CTA content

= Matching Priority =

1. Post type + taxonomy term (most specific)
2. Taxonomy term only
3. Post type only
4. Global fallback (no filters)

== Shortcode ==

`[eco_cta]` — Renders the global fallback CTA
`[eco_cta category="16"]` — Legacy: by WordPress category ID
`[eco_cta post_type="glosario"]` — By post type
`[eco_cta taxonomy="product_cat" term="42"]` — By any taxonomy term

== Frequently Asked Questions ==

= Does it work with WooCommerce? =

Yes. Enable the "product" post type and configure CTAs filtered by `product_cat` taxonomy.

= Can I have multiple CTAs in one post? =

Currently one CTA per post (the best match). Multiple CTA support with rotation is planned for a future release.

= Does it affect page speed? =

Minimal impact. The plugin adds one database query (cached) and outputs inline CSS (~400 bytes). No external JS or CSS files are loaded.

== Changelog ==

= 1.3.0 =
* Refactored to namespaced architecture (EcoCTA namespace)
* Separated concerns: helpers, admin, frontend
* Added activation check for PHP 8.1 minimum
* Added "Settings" link in plugins list
* Internationalized all admin strings (text domain: eco-cta)
* Added `is_admin()` guard in content filter
* Improved type safety with `declare(strict_types=1)`
* WordPress Coding Standards compliance

= 1.2.0 =
* Added multi post type support with admin checkboxes
* Added generic taxonomy/term filtering (any public taxonomy)
* Replaced category-only matching with priority-based engine
* Expanded shortcode: `post_type`, `taxonomy`, `term` attributes
* Backward compatible: `[eco_cta category="X"]` still works

= 1.1.0 =
* Added per-CTA position selector: inline, end, both

= 1.0.0 =
* Initial release
* Category-based CTA matching with admin panel
* Inline injection after paragraph N
* Shortcode support
* Mobile-first CSS with custom properties
