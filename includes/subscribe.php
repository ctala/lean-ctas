<?php

declare( strict_types=1 );
/**
 * Opt-in form subscription handler.
 *
 * Handles both:
 *   - No-JS path: admin_post_nopriv_lc_subscribe  (form POST → redirect)
 *   - JS path:    REST POST /lean-ctas/v1/subscribe (fetch → JSON)
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

/* ─────────────────────────────────────────────
   Hooks
───────────────────────────────────────────── */

// No-JS fallback: standard admin-post action (works without JS).
add_action( 'admin_post_nopriv_lc_subscribe', __NAMESPACE__ . '\\handle_post' );
// Also handle for logged-in users (edge case: logged-in visitor uses the form).
add_action( 'admin_post_lc_subscribe', __NAMESPACE__ . '\\handle_post' );

// REST endpoint for progressive-enhancement JS path.
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_rest_route' );

/* ─────────────────────────────────────────────
   No-JS handler
───────────────────────────────────────────── */

/**
 * Handle the no-JS POST form submission.
 * Verifies nonce, honeypot, email; sends to Listmonk; redirects back.
 */
function handle_post(): void {
    // Nonce check.
    if ( ! isset( $_POST['_lc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_lc_nonce'] ) ), 'lc_subscribe' ) ) {
        redirect_back( 'err' );
        return;
    }

    // Honeypot: reject if filled.
    if ( ! empty( $_POST['lc_hp'] ) ) {
        // Silent success so bots don't learn the field matters.
        redirect_back( 'ok' );
        return;
    }

    $email    = sanitize_email( wp_unslash( $_POST['lc_email'] ?? '' ) );
    $list_uuid = sanitize_text_field( wp_unslash( $_POST['lc_list'] ?? '' ) );
    $referer  = wp_get_referer() ?: home_url();

    if ( ! is_email( $email ) || empty( $list_uuid ) ) {
        redirect_back( 'err', $referer );
        return;
    }

    $result = send_to_listmonk( $email, $list_uuid );

    redirect_back( is_wp_error( $result ) ? 'err' : 'ok', $referer );
}

/**
 * Redirect back to referring page with a status query arg.
 *
 * @param string $status 'ok' | 'err'.
 * @param string $url    Target URL (defaults to referer).
 */
function redirect_back( string $status, string $url = '' ): void {
    if ( empty( $url ) ) {
        $url = wp_get_referer() ?: home_url();
    }
    $url = add_query_arg( 'lc_done', $status, $url );
    // Remove the fragment so the browser scrolls to top, not to an anchor.
    wp_safe_redirect( $url );
    exit;
}

/* ─────────────────────────────────────────────
   REST endpoint
───────────────────────────────────────────── */

/**
 * Register the REST route for the JS-enhanced path.
 */
function register_rest_route(): void {
    register_rest_route(
        'lean-ctas/v1',
        '/subscribe',
        [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => __NAMESPACE__ . '\\rest_subscribe',
            'permission_callback' => '__return_true', // Public endpoint — auth is via nonce in body.
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
                'nonce' => [
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
    // Nonce check (wp_rest nonce from wp_create_nonce('wp_rest')).
    if ( ! wp_verify_nonce( $request->get_param( 'nonce' ), 'lc_subscribe' ) ) {
        return new \WP_Error( 'invalid_nonce', __( 'Security check failed.', 'lean-ctas' ), [ 'status' => 403 ] );
    }

    // Honeypot.
    if ( ! empty( $request->get_param( 'hp' ) ) ) {
        // Return 200 so bots don't learn the field matters.
        return rest_ensure_response( [ 'success' => true ] );
    }

    $email     = $request->get_param( 'email' );
    $list_uuid = $request->get_param( 'list_uuid' );

    $result = send_to_listmonk( $email, $list_uuid );

    if ( is_wp_error( $result ) ) {
        return new \WP_Error( 'subscribe_error', $result->get_error_message(), [ 'status' => 500 ] );
    }

    return rest_ensure_response( [ 'success' => true ] );
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
 * @param string $email     Subscriber email.
 * @param string $list_uuid Listmonk list UUID.
 * @return true|\WP_Error
 */
function send_to_listmonk( string $email, string $list_uuid ): true|\WP_Error {
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
                'list_uuids' => [ $list_uuid ],
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
