# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.4.0] - 2026-06-09

### Added
- **Opt-in form mode** (`button_type: optin_form`): a CTA can now render an email capture form instead of a button link. Server-rendered `<form method="post">`, zero external dependencies, zero JS required.
- **Listmonk integration**: opt-in forms POST to Listmonk's public `/api/public/subscription` endpoint. No API credentials needed — uses the unauthenticated public endpoint, which respects the list's double opt-in setting. Add your Listmonk base URL in Settings → Lean CTAs.
- **No-JS fallback path** via `admin_post_nopriv_lc_subscribe`: works with full page reload, redirects back with `?lc_done=ok|err` query arg.
- **Progressive enhancement JS** (~1.3 KB inline, vanilla, no jQuery): intercepted fetch submit that swaps the form for the success message without page reload. Only injected on pages with an active opt-in CTA. Falls back to POST if fetch fails or JS is disabled.
- **Honeypot anti-spam**: off-screen hidden input (`lc_hp`) — bots fill it, humans don't see it. Rejection is silent (returns 200) to avoid leaking the mechanism.
- **CLS prevention**: `min-height: 52px` reserved on the state container so switching form→success message does not cause layout shift.
- Per-CTA fields: `optin_list_uuid` (Listmonk list UUID) and `optin_success_msg` (confirmation message shown after submit).
- Admin: Listmonk URL global setting. Button type select shows/hides URL vs optin fields via inline JS.

## [2.3.2] - 2026-03-26

### Fixed
- CTA blocks disappeared when LeanAutoLinks plugin was active. Root cause: CTAs injected at priority 20, but AutoLinks at priority 999 replaced content with its pre-cached version (which never contained the CTA). Moved CTA injection to priority 1001 to run after AutoLinks.

## [2.3.1] - 2026-03-11

### Fixed
- Removed `prefers-color-scheme` media query that overrode theme detection on light themes with OS-level dark mode

## [2.3.0] - 2026-03-11

### Added
- Dark mode support: `prefers-color-scheme`, `.dark`, `[data-theme=dark]`, and auto-detection via background luminosity
- CSS variables `--lean-bg`, `--lean-title`, `--lean-text` for theme-aware styling

### Fixed
- CTA block was a white rectangle on dark themes (hardcoded `#f9f9f9` background)

## [2.2.0] - 2026-03-11

### Changed
- Restored PHP 8.1 minimum requirement (proper `match`, union types, `strict_types`)
- v2.1.1 PHP 7.4 compat was unnecessary — Rocket.net CLI uses 7.4 but web runs 8.3

## [2.1.0] - 2026-03-11

### Added
- Global default accent color setting in admin panel
- Per-CTA color is now optional (falls back to global default)

### Changed
- CSS fallback color is now dynamic from settings instead of hardcoded
- CTA color picker shows "(override)" label with description

## [2.0.1] - 2026-03-11

### Fixed
- Added `ABSPATH` direct access protection to all include files
- Renamed `get_settings()` to `get_plugin_settings()` to avoid collision with deprecated WordPress core function
- Plugin Check (PCP) now passes with zero errors

## [2.0.0] - 2026-03-11

### Changed
- **BREAKING:** Renamed plugin from "Eco CTA Plugin" to "Lean CTAs"
- **BREAKING:** Option key changed from `eco_cta_settings` to `lean_ctas_settings`
- Namespace changed from `EcoCTA` to `LeanCTAs`
- Entry file renamed from `eco-cta-plugin.php` to `lean-ctas.php`
- CSS classes renamed from `eco-cta-*` to `lean-cta-*`
- CSS variable renamed from `--eco-accent` to `--lean-accent`
- Admin page slug changed from `eco-cta` to `lean-ctas`
- Settings group changed from `eco_cta_group` to `lean_ctas_group`

### Added
- Migration hook: auto-imports `eco_cta_settings` on activation
- `[lean_cta]` shortcode (primary)
- `[eco_cta]` shortcode (legacy compat, still works)

## [1.3.0] - 2026-03-11

### Added
- Namespaced architecture (`EcoCTA\Helpers`, `EcoCTA\Admin`, `EcoCTA\Frontend`)
- Activation hook with PHP version check
- "Settings" shortcut link in the plugins list
- Internationalization support (text domain: `eco-cta`)
- `is_admin()` guard in content filter
- PHPDoc blocks on all public functions

### Changed
- Refactored from single file to modular structure (`includes/`)
- All files use `declare(strict_types=1)`
- Strict type comparisons throughout
- WordPress readme.txt for plugin directory compatibility

## [1.2.0] - 2026-03-11

### Added
- Multi post type support with admin checkboxes
- Generic taxonomy/term filtering for any registered public taxonomy
- Priority-based matching engine (type+term → term → type → global)
- Shortcode attributes: `post_type`, `taxonomy`, `term`

## [1.1.0] - 2026-03-11

### Added
- Per-CTA position selector: `inline`, `end`, `both`

## [1.0.0] - 2026-03-11

### Added
- Initial release
- Category-based CTA matching with admin panel
- Inline injection after configurable paragraph N
- `[eco_cta]` shortcode
- Mobile-first CSS with CSS custom properties

[2.4.0]: https://github.com/ctala/lean-ctas/compare/v2.3.2...v2.4.0
[2.3.2]: https://github.com/ctala/lean-ctas/compare/v2.3.1...v2.3.2
[2.3.1]: https://github.com/ctala/lean-ctas/compare/v2.3.0...v2.3.1
[2.3.0]: https://github.com/ctala/lean-ctas/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/ctala/lean-ctas/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/ctala/lean-ctas/compare/v2.0.1...v2.1.0
[2.0.1]: https://github.com/ctala/lean-ctas/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/ctala/lean-ctas/compare/v1.3.0...v2.0.0
[1.3.0]: https://github.com/ctala/lean-ctas/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/ctala/lean-ctas/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/ctala/lean-ctas/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ctala/lean-ctas/releases/tag/v1.0.0
