<?php

declare( strict_types=1 );
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

// Priority 1001: must run AFTER leanautolinks (priority 999) which replaces
// content with its pre-cached version. Running before it would lose the CTA.
add_filter( 'the_content', __NAMESPACE__ . '\\inject', 1001 );
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
 * Runs at priority 1001, after leanautolinks (999) replaces content with
 * its pre-cached version. Running earlier would lose the injected CTA.
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

    $position = $cta['position'] ?? 'inline';

    // 'manual' CTAs are shortcode-only (e.g. rendered inside the sitewide
    // popup via [lean_cta optin="1"]) — never auto-injected into content.
    if ( 'manual' === $position ) {
        return $content;
    }

    $html = render( $cta );

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
    if ( ( $cta['button_type'] ?? 'link' ) === 'optin_form' ) {
        return render_optin( $cta );
    }

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

/**
 * Render an opt-in form CTA block.
 *
 * Server-rendered, 0 external dependencies. Works via POST (no-JS path).
 * Progressive enhancement handled by inline JS in print_styles().
 *
 * Height of the state area is reserved via min-height to prevent CLS.
 *
 * @param array<string, mixed> $cta CTA data.
 * @return string HTML.
 */
function render_optin( array $cta ): string {
    $settings    = get_plugin_settings();
    $accent      = esc_attr( $cta['accent_color'] ?: ( $settings['default_color'] ?? '#FF6B35' ) );
    $title       = esc_html( $cta['title'] ?? '' );
    $text        = esc_html( $cta['text'] ?? '' );
    $label       = esc_html( $cta['button_label'] ?: __( 'Subscribe', 'lean-ctas' ) );
    $list_uuid   = esc_attr( $cta['optin_list_uuid'] ?? '' );
    $success_msg = esc_html( $cta['optin_success_msg'] ?: __( 'Check your email to confirm your subscription.', 'lean-ctas' ) );

    // Detect state from query arg (no-JS path redirect result).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no data change.
    $done = isset( $_GET['lc_done'] ) ? sanitize_key( $_GET['lc_done'] ) : '';

    // No WP nonce: eco runs server-level page cache (WPMU DEV) that can serve
    // pages for days. A nonce baked into cached HTML expires in ~24h, silently
    // breaking every subscription attempt on stale cache. For a public double
    // opt-in form the nonce adds no real security (attacker can POST to
    // Listmonk's public endpoint directly regardless). Protection is provided
    // by the UUID allowlist + honeypot + IP rate limit in subscribe.php.
    $action_url = esc_url( admin_url( 'admin-post.php' ) );

    $html = '<div class="lean-cta-block lean-cta-optin" style="--lean-accent:' . $accent . '">';

    if ( $title ) {
        $html .= '<p class="lean-cta-title">' . $title . '</p>';
    }
    if ( $text ) {
        $html .= '<p class="lean-cta-text">' . $text . '</p>';
    }

    // State area: reserved height prevents CLS when switching form ↔ success message.
    // min-height matches form row height (~44px input + gap).
    $html .= '<div class="lean-cta-optin-state" style="min-height:52px">';

    if ( 'ok' === $done ) {
        // Success state (no-JS redirect landed here).
        $html .= '<p class="lean-cta-optin-success" role="status">' . $success_msg . '</p>';
    } else {
        // Form state — renders even if $list_uuid is empty (graceful degradation).
        $html .= '<form class="lean-cta-optin-form" method="post" action="' . $action_url . '" novalidate'
               . ' data-list="' . $list_uuid . '"'
               . ' data-success="' . $success_msg . '">';
        $html .= '<input type="hidden" name="action" value="lc_subscribe">';
        $html .= '<input type="hidden" name="lc_list" value="' . $list_uuid . '">';

        // Honeypot: positioned off-screen so screen readers/bots see it but visual users don't.
        $html .= '<span aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">'
               . '<label for="lc_hp_field">' . esc_html__( 'Leave this field blank', 'lean-ctas' ) . '</label>'
               . '<input type="text" id="lc_hp_field" name="lc_hp" value="" autocomplete="off" tabindex="-1">'
               . '</span>';

        $html .= '<div class="lean-cta-optin-row">';
        $html .= '<label for="lc_email_' . esc_attr( $list_uuid ) . '" class="screen-reader-text">'
               . esc_html__( 'Email address', 'lean-ctas' )
               . '</label>';
        $html .= '<input type="email" id="lc_email_' . esc_attr( $list_uuid ) . '" name="lc_email"'
               . ' placeholder="' . esc_attr__( 'your@email.com', 'lean-ctas' ) . '"'
               . ' aria-label="' . esc_attr__( 'Email address', 'lean-ctas' ) . '"'
               . ' required autocomplete="email">';
        $html .= '<button type="submit" class="lean-cta-btn">' . $label . '</button>';
        $html .= '</div>';

        if ( 'err' === $done ) {
            $html .= '<p class="lean-cta-optin-error" role="alert">'
                   . esc_html__( 'Something went wrong. Please try again.', 'lean-ctas' )
                   . '</p>';
        }

        $html .= '</form>';
    }

    $html .= '</div>'; // .lean-cta-optin-state
    $html .= '</div>'; // .lean-cta-block

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

    // Detect if any optin_form CTA is configured. An opt-in form may be rendered
    // sitewide via the [lean_cta optin="1"] shortcode (e.g. the Daily Shot popup
    // in wp_footer), so its styles/JS must load beyond singular pages.
    $has_optin = false;
    foreach ( $settings['ctas'] as $cta ) {
        if ( ( $cta['button_type'] ?? '' ) === 'optin_form' ) {
            $has_optin = true;
            break;
        }
    }

    // Load on singular post-type pages (inline-injected CTAs) OR anywhere an
    // opt-in form can appear via shortcode (sitewide popup).
    if ( ! is_singular( $settings['post_types'] ) && ! $has_optin ) {
        return;
    }

    // No nonce: cached pages would serve a stale nonce after ~24h, silently
    // breaking subscriptions. Protection is via UUID allowlist + honeypot + IP
    // rate limit. See subscribe.php for the full security rationale.
    $rest_url = $has_optin ? esc_url( rest_url( 'lean-ctas/v1/subscribe' ) ) : '';

    ?>
    <style id="lean-cta-styles">
    .lean-cta-block{--lean-accent:<?php echo esc_attr( $settings['default_color'] ?? '#FF6B35' ); ?>;--lean-bg:rgba(0,0,0,.04);--lean-title:#111;--lean-text:#444;border-left:4px solid var(--lean-accent);background:var(--lean-bg);padding:16px 20px;margin:28px 0;border-radius:0 6px 6px 0;font-family:inherit}
    .lean-cta-title{font-weight:700;font-size:1.05em;margin:0 0 6px;color:var(--lean-title)}
    .lean-cta-text{margin:0 0 12px;color:var(--lean-text);font-size:.95em;line-height:1.5}
    .lean-cta-btn{display:inline-block;background:var(--lean-accent);color:#fff!important;padding:8px 18px;border-radius:4px;text-decoration:none!important;font-weight:600;font-size:.9em;transition:opacity .2s;border:none;cursor:pointer;line-height:1.4}
    .lean-cta-btn:hover{opacity:.85}
    .dark .lean-cta-block,[data-theme=dark] .lean-cta-block,[data-color-scheme=dark] .lean-cta-block,.lean-cta-dark{--lean-bg:rgba(255,255,255,.06);--lean-title:rgba(255,255,255,.92);--lean-text:rgba(255,255,255,.7)}
    .lean-cta-optin-row{display:flex;gap:8px;align-items:stretch;flex-wrap:wrap}
    .lean-cta-optin-row input[type=email]{flex:1 1 200px;min-width:0;padding:8px 12px;border:1px solid #ccc;border-radius:4px;font-size:.9em;font-family:inherit;background:transparent;color:inherit;box-sizing:border-box}
    .lean-cta-optin-row input[type=email]:focus{outline:2px solid var(--lean-accent);outline-offset:1px;border-color:transparent}
    .lean-cta-optin-success{margin:0;font-weight:600;color:var(--lean-title);font-size:.95em}
    .lean-cta-optin-error{margin:6px 0 0;font-size:.85em;color:#c00}
    .screen-reader-text{position:absolute;width:1px;height:1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    @media(max-width:600px){.lean-cta-btn{display:block;text-align:center}.lean-cta-optin-row{flex-direction:column}.lean-cta-optin-row .lean-cta-btn{width:100%}}
    </style>
    <script>document.addEventListener('DOMContentLoaded',function(){var b=getComputedStyle(document.body).backgroundColor,m=b.match(/\d+/g);if(m&&m.length>=3){var l=(.299*m[0]+.587*m[1]+.114*m[2]);if(l<128)document.querySelectorAll('.lean-cta-block').forEach(function(e){e.classList.add('lean-cta-dark')})}})</script>
    <?php

    // Progressive-enhancement JS for optin forms — only injected when needed.
    // Intercepts submit, posts to REST, swaps form for success message without reload.
    // Falls back to normal POST if JS is disabled or fetch fails.
    if ( $has_optin ) :
    ?>
    <script>
    (function(){
    var U=<?php echo wp_json_encode( $rest_url ); ?>;
    document.addEventListener('submit',function(e){
        var f=e.target;
        if(!f.classList.contains('lean-cta-optin-form'))return;
        e.preventDefault();
        var em=f.querySelector('[name=lc_email]'),btn=f.querySelector('[type=submit]');
        if(!em||!em.value)return;
        btn.disabled=true;
        fetch(U,{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({email:em.value,list_uuid:f.dataset.list,hp:''})})
        .then(function(r){return r.json()})
        .then(function(d){
            var s=f.closest('.lean-cta-optin-state');
            if(d.success&&s){s.innerHTML='<p class="lean-cta-optin-success" role="status">'+
                (f.dataset.success||'Check your email to confirm.')+
            '</p>';
            // Notify listeners (e.g. the sitewide popup auto-closes on this).
            document.dispatchEvent(new CustomEvent('leanctas:subscribed',{detail:{list:f.dataset.list}}));
            }else{btn.disabled=false;var err=f.querySelector('.lean-cta-optin-error');
            if(err)err.style.display='';else{var p=document.createElement('p');
            p.className='lean-cta-optin-error';p.setAttribute('role','alert');
            p.textContent='Something went wrong. Please try again.';f.appendChild(p);}}
        })
        .catch(function(){btn.disabled=false;f.submit()});
    });
    })();
    </script>
    <?php
    endif;
}

/* ─────────────────────────────────────────────
   Shortcode  [lean_cta] or [eco_cta] (legacy)
───────────────────────────────────────────── */

/**
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML.
 */
function shortcode( array|string $atts ): string {
    $atts = shortcode_atts( [
        'post_type' => '',
        'taxonomy'  => '',
        'term'      => 0,
        'category'  => 0,
        'optin'     => '',
    ], $atts, 'lean_cta' );

    $settings = get_plugin_settings();

    if ( empty( $settings['ctas'] ) ) {
        return '';
    }

    // optin="1" → render the first configured opt-in form CTA directly,
    // bypassing post/taxonomy matching. Used by the sitewide Daily Shot popup.
    if ( ! empty( $atts['optin'] ) ) {
        foreach ( $settings['ctas'] as $c ) {
            if ( ( $c['button_type'] ?? '' ) === 'optin_form' ) {
                return render( $c );
            }
        }
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
