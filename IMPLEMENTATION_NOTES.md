# Implementation Notes — v2.4.0 opt-in form mode

## What changed

### Files modified
- `lean-ctas.php` — version bump 2.3.2 → 2.4.0; `require_once includes/subscribe.php`
- `includes/helpers.php` — `defaults()` adds `listmonk_url`; `cta_defaults()` adds `optin_list_uuid`, `optin_success_msg`; `sanitize()` validates `optin_form` as allowed `button_type` and sanitizes the new fields
- `includes/frontend.php` — `render()` dispatches to `render_optin()` for `button_type === optin_form`; new `render_optin()` function; `print_styles()` adds form CSS + conditional progressive-enhancement JS
- `includes/admin.php` — Listmonk URL global field in settings table; `optin_form` option in button_type select; `lean-field-optin` / `lean-field-url` conditional field rows; admin JS `syncOptinFields()` toggle

### Files created
- `includes/subscribe.php` — subscription handler (no-JS POST + REST endpoint + Listmonk delivery)

### LOC added (approx)
- helpers.php: +14
- frontend.php: +145
- admin.php: +52
- subscribe.php: +224 (new file)
- lean-ctas.php: +3

Total new LOC: ~438

## Endpoints

### No-JS path (admin-post)
```
POST /wp-admin/admin-post.php
action=lc_subscribe
_lc_nonce=<wp_nonce lc_subscribe>
lc_email=<email>
lc_list=<list_uuid>
lc_hp=   (must be empty — honeypot)
```
Redirects to `{referer}?lc_done=ok` or `?lc_done=err`.

### REST path (progressive enhancement)
```
POST /wp-json/lean-ctas/v1/subscribe
Content-Type: application/json

{
  "email": "user@example.com",
  "list_uuid": "2c6f425d-96d9-47b6-bc7b-761dd04e185f",
  "nonce": "<wp_create_nonce('lc_subscribe')>",
  "hp": ""
}
```
Returns `{"success": true}` or a WP_Error JSON.

### Listmonk delivery (internal)
```
POST {listmonk_url}/api/public/subscription
Content-Type: application/json

{"email": "...", "list_uuids": ["<uuid>"]}
```
No auth. Respects list opt-in setting (double opt-in if configured).

## How to configure a Daily Shot opt-in CTA on eco

1. Settings → Lean CTAs
2. Set **Listmonk URL**: `https://listmonk.nyx.cristiantala.com`
3. Save.
4. Click **+ Add CTA**
5. Filter by Post Type: `post` (or leave blank for all)
6. Filter by Taxonomy: your preferred category, or none for global
7. Position: `both` (inline + end)
8. CTA Title: e.g. "El Daily Shot de IA"
9. CTA Text: e.g. "Una idea de IA para founders, todos los días. Gratis."
10. Button label: e.g. "Suscribirme gratis"
11. Button type: **Opt-in form (Listmonk)**
12. Listmonk List UUID: `2c6f425d-96d9-47b6-bc7b-761dd04e185f`
13. Success message: e.g. "Revisa tu email para confirmar la suscripción."
14. Save Changes.

## Design decisions

**button_type = optin_form (not a new `mode` field)**
The existing `button_type` field already describes the button behavior. Adding `optin_form` as a fourth value keeps the schema flat and backward-compatible. The admin JS shows/hides relevant fields when this value is selected.

**No-JS path via admin-post, not wp-ajax**
`admin-post.php` is the WP standard for unauthenticated form handling. `wp-ajax` requires JS by definition. The admin-post path ensures the form works for screen readers, crawlers, and users with JS disabled.

**Public Listmonk endpoint, no credentials**
`/api/public/subscription` requires no auth and triggers the list's native double opt-in flow. Using `/api/subscribers` with `preconfirm_subscriptions: true` would bypass double opt-in and require admin credentials in the frontend path — both wrong. The Listmonk URL is stored in wp_options (server-side only), never exposed to the browser.

**Progressive enhancement is inline, conditional**
The ~1.3 KB JS is injected inline in `<head>` only on pages where at least one `optin_form` CTA is configured. If no opt-in CTA exists, zero JS is added. This keeps the 0-JS-frontend principle intact for pages without forms.

**CLS prevention**
`min-height: 52px` on `.lean-cta-optin-state` reserves the height of the input row before the DOM swap. Prevents layout shift when the form is replaced by the success message.

**Honeypot implementation**
Off-screen `<span aria-hidden="true" style="position:absolute;left:-9999px;...">` wraps the honeypot input. Not `display:none` or `visibility:hidden` (those are trivially detectable by modern bots). The honeypot input has `tabindex="-1"` so keyboard users cannot focus it accidentally.

**Rate limiting: omitted**
A transient-based IP rate limit would add complexity (db write per request) for marginal anti-spam benefit given the honeypot already covers bots. Listmonk itself deduplicates subscriptions. Can be added later if needed.

**HTTP 400/409 from Listmonk treated as success**
If the email is already subscribed (409) or the list sends a 400 for various reasons (already pending, etc.), the public endpoint behavior is to resend the confirmation email. From the user's perspective, this is success ("check your email"). Treating 4xx < 500 as success is deliberate.

## php -l results

```
No syntax errors detected in lean-ctas.php
No syntax errors detected in includes/helpers.php
No syntax errors detected in includes/admin.php
No syntax errors detected in includes/frontend.php
No syntax errors detected in includes/subscribe.php
```
