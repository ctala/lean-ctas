<?php
/**
 * Frontend: content injection, rendering, shortcode, styles.
 *
 * @package LeanCTAs
 * @since   2.0.0
 */


namespace LeanCTAs\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use function LeanCTAs\Helpers\get_plugin_settings;

add_filter( 'the_content', __NAMESPACE__ . '\\inject', 20 );
add_action( 'wp_head', __NAMESPACE__ . '\\print_styles' );
add_shortcode( 'lean_cta', __NAMESPACE__ . '\\shortcode' );
// Legacy shortcode compat.
add_shortcode( 'eco_cta', __NAMESPACE__ . '\\shortcode' );

/* ─────────────────────────────────────────────
   Content injection
───────────────────────────────────────────── */

/**
 * Inject the matching CTA into the post content.
 *
 * Runs at priority 20 to let shortcodes and embeds process first.
 */
function inject( string $content ): string {
    if ( is_admin() || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $settings = get_plugin_settings();

    if ( empty( $settings['enabled'] ) || empty( $settings['ctas'] ) ) {
        return $content;
    }

    $current_type = (string) get_post_type();

    if ( ! in_array( $current_type, $settings['post_types'], true ) ) {
        return $content;
    }

    if ( ! is_singular( $settings['post_types'] ) ) {
        return $content;
    }

    $cta = match_cta( $settings['ctas'], $current_type );

    if ( null === $cta ) {
        return $content;
    }

    $html     = render( $cta );
    $position = $cta['position'] ?? 'inline';

    return match ( $position ) {
        'inline' => insert_after_paragraph( $content, $html, $settings['insert_after_paragraph'] ),
        'end'    => $content . $html,
        'both'   => insert_after_paragraph( $content, $html, $settings['insert_after_paragraph'] ) . $html,
        default  => $content . $html,
    };
}

/* ─────────────────────────────────────────────
   Matching engine
───────────────────────────────────────────── */

/**
 * Find the best matching CTA using priority:
 *   1. post_type + taxonomy term  (most specific)
 *   2. taxonomy term only
 *   3. post_type only
 *   4. global fallback  (no filters)
 *
 * @param array<int, array<string, mixed>> $ctas         Configured CTAs.
 * @param string                           $current_type Current post type slug.
 * @return array<string, mixed>|null
 */
function match_cta( array $ctas, string $current_type ): ?array {
    $post_terms = get_post_terms_indexed( (int) get_the_ID() );

    // Priority 1 — post_type + taxonomy term.
    foreach ( $ctas as $cta ) {
        if (
            ! empty( $cta['post_type'] ) && $cta['post_type'] === $current_type
            && ! empty( $cta['taxonomy'] ) && ! empty( $cta['term_id'] )
            && has_term( $post_terms, $cta['taxonomy'], (int) $cta['term_id'] )
        ) {
            return $cta;
        }
    }

    // Priority 2 — taxonomy term only.
    foreach ( $ctas as $cta ) {
        if (
            empty( $cta['post_type'] )
            && ! empty( $cta['taxonomy'] ) && ! empty( $cta['term_id'] )
            && has_term( $post_terms, $cta['taxonomy'], (int) $cta['term_id'] )
        ) {
            return $cta;
        }
    }

    // Priority 3 — post_type only.
    foreach ( $ctas as $cta ) {
        if (
            ! empty( $cta['post_type'] ) && $cta['post_type'] === $current_type
            && empty( $cta['taxonomy'] ) && empty( $cta['term_id'] )
        ) {
            return $cta;
        }
    }

    // Priority 4 — global fallback.
    foreach ( $ctas as $cta ) {
        if ( empty( $cta['post_type'] ) && empty( $cta['taxonomy'] ) && empty( $cta['term_id'] ) ) {
            return $cta;
        }
    }

    return null;
}

/**
 * Build an indexed array of term IDs keyed by taxonomy for the given post.
 *
 * @param int $post_id Post ID.
 * @return array<string, int[]>
 */
function get_post_terms_indexed( int $post_id ): array {
    $result     = [];
    $taxonomies = get_object_taxonomies( (string) get_post_type( $post_id ), 'names' );

    foreach ( $taxonomies as $tax ) {
        $terms = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'ids' ] );
        if ( ! is_wp_error( $terms ) ) {
            $result[ $tax ] = $terms;
        }
    }

    return $result;
}

/**
 * Check whether the indexed terms contain a specific taxonomy:term_id pair.
 *
 * @param array<string, int[]> $post_terms Indexed terms.
 * @param string               $taxonomy   Taxonomy slug.
 * @param int                  $term_id    Term ID.
 */
function has_term( array $post_terms, string $taxonomy, int $term_id ): bool {
    return isset( $post_terms[ $taxonomy ] ) && in_array( $term_id, $post_terms[ $taxonomy ], true );
}

/* ─────────────────────────────────────────────
   Paragraph insertion
───────────────────────────────────────────── */

/**
 * Insert HTML after the Nth closing </p> tag.
 *
 * @param string $content   Post HTML.
 * @param string $injection CTA HTML.
 * @param int    $after     Paragraph number (1-based).
 */
