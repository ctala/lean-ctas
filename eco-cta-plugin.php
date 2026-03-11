<?php
/**
 * Plugin Name: Eco CTA Plugin
 * Plugin URI:  https://github.com/ctala/eco-cta-plugin
 * Description: Inyecta CTAs dinámicos inline según categoría, taxonomía o post type. Configurable desde el panel de administración.
 * Version:     1.2.0
 * Author:      Cristian Tala / Nyx
 * Author URI:  https://cristiantala.com
 * License:     GPL-2.0+
 * Text Domain: eco-cta
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ECO_CTA_VERSION', '1.2.0' );
define( 'ECO_CTA_OPTION',  'eco_cta_settings' );

/* ─────────────────────────────────────────────
   1. ADMIN — Página de ajustes
───────────────────────────────────────────── */

add_action( 'admin_menu', function () {
    add_options_page(
        'Eco CTA Settings',
        'Eco CTA',
        'manage_options',
        'eco-cta',
        'eco_cta_settings_page'
    );
} );

add_action( 'admin_init', function () {
    register_setting( 'eco_cta_group', ECO_CTA_OPTION, 'eco_cta_sanitize' );
} );

function eco_cta_defaults(): array {
    return [
        'enabled'                => true,
        'insert_after_paragraph' => 3,
        'post_types'             => [ 'post' ],
        'ctas'                   => [],
    ];
}

function eco_cta_get(): array {
    return wp_parse_args( get_option( ECO_CTA_OPTION, [] ), eco_cta_defaults() );
}

function eco_cta_sanitize( $input ): array {
    $clean = eco_cta_defaults();
    $clean['enabled']                = ! empty( $input['enabled'] );
    $clean['insert_after_paragraph'] = max( 1, intval( $input['insert_after_paragraph'] ?? 3 ) );

    // Post types — solo aceptar post types públicos que existan
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

    // CTAs
    $valid_positions = [ 'inline', 'end', 'both' ];
    $clean['ctas'] = [];
    if ( ! empty( $input['ctas'] ) && is_array( $input['ctas'] ) ) {
        foreach ( $input['ctas'] as $cta ) {
            $position = sanitize_key( $cta['position'] ?? 'inline' );
            $clean['ctas'][] = [
                'post_type'    => sanitize_key( $cta['post_type'] ?? '' ),
                'taxonomy'     => sanitize_key( $cta['taxonomy'] ?? '' ),
                'term_id'      => intval( $cta['term_id'] ?? 0 ),
                'title'        => sanitize_text_field( $cta['title'] ?? '' ),
                'text'         => sanitize_textarea_field( $cta['text'] ?? '' ),
                'button_label' => sanitize_text_field( $cta['button_label'] ?? '' ),
                'button_url'   => esc_url_raw( $cta['button_url'] ?? '' ),
                'button_type'  => sanitize_key( $cta['button_type'] ?? 'link' ),
                'accent_color' => sanitize_hex_color( $cta['accent_color'] ?? '#FF6B35' ),
                'position'     => in_array( $position, $valid_positions ) ? $position : 'inline',
            ];
        }
    }

    return $clean;
}

/**
 * Obtener todas las taxonomías con sus terms, agrupadas para el selector del admin.
 */
