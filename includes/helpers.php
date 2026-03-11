<?php
/**
 * Shared helpers: defaults, getters, sanitizers.
 *
 * @package LeanCTAs
 * @since   2.0.0
 */

declare( strict_types=1 );

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
function defaults(): array {
    return [
        'enabled'                => true,
        'insert_after_paragraph' => 3,
        'post_types'             => [ 'post' ],
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
        'post_type'    => '',
        'taxonomy'     => '',
        'term_id'      => 0,
        'title'        => '',
        'text'         => '',
        'button_label' => '',
        'button_url'   => '',
        'button_type'  => 'link',
        'accent_color' => '#FF6B35',
        'position'     => 'inline',
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
    $valid_positions = [ 'inline', 'end', 'both' ];
    $clean['ctas']   = [];

    if ( ! empty( $input['ctas'] ) && is_array( $input['ctas'] ) ) {
        foreach ( $input['ctas'] as $cta ) {
            if ( ! is_array( $cta ) ) {
                continue;
            }

            $position = sanitize_key( $cta['position'] ?? 'inline' );

            $clean['ctas'][] = [
                'post_type'    => sanitize_key( $cta['post_type'] ?? '' ),
                'taxonomy'     => sanitize_key( $cta['taxonomy'] ?? '' ),
                'term_id'      => (int) ( $cta['term_id'] ?? 0 ),
                'title'        => sanitize_text_field( $cta['title'] ?? '' ),
                'text'         => sanitize_textarea_field( $cta['text'] ?? '' ),
                'button_label' => sanitize_text_field( $cta['button_label'] ?? '' ),
                'button_url'   => esc_url_raw( $cta['button_url'] ?? '' ),
                'button_type'  => sanitize_key( $cta['button_type'] ?? 'link' ),
                'accent_color' => sanitize_hex_color( $cta['accent_color'] ?? '#FF6B35' ) ?: '#FF6B35',
                'position'     => in_array( $position, $valid_positions, true ) ? $position : 'inline',
            ];
        }
    }

    return $clean;
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
