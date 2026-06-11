<?php

declare( strict_types=1 );
/**
 * Opt-in form subscription handler.
 *
 * Handles both:
 *   - No-JS path: admin_post_nopriv_lc_subscribe  (form POST → redirect)
 *   - JS path:    REST POST /lean-ctas/v1/subscribe (fetch → JSON)
 *
 * Security model (no WP nonce — cache-safe design):
 *   WP nonces expire in ~24h. Eco runs server-level page cache (WPMU DEV)
 *   that can serve pages for days. A nonce baked into cached HTML would be
 *   stale on the first cache hit after expiry, silently breaking every
 *   subscription. For a public double opt-in form this tradeoff is wrong:
 *   the nonce adds no meaningful security (an attacker can POST directly to
 *   Listmonk's public endpoint anyway; the worst outcome is a confirmation
 *   email the recipient never clicks), while the breakage is total and silent.
 *
 *   Protection comes from three layers instead:
 *     1. Honeypot field (off-screen, bots fill it, humans don't).
 *     2. UUID allowlist: submitted list_uuid must be one configured in
 *        Settings → Lean CTAs. Prevents using this handler to subscribe
 *        addresses to arbitrary Listmonk lists.
 *     3. IP rate limit: max 10 submits per IP per 10 min (transient).
 *        Limits email-bombing of third-party inboxes even if honeypot is bypassed.
 *
 * Listmonk delivery uses the PUBLIC endpoint (no auth required):
 *   POST {listmonk_url}/api/public/subscription
 *   {"email":"…","list_uuids":["…"]}
 *
 * This deliberately triggers Listmonk's double opt-in flow.
 * Do NOT switch to /api/subscribers with preconfirm_subscriptions — that
 * bypasses double opt-in, which is the opposite of what we want.
 *
 * @package LeanCTAs
 * @since   2.4.0
 */


namespace LeanCTAs\Subscribe;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use function LeanCTAs\Helpers\get_plugin_settings;
use function LeanCTAs\Helpers\get_configured_optin_uuids;
use function LeanCTAs\Helpers\parse_uuid_list;
use function LeanCTAs\Helpers\get_ga_ids;
use function LeanCTAs\Helpers\get_client_ip;

/* ─────────────────────────────────────────────
   Constants
───────────────────────────────────────────── */

/** Max submissions per IP within RATE_WINDOW seconds. */
const RATE_LIMIT  = 10;
/** Rate-limit window in seconds (10 minutes). */
const RATE_WINDOW = 600;

/* ─────────────────────────────────────────────
   Hooks
───────────────────────────────────────────── */

// No-JS fallback: standard admin-post action (works without JS).
add_action( 'admin_post_nopriv_lc_subscribe', __NAMESPACE__ . '\\handle_post' );
// Also handle for logged-in users (edge case: logged-in visitor uses the form).
add_action( 'admin_post_lc_subscribe', __NAMESPACE__ . '\\handle_post' );

// REST endpoint for progressive-enhancement JS path.
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );

/* ─────────────────────────────────────────────
   No-JS handler
───────────────────────────────────────────── */

/**
 * Handle the no-JS POST form submission.
 * Checks honeypot, allowlist, rate limit, email; sends to Listmonk; redirects back.
 */
function handle_post(): void {
    $referer = wp_get_referer() ?: home_url();

    // Honeypot: reject if filled. Silent success so bots don't learn the field matters.
    if ( ! empty( $_POST['lc_hp'] ) ) {
        redirect_back( 'ok', $referer );
        return;
    }

    $email = sanitize_email( wp_unslash( $_POST['lc_email'] ?? '' ) );
    // lc_list may carry one or several comma-separated list UUIDs.
    $uuids = parse_uuid_list( sanitize_text_field( wp_unslash( $_POST['lc_list'] ?? '' ) ) );

    if ( ! is_email( $email ) || empty( $uuids ) ) {
        redirect_back( 'err', $referer );
        return;
    }

    // UUID allowlist: every submitted UUID must be configured in plugin settings.
    $allowed = get_configured_optin_uuids();
    foreach ( $uuids as $u ) {
        if ( ! in_array( $u, $allowed, true ) ) {
            redirect_back( 'err', $referer );
            return;
        }
    }

    // Rate limit by IP.
    if ( is_rate_limited() ) {
        // Silent success — don't reveal the limit to scrapers.
        redirect_back( 'ok', $referer );
        return;
    }

    $result = deliver_capture( $email, $uuids, $referer );

    redirect_back( is_wp_error( $result ) ? 'err' : 'ok', $referer );
}