function eco_cta_get_taxonomy_options(): array {
    $options = [];
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

function eco_cta_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $settings      = eco_cta_get();
    $tax_options   = eco_cta_get_taxonomy_options();
    $public_types  = get_post_types( [ 'public' => true ], 'objects' );
    // Excluir attachment
    unset( $public_types['attachment'] );
    ?>
    <div class="wrap">
        <h1>⚡ Eco CTA Plugin</h1>
        <p style="color:#666">Inyecta CTAs dinámicos dentro del contenido según post type, taxonomía o categoría.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'eco_cta_group' ); ?>

            <table class="form-table">
                <tr>
                    <th>Activado</th>
                    <td>
                        <input type="checkbox" name="<?= ECO_CTA_OPTION ?>[enabled]" value="1"
                            <?= checked( $settings['enabled'], true, false ) ?>>
                        <label>Inyectar CTAs en contenido</label>
                    </td>
                </tr>
                <tr>
                    <th>Post Types habilitados</th>
                    <td>
                        <?php foreach ( $public_types as $pt ) : ?>
                            <label style="display:inline-block;margin-right:16px">
                                <input type="checkbox"
                                    name="<?= ECO_CTA_OPTION ?>[post_types][]"
                                    value="<?= esc_attr( $pt->name ) ?>"
                                    <?= checked( in_array( $pt->name, $settings['post_types'] ), true, false ) ?>>
                                <?= esc_html( $pt->labels->singular_name ) ?>
                                <code style="font-size:11px;color:#999">(<?= $pt->name ?>)</code>
                            </label><br>
                        <?php endforeach; ?>
                        <p class="description">El plugin solo se ejecuta en los post types seleccionados.</p>
                    </td>
                </tr>
                <tr>
                    <th>Insertar inline después del párrafo N°</th>
                    <td>
                        <input type="number" min="1" max="20"
                            name="<?= ECO_CTA_OPTION ?>[insert_after_paragraph]"
                            value="<?= esc_attr( $settings['insert_after_paragraph'] ) ?>"
                            style="width:80px">
                        <p class="description">Si el post tiene menos párrafos, el CTA se inserta al final.</p>
                    </td>
                </tr>
            </table>

            <h2 style="margin-top:2em">CTAs configurados</h2>
            <p>Cada CTA puede filtrar por post type y/o taxonomía. Si no se especifica filtro, se aplica a todo el contenido habilitado.</p>

            <div id="eco-cta-list">
                <?php foreach ( $settings['ctas'] as $i => $cta ) : ?>
                    <?php eco_cta_row( $i, $cta, $tax_options, $public_types ); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" id="eco-add-cta" class="button" style="margin-top:1em">
                + Agregar CTA
            </button>

            <p style="margin-top:2em">
                <?php submit_button( 'Guardar cambios', 'primary', 'submit', false ); ?>
            </p>
        </form>
    </div>

    <template id="eco-cta-template">
        <?php eco_cta_row( '__INDEX__', [], $tax_options, $public_types ); ?>
    </template>

    <style>
        .eco-cta-row { border:1px solid #ddd; padding:16px; margin-bottom:16px; border-radius:4px; background:#fafafa; }
        .eco-cta-row h3 { margin:0 0 12px; }
        .eco-cta-row .eco-cta-fields { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .eco-cta-row label { display:block; font-weight:600; margin-bottom:4px; font-size:13px; }
        .eco-cta-row input[type=text], .eco-cta-row textarea, .eco-cta-row select, .eco-cta-row input[type=url], .eco-cta-row input[type=color] { width:100%; }
        .eco-remove-cta { float:right; color:#a00 !important; cursor:pointer; text-decoration:none !important; }
    </style>

    <script>
    (function () {
        let index = <?= count( $settings['ctas'] ) ?>;
        document.getElementById('eco-add-cta').addEventListener('click', function () {
            const tpl = document.getElementById('eco-cta-template').innerHTML
                .replace(/__INDEX__/g, index++);
            const div = document.createElement('div');
            div.innerHTML = tpl;
            document.getElementById('eco-cta-list').appendChild(div.firstElementChild);
        });
        document.getElementById('eco-cta-list').addEventListener('click', function (e) {
            if (e.target.classList.contains('eco-remove-cta')) {
                e.preventDefault();
                e.target.closest('.eco-cta-row').remove();
            }
        });
    })();
    </script>
    <?php
}

function eco_cta_row( $i, array $cta, array $tax_options, $public_types ) {
    $defaults = [
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
    $cta = wp_parse_args( $cta, $defaults );
    $n   = ECO_CTA_OPTION . "[ctas][$i]";
    ?>
    <div class="eco-cta-row">
        <h3>
            CTA #<?= is_numeric($i) ? intval($i) + 1 : '?' ?>
            <a href="#" class="eco-remove-cta">✕ Eliminar</a>
        </h3>
        <div class="eco-cta-fields">
            <div>
                <label>Filtrar por Post Type</label>
                <select name="<?= $n ?>[post_type]">
                    <option value="">— Todos los habilitados —</option>
                    <?php foreach ( $public_types as $pt ) : ?>
                        <option value="<?= esc_attr( $pt->name ) ?>"
                            <?= selected( $cta['post_type'], $pt->name, false ) ?>>
                            <?= esc_html( $pt->labels->singular_name ) ?> (<?= $pt->name ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Filtrar por Taxonomía / Término</label>
                <select name="<?= $n ?>[term_id]" data-taxonomy-field="<?= $n ?>[taxonomy]">
                    <option value="0">— Sin filtro de taxonomía —</option>
                    <?php foreach ( $tax_options as $tax_name => $tax_data ) : ?>
                        <optgroup label="<?= esc_attr( $tax_data['label'] ) ?> (<?= $tax_name ?>)">
                            <?php foreach ( $tax_data['terms'] as $term ) :
                                $combo_val = $tax_name . ':' . $term->term_id;
                                $current   = $cta['taxonomy'] . ':' . $cta['term_id'];
                            ?>
                                <option value="<?= esc_attr( $combo_val ) ?>"
                                    <?= selected( $combo_val, $current, false ) ?>>
                                    <?= esc_html( $term->name ) ?> (<?= $term->count ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <p class="description" style="font-size:11px;margin-top:4px">
                    Categorías, tags, taxonomías custom — todo aparece aquí.
                </p>
            </div>
            <div>
                <label>Color de acento</label>
                <input type="color" name="<?= $n ?>[accent_color]" value="<?= esc_attr( $cta['accent_color'] ) ?>">
            </div>
            <div>
                <label>Posición</label>
                <select name="<?= $n ?>[position]">
                    <option value="inline" <?= selected( $cta['position'], 'inline', false ) ?>>📍 Inline (después del párrafo N)</option>
                    <option value="end"    <?= selected( $cta['position'], 'end',    false ) ?>>⬇️ Al final del post</option>
                    <option value="both"   <?= selected( $cta['position'], 'both',   false ) ?>>📍⬇️ Ambos</option>
                </select>
            </div>
            <div>
                <label>Título del CTA</label>
                <input type="text" name="<?= $n ?>[title]" value="<?= esc_attr( $cta['title'] ) ?>"
                    placeholder="ej: ¿Buscas financiamiento?">
            </div>
            <div>
                <label>Tipo de botón</label>
                <select name="<?= $n ?>[button_type]">
                    <option value="link"       <?= selected( $cta['button_type'], 'link',       false ) ?>>🔗 Link externo</option>
                    <option value="newsletter" <?= selected( $cta['button_type'], 'newsletter', false ) ?>>📧 Newsletter</option>
                    <option value="community"  <?= selected( $cta['button_type'], 'community',  false ) ?>>👥 Comunidad</option>
                </select>
            </div>
            <div style="grid-column:span 2">
                <label>Texto del CTA</label>
                <textarea name="<?= $n ?>[text]" rows="2"
                    placeholder="ej: Cada semana enviamos las convocatorias abiertas directo a tu bandeja."><?= esc_textarea( $cta['text'] ) ?></textarea>
            </div>
            <div>
                <label>Texto del botón</label>
                <input type="text" name="<?= $n ?>[button_label]" value="<?= esc_attr( $cta['button_label'] ) ?>"
                    placeholder="ej: Suscribirme gratis">
            </div>
            <div>
                <label>URL del botón</label>
                <input type="url" name="<?= $n ?>[button_url]" value="<?= esc_attr( $cta['button_url'] ) ?>"
                    placeholder="https://...">
            </div>
        </div>
    </div>
    <?php
}

/* ─────────────────────────────────────────────
   2. FRONTEND — Inyección en contenido
───────────────────────────────────────────── */

add_filter( 'the_content', 'eco_cta_inject', 20 );

function eco_cta_inject( string $content ): string {
    if ( ! in_the_loop() || ! is_main_query() ) return $content;

    $settings = eco_cta_get();
    if ( empty( $settings['enabled'] ) || empty( $settings['ctas'] ) ) return $content;

    // Verificar post type habilitado
    $current_type = get_post_type();
    if ( ! in_array( $current_type, $settings['post_types'] ) ) return $content;
    if ( ! is_singular( $settings['post_types'] ) ) return $content;

    // Encontrar CTA aplicable
    $cta = eco_cta_match( $settings['ctas'], $current_type );
    if ( ! $cta ) return $content;

    $position = $cta['position'] ?? 'inline';
    $html     = eco_cta_render( $cta );

    if ( $position === 'inline' ) {
        $content = eco_cta_insert_after_paragraph( $content, $html, $settings['insert_after_paragraph'] );
    } elseif ( $position === 'end' ) {
        $content .= $html;
    } elseif ( $position === 'both' ) {
        $content = eco_cta_insert_after_paragraph( $content, $html, $settings['insert_after_paragraph'] );
        $content .= $html;
    }

    return $content;
}

/**
 * Matching con prioridad: post_type+term > post_type > term > fallback global
 */
function eco_cta_match( array $ctas, string $current_type ): ?array {
    $post_id    = get_the_ID();
    $post_terms = eco_cta_get_post_terms( $post_id );

    // Prioridad 1: match por post_type + taxonomy term
    foreach ( $ctas as $cta ) {
        if ( ! empty( $cta['post_type'] ) && $cta['post_type'] === $current_type
             && ! empty( $cta['taxonomy'] ) && ! empty( $cta['term_id'] ) ) {
            if ( eco_cta_has_term( $post_terms, $cta['taxonomy'], $cta['term_id'] ) ) {
                return $cta;
            }
        }
    }

    // Prioridad 2: match solo por taxonomy term (sin filtro de post_type)
    foreach ( $ctas as $cta ) {
        if ( empty( $cta['post_type'] )
             && ! empty( $cta['taxonomy'] ) && ! empty( $cta['term_id'] ) ) {
            if ( eco_cta_has_term( $post_terms, $cta['taxonomy'], $cta['term_id'] ) ) {
                return $cta;
            }
        }
    }

    // Prioridad 3: match solo por post_type (sin filtro de taxonomía)
    foreach ( $ctas as $cta ) {
        if ( ! empty( $cta['post_type'] ) && $cta['post_type'] === $current_type
             && empty( $cta['taxonomy'] ) && empty( $cta['term_id'] ) ) {
            return $cta;
        }
    }

    // Prioridad 4: fallback global (sin post_type ni taxonomía)
    foreach ( $ctas as $cta ) {
        if ( empty( $cta['post_type'] ) && empty( $cta['taxonomy'] ) && empty( $cta['term_id'] ) ) {
            return $cta;
        }
    }

    return null;
}

/**
 * Obtener todos los terms del post, indexados por taxonomía.
 */
function eco_cta_get_post_terms( int $post_id ): array {
    $result     = [];
    $taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'names' );

    foreach ( $taxonomies as $tax ) {
        $terms = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'ids' ] );
        if ( ! is_wp_error( $terms ) ) {
            $result[ $tax ] = $terms;
        }
    }

    return $result;
}

function eco_cta_has_term( array $post_terms, string $taxonomy, int $term_id ): bool {
    return isset( $post_terms[ $taxonomy ] ) && in_array( $term_id, $post_terms[ $taxonomy ] );
}

function eco_cta_insert_after_paragraph( string $content, string $injection, int $after ): string {
    $parts = explode( '</p>', $content );
    $total = count( $parts );

    if ( $total <= 1 ) return $content . $injection;

    $insert_at = min( $after, $total - 1 );
    $output    = '';

    for ( $i = 0; $i < $total; $i++ ) {
        $output .= $parts[ $i ];
        // Solo agregar </p> si no es el último fragmento vacío
        if ( $i < $total - 1 ) {
            $output .= '</p>';
        }
        if ( $i === $insert_at - 1 ) {
            $output .= $injection;
        }
    }

    return $output;
}

function eco_cta_render( array $cta ): string {
    $accent  = esc_attr( $cta['accent_color'] ?? '#FF6B35' );
    $title   = esc_html( $cta['title'] ?? '' );
    $text    = esc_html( $cta['text'] ?? '' );
    $label   = esc_html( $cta['button_label'] ?? 'Ver más' );
    $url     = esc_url( $cta['button_url'] ?? '#' );

    $icon = match ( $cta['button_type'] ?? 'link' ) {
        'newsletter'  => '📧',
        'community'   => '👥',
        default       => '→',
    };

    ob_start(); ?>
<div class="eco-cta-block" style="--eco-accent:<?= $accent ?>">
    <?php if ( $title ) : ?>
    <p class="eco-cta-title"><?= $title ?></p>
    <?php endif; ?>
    <?php if ( $text ) : ?>
    <p class="eco-cta-text"><?= $text ?></p>
    <?php endif; ?>
    <a class="eco-cta-btn" href="<?= $url ?>" target="_blank" rel="noopener">
        <?= $icon ?> <?= $label ?>
    </a>
</div>
    <?php return ob_get_clean();
}

/* ─────────────────────────────────────────────
   3. ESTILOS FRONTEND
───────────────────────────────────────────── */

add_action( 'wp_head', function () {
    $settings = eco_cta_get();
    if ( empty( $settings['enabled'] ) || empty( $settings['ctas'] ) ) return;
    if ( ! is_singular( $settings['post_types'] ) ) return;
    ?>
    <style id="eco-cta-styles">
    .eco-cta-block {
        --eco-accent: #FF6B35;
        border-left: 4px solid var(--eco-accent);
        background: #f9f9f9;
        padding: 16px 20px;
        margin: 28px 0;
        border-radius: 0 6px 6px 0;
        font-family: inherit;
    }
    .eco-cta-title {
        font-weight: 700;
        font-size: 1.05em;
        margin: 0 0 6px;
        color: #111;
    }
    .eco-cta-text {
        margin: 0 0 12px;
        color: #444;
        font-size: 0.95em;
        line-height: 1.5;
    }
    .eco-cta-btn {
        display: inline-block;
        background: var(--eco-accent);
        color: #fff !important;
        padding: 8px 18px;
        border-radius: 4px;
        text-decoration: none !important;
        font-weight: 600;
        font-size: 0.9em;
        transition: opacity .2s;
    }
    .eco-cta-btn:hover { opacity: .85; }
    @media (max-width: 600px) {
        .eco-cta-btn { display: block; text-align: center; }
    }
    </style>
    <?php
} );

/* ─────────────────────────────────────────────
   4. SHORTCODE — [eco_cta post_type="glosario" taxonomy="category" term="16"]
───────────────────────────────────────────── */

add_shortcode( 'eco_cta', function ( $atts ) {
    $atts = shortcode_atts( [
        'post_type' => '',
        'taxonomy'  => '',
        'term'      => 0,
        // Legacy: sigue aceptando category="X" como atajo
        'category'  => 0,
    ], $atts );

    $settings = eco_cta_get();
    if ( empty( $settings['ctas'] ) ) return '';

    // Legacy support: category="X" → taxonomy=category, term=X
    if ( ! empty( $atts['category'] ) && empty( $atts['taxonomy'] ) ) {
        $atts['taxonomy'] = 'category';
        $atts['term']     = intval( $atts['category'] );
    }

    $target_pt   = sanitize_key( $atts['post_type'] );
    $target_tax  = sanitize_key( $atts['taxonomy'] );
    $target_term = intval( $atts['term'] );

    // Buscar el CTA más específico que coincida
    foreach ( $settings['ctas'] as $c ) {
        $match_pt   = empty( $target_pt ) || ( ! empty( $c['post_type'] ) && $c['post_type'] === $target_pt );
        $match_tax  = empty( $target_tax ) || ( ! empty( $c['taxonomy'] ) && $c['taxonomy'] === $target_tax && intval( $c['term_id'] ) === $target_term );

        if ( $match_pt && $match_tax ) return eco_cta_render( $c );
    }

    // Fallback global
    foreach ( $settings['ctas'] as $c ) {
        if ( empty( $c['post_type'] ) && empty( $c['taxonomy'] ) && empty( $c['term_id'] ) ) {
            return eco_cta_render( $c );
        }
    }

    return '';
} );

/* ─────────────────────────────────────────────
   5. SANITIZE COMBO VALUE — taxonomy:term_id desde el select
───────────────────────────────────────────── */

add_filter( 'pre_update_option_' . ECO_CTA_OPTION, function ( $value ) {
    // Parsear los combo values "taxonomy:term_id" del select de taxonomía
    if ( ! empty( $value['ctas'] ) && is_array( $value['ctas'] ) ) {
        foreach ( $value['ctas'] as $i => &$cta ) {
            if ( ! empty( $cta['term_id'] ) && is_string( $cta['term_id'] ) && str_contains( $cta['term_id'], ':' ) ) {
                [ $tax, $tid ] = explode( ':', $cta['term_id'], 2 );
                $cta['taxonomy'] = $tax;
                $cta['term_id']  = intval( $tid );
            }
        }
    }
    return $value;
}, 5 );