function insert_after_paragraph( string $content, string $injection, int $after ): string {
    $parts = explode( '</p>', $content );
    $total = count( $parts );

    if ( $total <= 1 ) {
        return $content . $injection;
    }

    $insert_at = min( $after, $total - 1 );
    $output    = '';

    for ( $i = 0; $i < $total; $i++ ) {
        $output .= $parts[ $i ];

        if ( $i < $total - 1 ) {
            $output .= '</p>';
        }

        if ( $i === $insert_at - 1 ) {
            $output .= $injection;
        }
    }

    return $output;
}

/* ─────────────────────────────────────────────
   Render CTA block
───────────────────────────────────────────── */

/**
 * Render a single CTA block.
 *
 * @param array<string, mixed> $cta CTA data.
 * @return string HTML.
 */
function render( array $cta ): string {
    $settings = get_plugin_settings();
    $accent   = esc_attr( $cta['accent_color'] ?: ( $settings['default_color'] ?? '#FF6B35' ) );
    $title  = esc_html( $cta['title'] ?? '' );
    $text   = esc_html( $cta['text'] ?? '' );
    $label  = esc_html( $cta['button_label'] ?: __( 'Learn more', 'lean-ctas' ) );
    $url    = esc_url( $cta['button_url'] ?? '#' );

    $icon = match ( $cta['button_type'] ?? 'link' ) {
        'newsletter' => '📧',
        'community'  => '👥',
        default      => '→',
    };

    $html = '<div class="lean-cta-block" style="--lean-accent:' . $accent . '">';

    if ( $title ) {
        $html .= '<p class="lean-cta-title">' . $title . '</p>';
    }
    if ( $text ) {
        $html .= '<p class="lean-cta-text">' . $text . '</p>';
    }

    $html .= '<a class="lean-cta-btn" href="' . $url . '" target="_blank" rel="noopener">'
           . $icon . ' ' . $label
           . '</a></div>';

    return $html;
}

/* ─────────────────────────────────────────────
   Frontend styles
───────────────────────────────────────────── */

function print_styles(): void {
    $settings = get_plugin_settings();

    if ( empty( $settings['enabled'] ) || empty( $settings['ctas'] ) ) {
        return;
    }

    if ( ! is_singular( $settings['post_types'] ) ) {
        return;
    }

    ?>
    <style id="lean-cta-styles">
    .lean-cta-block{--lean-accent:<?php echo esc_attr( $settings['default_color'] ?? '#FF6B35' ); ?>;border-left:4px solid var(--lean-accent);background:#f9f9f9;padding:16px 20px;margin:28px 0;border-radius:0 6px 6px 0;font-family:inherit}
    .lean-cta-title{font-weight:700;font-size:1.05em;margin:0 0 6px;color:#111}
    .lean-cta-text{margin:0 0 12px;color:#444;font-size:.95em;line-height:1.5}
    .lean-cta-btn{display:inline-block;background:var(--lean-accent);color:#fff!important;padding:8px 18px;border-radius:4px;text-decoration:none!important;font-weight:600;font-size:.9em;transition:opacity .2s}
    .lean-cta-btn:hover{opacity:.85}
    @media(max-width:600px){.lean-cta-btn{display:block;text-align:center}}
    </style>
    <?php
}

/* ─────────────────────────────────────────────
   Shortcode  [lean_cta] or [eco_cta] (legacy)
───────────────────────────────────────────── */

/**
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML.
 */
function shortcode( $atts ): string {
    $atts = shortcode_atts( [
        'post_type' => '',
        'taxonomy'  => '',
        'term'      => 0,
        'category'  => 0,
    ], $atts, 'lean_cta' );

    $settings = get_plugin_settings();

    if ( empty( $settings['ctas'] ) ) {
        return '';
    }

    // Legacy: category="X" → taxonomy=category, term=X.
    if ( ! empty( $atts['category'] ) && empty( $atts['taxonomy'] ) ) {
        $atts['taxonomy'] = 'category';
        $atts['term']     = (int) $atts['category'];
    }

    $target_pt   = sanitize_key( $atts['post_type'] );
    $target_tax  = sanitize_key( $atts['taxonomy'] );
    $target_term = (int) $atts['term'];

    foreach ( $settings['ctas'] as $c ) {
        $match_pt  = empty( $target_pt ) || ( ! empty( $c['post_type'] ) && $c['post_type'] === $target_pt );
        $match_tax = empty( $target_tax ) || (
            ! empty( $c['taxonomy'] ) && $c['taxonomy'] === $target_tax
            && (int) $c['term_id'] === $target_term
        );

        if ( $match_pt && $match_tax ) {
            return render( $c );
        }
    }

    // Fallback global.
    foreach ( $settings['ctas'] as $c ) {
        if ( empty( $c['post_type'] ) && empty( $c['taxonomy'] ) && empty( $c['term_id'] ) ) {
            return render( $c );
        }
    }

    return '';
}