/**
 * Redirect back to referring page with a status query arg.
 *
 * @param string $status 'ok' | 'err'.
 * @param string $url    Target URL.
 */
function redirect_back( string $status, string $url ): void {
    $url = add_query_arg( 'lc_done', $status, $url );
    wp_safe_redirect( $url );
    exit;
}

/* ─────────────────────────────────────────────
   REST endpoint
───────────────────────────────────────────── */

/**
 * Register the REST route for the JS-enhanced path.
 *
 * NOTE: named register_routes() (not register_rest_route) to avoid colliding
 * with WP's global register_rest_route(); inside a namespace an unqualified
 * call would resolve to this function and recurse infinitely. The WP call
 * below is fully qualified with a leading backslash for the same reason.
 */
function register_routes(): void {
    \register_rest_route(
        'lean-ctas/v1',
        '/subscribe',
        [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => __NAMESPACE__ . '\\rest_subscribe',
            'permission_callback' => '__return_true', // Public endpoint — protected by allowlist + rate limit.
            'args'                => [
                'email'     => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => 'is_email',
                ],
                'list_uuid' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'hp' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page_url' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]
    );
}

/**
 * REST subscription callback.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_subscribe( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
    // Honeypot.
    if ( ! empty( $request->get_param( 'hp' ) ) ) {
        return rest_ensure_response( [ 'success' => true ] );
    }

    $email = $request->get_param( 'email' );
    // list_uuid may carry one or several comma-separated list UUIDs.
    $uuids = parse_uuid_list( (string) $request->get_param( 'list_uuid' ) );

    if ( empty( $uuids ) ) {
        return new \WP_Error( 'invalid_list', __( 'Invalid list.', 'lean-ctas' ), [ 'status' => 400 ] );
    }

    // UUID allowlist: every submitted UUID must be configured.
    $allowed = get_configured_optin_uuids();
    foreach ( $uuids as $u ) {
        if ( ! in_array( $u, $allowed, true ) ) {
            return new \WP_Error( 'invalid_list', __( 'Invalid list.', 'lean-ctas' ), [ 'status' => 400 ] );
        }
    }

    // Rate limit by IP. Silent 200 — don't signal the limit to scrapers.
    if ( is_rate_limited() ) {
        return rest_ensure_response( [ 'success' => true ] );
    }

    $page_url = esc_url_raw( (string) $request->get_param( 'page_url' ) );
    $result   = deliver_capture( $email, $uuids, $page_url );

    if ( is_wp_error( $result ) ) {
        return new \WP_Error( 'subscribe_error', $result->get_error_message(), [ 'status' => 500 ] );
    }

    return rest_ensure_response( [ 'success' => true ] );
}

/* ─────────────────────────────────────────────
   Rate limiting
───────────────────────────────────────────── */

/**
 * Check (and increment) the per-IP submission counter.
 *
 * Uses a single transient per IP. On first call: sets counter=1, expiry=RATE_WINDOW.
 * Subsequent calls within the window: increments in-place.
 * Returns true if the IP has exceeded RATE_LIMIT within the current window.
 *
 * Transient key: lc_rl_{md5(ip)} — md5 to keep key length safe and avoid
 * special chars from IPv6 addresses in option names.
 *
 * @return bool True = rate limited (should be blocked).
 */
function is_rate_limited(): bool {
    $ip  = get_client_ip();
    $key = 'lc_rl_' . md5( $ip );

    $count = (int) get_transient( $key );

    if ( $count >= RATE_LIMIT ) {
        return true;
    }

    // Increment. On first hit, set_transient creates with RATE_WINDOW expiry.
    // On subsequent hits within the window, get_transient already returned the
    // existing value — we overwrite with count+1, preserving expiry via
    // delete+set (WP transients reset TTL on set, so we calculate remaining time).
    // Simpler approach: always set with full window on each increment.
    // This slightly extends the window on repeated hits, but for a spam
    // protection use-case that is acceptable — the attacker just gets
    // a rolling 10-min window, not a fixed one.
    set_transient( $key, $count + 1, RATE_WINDOW );

    return false;
}

/* ─────────────────────────────────────────────
   Delivery router: capture webhook (n8n) con fallback a Listmonk directo
───────────────────────────────────────────── */

/**
 * Deliver the capture. If a capture webhook (n8n) is configured, POST there —
 * the workflow handles Listmonk + GA4 Measurement Protocol + future plumbing
 * (CRM/tagging). On webhook failure (non-2xx / timeout), falls back to the
 * direct Listmonk path so a capture is never lost.
 *
 * @param string        $email    Subscriber email.
 * @param array<string> $uuids    Listmonk list UUIDs.
 * @param string        $page_url Page where the form was submitted (for GA4/tagging).
 * @return true|\WP_Error
 */
function deliver_capture( string $email, array $uuids, string $page_url = '' ): true|\WP_Error {
    $settings    = get_plugin_settings();
    $webhook_url = $settings['capture_webhook_url'] ?? '';

    if ( ! empty( $webhook_url ) ) {
        $ga = get_ga_ids();

        $response = wp_remote_post(
            $webhook_url,
            [
                'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
                'body'    => wp_json_encode( [
                    'email'      => $email,
                    'list_uuids' => array_values( $uuids ),
                    'page_url'   => $page_url,
                    'client_id'  => $ga['client_id'],
                    'session_id' => $ga['session_id'],
                ] ),
                'timeout' => 4,
            ]
        );

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 300 ) {
                return true;
            }
        }
        // Fall through: webhook caído/lento → entrega directa a Listmonk (sin GA4).
    }

    return send_to_listmonk( $email, $uuids );
}

