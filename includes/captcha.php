<?php

declare( strict_types=1 );
/**
 * Cloudflare Turnstile challenge — anti-bot gate for opt-in submissions.
 *
 * WHY TURNSTILE, NOT reCAPTCHA:
 *   1. The site already sits behind Cloudflare (CLOUDFLARE_API_TOKEN in
 *      Infisical, DNS + proxy managed there) — Turnstile is a natural fit,
 *      zero new vendor relationship.
 *   2. Free with no request cap (reCAPTCHA v3 is also free today, but scored
 *      requests are capped and Google can gate/charge later — see
 *      "reCAPTCHA Enterprise" migration precedent).
 *   3. No visitor data sent to Google. reCAPTCHA sets third-party cookies and
 *      fingerprints the browser for Google's ad profile; Turnstile does not
 *      phone home to an ad network. Matches the "no telemetry" plugin family
 *      rule even though this specific check is unavoidably 3rd-party.
 *   4. Invisible by default (non-interactive managed challenge) — same UX
 *      promise reCAPTCHA v3 makes, without the Google trade-off.
 *   5. ~28 KB async script vs reCAPTCHA's heavier badge/bundle. Closer to the
 *      "zero JS on frontend" spirit — it's the smallest tax available for a
 *      real bot gate on a public unauthenticated endpoint.
 *
 * NOT built as a multi-provider switch: today there is exactly one provider.
 * A second one (hCaptcha, reCAPTCHA) would only touch verify() and
 * widget_html() below — both have a narrow, documented contract — so the
 * cost of adding one later is small. Building the switch now for a single
 * option would be dead code, which the lean-* rules explicitly reject.
 *
 * FAIL-CLOSED CONTRACT:
 *   is_configured() === false  → caller must treat the submission as if no
 *                                 captcha exists (today's behaviour, unchanged).
 *   is_configured() === true   → caller MUST call verify() and reject the
 *                                 submission on anything but a hard `true`.
 *
 * @package LeanCTAs
 * @since   2.7.0
 */


namespace LeanCTAs\Captcha;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use function LeanCTAs\Helpers\get_plugin_settings;

const VERIFY_URL   = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const WIDGET_JS    = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
/** Name of the hidden input Turnstile's script injects into the form. */
const FIELD_NAME   = 'cf-turnstile-response';

/**
 * Whether both Turnstile keys are configured. Gate for every other function
 * here — when false, the rest of the plugin must behave exactly as before
 * this feature existed.
 */
function is_configured(): bool {
    $settings = get_plugin_settings();
    return ! empty( $settings['captcha_site_key'] ) && ! empty( $settings['captcha_secret_key'] );
}

/**
 * Render the Turnstile widget div. Cloudflare's api.js scans the DOM for
 * `.cf-turnstile` and auto-injects the hidden `cf-turnstile-response` input
 * inside it — no manual wiring needed for the no-JS-enhanced (plain POST) path.
 *
 * Returns '' when not configured, so callers can unconditionally echo it.
 */
function widget_html(): string {
    if ( ! is_configured() ) {
        return '';
    }

    $settings = get_plugin_settings();

    // appearance: se usa el valor por defecto ("always") a propósito.
    // Con "interaction-only" el widget se queda esperando una interacción que
    // el visitante nunca hace, el campo cf-turnstile-response llega vacío y el
    // backend —que falla cerrado— rechaza altas legítimas. Verificado el 14-ago-2026:
    // 30s sin token y HTTP 400 en un envío real. Preferimos un widget visible
    // antes que perder suscriptores.
    return '<div class="cf-turnstile" data-sitekey="' . esc_attr( $settings['captcha_site_key'] )
        . '" data-theme="auto"></div>';
}

/**
 * <script> tag for Turnstile's API. Only print when an opt-in form that
 * needs it is on the page (caller decides when — see frontend.php).
 */
function script_tag(): string {
    if ( ! is_configured() ) {
        return '';
    }

    return '<script src="' . esc_url( WIDGET_JS ) . '" async defer></script>';
}

/**
 * Verify a Turnstile token against Cloudflare's siteverify endpoint.
 *
 * Fails closed: any missing token, network error, or non-success response
 * returns false. Callers must treat false as "reject the submission".
 *
 * @param string $token Value of the `cf-turnstile-response` field.
 * @param string $ip    Visitor IP (see Helpers\get_client_ip()).
 */
function verify( string $token, string $ip ): bool {
    if ( '' === trim( $token ) ) {
        return false;
    }

    $settings = get_plugin_settings();
    $secret   = (string) ( $settings['captcha_secret_key'] ?? '' );

    if ( '' === $secret ) {
        return false;
    }

    $response = wp_remote_post(
        VERIFY_URL,
        [
            'body'    => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ],
            'timeout' => 5,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    return ! empty( $body['success'] );
}
