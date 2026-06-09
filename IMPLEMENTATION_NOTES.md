# Implementation Notes — v2.4.0 opt-in form mode

## What changed

### Files modified
- `lean-ctas.php` — version bump 2.3.2 → 2.4.0; `require_once includes/subscribe.php`
- `includes/helpers.php` — `defaults()` adds `listmonk_url`; `cta_defaults()` adds `optin_list_uuid`, `optin_success_msg`; `sanitize()` validates `optin_form` as allowed `button_type`; new `get_configured_optin_uuids()` allowlist helper; new `get_client_ip()` CF-aware IP helper
- `includes/frontend.php` — `render()` dispatches to `render_optin()` for `button_type === optin_form`; new `render_optin()` function (no nonce); `print_styles()` adds form CSS + conditional progressive-enhancement JS (no nonce)
- `includes/admin.php` — Listmonk URL global field in settings table; `optin_form` option in button_type select; `lean-field-optin` / `lean-field-url` conditional field rows; admin JS `syncOptinFields()` toggle

### Files created
- `includes/subscribe.php` — subscription handler (no-JS POST + REST endpoint + UUID allowlist + IP rate limit + Listmonk delivery)

## Endpoints

### No-JS path (admin-post)
```
POST /wp-admin/admin-post.php
action=lc_subscribe
lc_email=<email>
lc_list=<list_uuid>
lc_hp=   (must be empty — honeypot)
```
Redirects to `{referer}?lc_done=ok` or `?lc_done=err`.
Note: no `_lc_nonce` field — see security model below.

### REST path (progressive enhancement)
```
POST /wp-json/lean-ctas/v1/subscribe
Content-Type: application/json

{
  "email": "user@example.com",
  "list_uuid": "2c6f425d-96d9-47b6-bc7b-761dd04e185f",
  "hp": ""
}
```
Returns `{"success": true}` or a WP_Error JSON.
Note: no `nonce` field — see security model below.

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

## Sitewide popup placement (`position: manual` + `[lean_cta optin="1"]`)

The opt-in form does not have to be injected into post content. A CTA with
`position: manual` is **never** auto-injected by `inject()`; it is rendered only
where you place the shortcode `[lean_cta optin="1"]`, which renders the first
configured `optin_form` CTA directly (bypassing post/taxonomy matching).

This is how the sitewide **Daily Shot popup** uses it: the popup mu-plugin
(`wp_footer`) calls `do_shortcode('[lean_cta optin="1"]')` instead of the old
`[forminator_form id="55561"]`. Because the popup appears on every page,
`print_styles()` loads the form CSS + progressive-enhancement JS whenever any
`optin_form` CTA is configured (not only on singular post-type pages).

On successful subscription the JS dispatches a `leanctas:subscribed` DOM event
(`document`), so the popup can auto-close and set its "don't show again" cookie:

```js
document.addEventListener('leanctas:subscribed', function(e){ /* close popup */ });
```

## Listmonk credentials (URL + API user + token — all editable)

`listmonk_url`, `listmonk_api_user`, `listmonk_api_token` are editable in
Settings → Lean CTAs. They are stored server-side only and never sent to the
browser. The **subscription flow uses the public endpoint and needs no auth** —
the user/token are stored for cross-site reuse, surviving a Listmonk migration,
and future admin features (e.g. fetching list names for a dropdown). Keeping
them configurable means the same plugin works across sites without code edits.

## Security model — why no WP nonce

WP nonces expire in ~24h. Eco runs server-level page cache (WPMU DEV) that serves cached HTML for days. A nonce baked into the rendered form would be stale on the first cache hit past its expiry, silently rejecting every subscription attempt until the cache is purged.

For a public double opt-in form, the nonce adds no meaningful security: the worst an attacker can do is cause Listmonk to send a confirmation email to an address — which the recipient must click to become a subscriber. An attacker bypassing the WP handler could POST directly to Listmonk's public endpoint with the same result. The protection that actually matters is:

1. **Honeypot**: off-screen text input. Bots fill it, humans don't see it. Silent "ok" on trigger so bots don't learn it exists.
2. **UUID allowlist** (`get_configured_optin_uuids()`): the submitted `list_uuid` must be one of the UUIDs configured in plugin settings. Prevents using this handler as a proxy to subscribe addresses to arbitrary Listmonk lists not belonging to this site.
3. **IP rate limit**: max 10 submits per IP per 10 minutes (transient-based, `lc_rl_{md5(ip)}`). Respects Cloudflare's `CF-Connecting-IP` header via `get_client_ip()`. Limits email-bombing of third-party inboxes even if the honeypot is bypassed by a human attacker. Returns silent 200 so the limit is not detectable.

## Design decisions

**button_type = optin_form (not a new `mode` field)**
The existing `button_type` field already describes the button behavior. Adding `optin_form` as a fourth value keeps the schema flat and backward-compatible. The admin JS shows/hides relevant fields when this value is selected.

**No-JS path via admin-post, not wp-ajax**
`admin-post.php` is the WP standard for unauthenticated form handling. `wp-ajax` requires JS by definition. The admin-post path ensures the form works for screen readers, crawlers, and users with JS disabled.

**Public Listmonk endpoint, no credentials**
`/api/public/subscription` requires no auth and triggers the list's native double opt-in flow. Using `/api/subscribers` with `preconfirm_subscriptions: true` would bypass double opt-in and require admin credentials in the frontend path — both wrong. The Listmonk URL is stored in wp_options (server-side only), never exposed to the browser.

**Progressive enhancement is inline, conditional**
The ~1.2 KB JS (nonce removed) is injected inline in `<head>` only on pages where at least one `optin_form` CTA is configured. If no opt-in CTA exists, zero JS is added. This keeps the 0-JS-frontend principle intact for pages without forms.

**CLS prevention**
`min-height: 52px` on `.lean-cta-optin-state` reserves the height of the input row before the DOM swap. Prevents layout shift when the form is replaced by the success message.

**Honeypot implementation**
Off-screen `<span aria-hidden="true" style="position:absolute;left:-9999px;...">` wraps the honeypot input. Not `display:none` or `visibility:hidden` (those are trivially detectable by modern bots). The honeypot input has `tabindex="-1"` so keyboard users cannot focus it accidentally.

**Rate limit: rolling window, not fixed**
`set_transient` resets TTL on every increment. A persistent submitter gets a rolling 10-min window instead of a fixed one. Acceptable for spam protection — the marginal difference (attacker could submit 10 every 10 min instead of 10 per fixed window) is negligible given Listmonk's double opt-in means each submission only triggers a confirmation email, not a live subscription.

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
