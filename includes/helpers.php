<?php

declare( strict_types=1 );
/**
 * Shared helpers: defaults, getters, sanitizers.
 *
 * @package LeanCTAs
 * @since   2.0.0
 */


namespace LeanCTAs\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use const LeanCTAs\OPTION_KEY;

/**
 * Default plugin settings.
 *
 * @return array<string, mixed>
 */
const DEFAULT_COLOR = '#FF6B35';

function defaults(): array {
    return [
        'enabled'                => true,
        'insert_after_paragraph' => 3,
        'default_color'          => DEFAULT_COLOR,
        'post_types'             => [ 'post' ],
        'listmonk_url'           => '',
        'listmonk_api_user'      => '',
        'listmonk_api_token'     => '',
        'ctas'                   => [],
    ];
}

/**
 * Retrieve current settings merged with defaults.
 *
 * @return array<string, mixed>
 */
function get_plugin_settings(): array {
    return wp_parse_args(
        get_option( OPTION_KEY, [] ),
        defaults()
    );
}

/**
 * Default values for a single CTA entry.
 *
 * @return array<string, mixed>
 */
function cta_defaults(): array {
    return [
        'post_type'            => '',
        'taxonomy'             => '',
        'term_id'              => 0,
        'title'                => '',
        'text'                 => '',
        'button_label'         => '',
        'button_url'           => '',
        'button_type'          => 'link',
        'accent_color'         => '',
        'position'             => 'inline',
        // Opt-in form fields (only used when button_type = 'optin_form').
        'optin_list_uuid'      => '',
        'optin_success_msg'    => '',
    ];
}

/**
 * Sanitize the full settings array before save.
 *
 * @param mixed $input Raw form input.
 * @return array<string, mixed>
 */
function sanitize( mixed $input ): array {
    $clean = defaults();

    if ( ! is_array( $input ) ) {
        return $clean;
    }

    $clean['enabled']                = ! empty( $input['enabled'] );
    $clean['insert_after_paragraph'] = max( 1, (int) ( $input['insert_after_paragraph'] ?? 3 ) );
    $clean['default_color']          = sanitize_hex_color( $input['default_color'] ?? DEFAULT_COLOR ) ?: DEFAULT_COLOR;
    $clean['listmonk_url']           = esc_url_raw( rtrim( $input['listmonk_url'] ?? '', '/' ) );
    $clean['listmonk_api_user']      = sanitize_text_field( $input['listmonk_api_user'] ?? '' );
    $clean['listmonk_api_token']     = sanitize_text_field( $input['listmonk_api_token'] ?? '' );

    // Post types — accept only registered public types.
    $clean['post_types'] = [];
    if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
        $public_types = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $input['post_types'] as $pt ) {
            $pt = sanitize_key( $pt );
            if ( isset( $public_types[ $pt ] ) ) {
                $clean['post_types'][] = $pt;
            }
        }
    }
    if ( empty( $clean['post_types'] ) ) {
        $clean['post_types'] = [ 'post' ];
    }

    // CTAs.
    $valid_positions = [ 'inline', 'end', 'both', 'manual' ];
    $clean['ctas']   = [];

    if ( ! empty( $input['ctas'] ) && is_array( $input['ctas'] ) ) {
        foreach ( $input['ctas'] as $cta ) {
            if ( ! is_array( $cta ) ) {
                continue;
            }

            $position = sanitize_key( $cta['position'] ?? 'inline' );

            $button_type        = sanitize_key( $cta['button_type'] ?? 'link' );
            $valid_button_types = [ 'link', 'newsletter', 'community', 'optin_form' ];
            if ( ! in_array( $button_type, $valid_button_types, true ) ) {
                $button_type = 'link';
            }

            $clean['ctas'][] = [
                'post_type'         => sanitize_key( $cta['post_type'] ?? '' ),
                'taxonomy'          => sanitize_key( $cta['taxonomy'] ?? '' ),
                'term_id'           => (int) ( $cta['term_id'] ?? 0 ),
                'title'             => sanitize_text_field( $cta['title'] ?? '' ),
                'text'              => sanitize_textarea_field( $cta['text'] ?? '' ),
                'button_label'      => sanitize_text_field( $cta['button_label'] ?? '' ),
                'button_url'        => esc_url_raw( $cta['button_url'] ?? '' ),
                'button_type'       => $button_type,
                'accent_color'      => sanitize_hex_color( $cta['accent_color'] ?? '' ) ?: '',
                'position'          => in_array( $position, $valid_positions, true ) ? $position : 'inline',
                // Opt-in form fields.
                'optin_list_uuid'   => sanitize_text_field( $cta['optin_list_uuid'] ?? '' ),
                'optin_success_msg' => sanitize_text_field( $cta['optin_success_msg'] ?? '' ),
            ];
        }
    }

    return $clean;
}

/**
 * Return the list of optin_list_uuid values configured across all CTAs.
 *
 * Used as an allowlist: any UUID submitted via the opt-in form must be in
 * this set, otherwise it is rejected. Prevents the handler from being used
 * as a proxy to subscribe addresses to arbitrary Listmonk lists.
 *
 * @return string[]
 */
function get_configured_optin_uuids(): array {
    $settings = get_plugin_settings();
    $uuids    = [];

    foreach ( $settings['ctas'] as $cta ) {
        if (
            ( $cta['button_type'] ?? '' ) === 'optin_form'
            && ! empty( $cta['optin_list_uuid'] )
        ) {
            $uuids[] = $cta['optin_list_uuid'];
        }
    }

    return array_unique( $uuids );
}

/**
 * Get the real client IP, respecting Cloudflare's CF-Connecting-IP header.
 *
 * Only trusts CF-Connecting-IP when REMOTE_ADDR looks like a CF edge IP
 * (i.e. not localhost). In staging/non-CF environments falls back to REMOTE_ADDR.
 *
 * @return string Sanitized IP string (empty string if not determinable).
 */
function get_client_ip(): string {
    // Prefer CF-Connecting-IP when the connection comes through Cloudflare.
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
    }

    return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
}

/**
 * Get all public taxonomies with their terms, grouped for the admin selector.
 *
 * @return array<string, array{label: string, terms: \WP_Term[]}>
 */
function get_taxonomy_options(): array {
    $options    = [];
    $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

    foreach ( $taxonomies as $tax ) {
        $terms = get_terms( [
            'taxonomy'   => $tax->name,
            'hide_empty' => false,
            'number'     => 200,
        ] );

        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $options[ $tax->name ] = [
                'label' => $tax->labels->singular_name,
                'terms' => $terms,
            ];
        }
    }

    return $options;
}
