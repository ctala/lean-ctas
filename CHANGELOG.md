# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-03-11

### Added
- Namespaced architecture (`EcoCTA\Helpers`, `EcoCTA\Admin`, `EcoCTA\Frontend`)
- Activation hook with PHP version check
- "Settings" shortcut link in the plugins list
- Internationalization support (text domain: `eco-cta`)
- `is_admin()` guard in content filter to prevent injection in admin previews
- PHPDoc blocks on all public functions

### Changed
- Refactored from single file to modular structure (`includes/`)
- All files use `declare(strict_types=1)`
- Strict type comparisons throughout (`===`, `in_array(..., true)`)
- Render function uses string concatenation instead of `ob_get_clean()` for performance
- WordPress readme.txt added for plugin directory compatibility

### Fixed
- Edge case where empty CTA array in form submission could cause warning

## [1.2.0] - 2026-03-11

### Added
- Multi post type support with admin checkboxes
- Generic taxonomy/term filtering for any registered public taxonomy
- Priority-based matching engine (type+term → term → type → global)
- Shortcode attributes: `post_type`, `taxonomy`, `term`

### Changed
- Admin UI: replaced category-only dropdown with grouped taxonomy/term selector
- Matching logic now supports 4-level priority cascade

### Deprecated
- `category_id` field in CTA data (replaced by `taxonomy` + `term_id`)

## [1.1.0] - 2026-03-11

### Added
- Per-CTA position selector: `inline`, `end`, `both`
- "Both" option injects CTA inline AND at end of post

## [1.0.0] - 2026-03-11

### Added
- Initial release
- Category-based CTA matching
- Admin settings page under Settings → Eco CTA
- Dynamic CTA addition/removal in admin UI
- Inline injection after configurable paragraph N
- `[eco_cta]` shortcode with `category` attribute
- Mobile-first CSS with CSS custom properties (`--eco-accent`)
- Color picker per CTA
- Button types: link, newsletter, community (with emoji icons)

[1.3.0]: https://github.com/ctala/eco-cta-plugin/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/ctala/eco-cta-plugin/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/ctala/eco-cta-plugin/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ctala/eco-cta-plugin/releases/tag/v1.0.0