/* ─────────────────────────────────────────────
   Listmonk delivery
───────────────────────────────────────────── */

/**
 * POST to Listmonk's public subscription endpoint (no auth required).
 *
 * Uses /api/public/subscription which respects the list's opt-in setting
 * (double opt-in if configured). This is intentional.
 *
 * @param string        $email Subscriber email.
 * @param array<string> $uuids One or more Listmonk list UUIDs.
 * @return true|\WP_Error
 */
function send_to_listmonk( string $email, array $uuids ): true|\WP_Error {
    $settings     = get_plugin_settings();
    $listmonk_url = $settings['listmonk_url'] ?? '';

    if ( empty( $listmonk_url ) ) {
        return new \WP_Error( 'no_listmonk_url', __( 'Listmonk URL not configured.', 'lean-ctas' ) );
    }

    $endpoint = trailingslashit( $listmonk_url ) . 'api/public/subscription';

    $response = wp_remote_post(
        $endpoint,
        [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body'    => wp_json_encode( [
                'email'      => $email,
                'list_uuids' => array_values( $uuids ),
            ] ),
            'timeout' => 8,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );

    // Listmonk public endpoint returns 200 on success.
    // It may return 400 for already-subscribed (double opt-in resends confirm email).
    // Treat both as success from the user's perspective.
    if ( $code >= 200 && $code < 500 ) {
        return true;
    }

    $body    = json_decode( wp_remote_retrieve_body( $response ), true );
    $message = $body['message'] ?? sprintf( __( 'Listmonk returned HTTP %d.', 'lean-ctas' ), $code );

    return new \WP_Error( 'listmonk_error', $message );
}
