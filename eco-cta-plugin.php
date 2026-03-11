<?php
/**
 * Plugin Name: Eco CTA Plugin
 * Plugin URI:  https://github.com/ctala/eco-cta-plugin
 * Description: Inyecta CTAs dinámicos inline según la categoría del post. Configurable desde el panel de administración.
 * Version:     1.0.0
 * Author:      Cristian Tala / Nyx
 * Author URI:  https://cristiantala.com
 * License:     GPL-2.0+
 * Text Domain: eco-cta
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ECO_CTA_VERSION', '1.0.0' );
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
        'enabled'          => true,
        'insert_after_paragraph' => 3,
        'ctas'             => [],
    ];
}

function eco_cta_get(): array {
    return wp_parse_args( get_option( ECO_CTA_OPTION, [] ), eco_cta_defaults() );
}

function eco_cta_sanitize( $input ): array {
    $clean = eco_cta_defaults();
    $clean['enabled']                 = ! empty( $input['enabled'] );
    $clean['insert_after_paragraph']  = max( 1, intval( $input['insert_after_paragraph'] ?? 3 ) );

    $clean['ctas'] = [];
    if ( ! empty( $input['ctas'] ) && is_array( $input['ctas'] ) ) {
        foreach ( $input['ctas'] as $cta ) {
            $clean['ctas'][] = [
                'category_id'  => intval( $cta['category_id'] ?? 0 ),
                'title'        => sanitize_text_field( $cta['title'] ?? '' ),
                'text'         => sanitize_textarea_field( $cta['text'] ?? '' ),
                'button_label' => sanitize_text_field( $cta['button_label'] ?? '' ),
                'button_url'   => esc_url_raw( $cta['button_url'] ?? '' ),
                'button_type'  => sanitize_key( $cta['button_type'] ?? 'link' ),
                'accent_color' => sanitize_hex_color( $cta['accent_color'] ?? '#FF6B35' ),
            ];
        }
    }

    return $clean;
}

function eco_cta_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $settings   = eco_cta_get();
    $categories = get_categories( [ 'hide_empty' => false ] );
    ?>
    <div class="wrap">
        <h1>⚡ Eco CTA Plugin</h1>
        <p style="color:#666">Inyecta CTAs dinámicos dentro del contenido según la categoría del post.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'eco_cta_group' ); ?>

            <table class="form-table">
                <tr>
                    <th>Activado</th>
                    <td>
                        <input type="checkbox" name="<?= ECO_CTA_OPTION ?>[enabled]" value="1"
                            <?= checked( $settings['enabled'], true, false ) ?>>
                        <label>Inyectar CTAs en posts</label>
                    </td>
                </tr>
                <tr>
                    <th>Insertar después del párrafo N°</th>
                    <td>
                        <input type="number" min="1" max="20"
                            name="<?= ECO_CTA_OPTION ?>[insert_after_paragraph]"
                            value="<?= esc_attr( $settings['insert_after_paragraph'] ) ?>"
                            style="width:80px">
                        <p class="description">Si el post tiene menos párrafos, el CTA se inserta al final.</p>
                    </td>
                </tr>
            </table>

            <h2 style="margin-top:2em">CTAs por Categoría</h2>
            <p>Cada categoría puede tener su propio CTA. Si un post tiene varias categorías con CTA, se usa el primero que coincida.</p>

            <div id="eco-cta-list">
                <?php foreach ( $settings['ctas'] as $i => $cta ) : ?>
                    <?php eco_cta_row( $i, $cta, $categories ); ?>
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
        <?php eco_cta_row( '__INDEX__', [], $categories ); ?>
    </template>

    <style>
        .eco-cta-row { border:1px solid #ddd; padding:16px; margin-bottom:16px; border-radius:4px; background:#fafafa; }
        .eco-cta-row h3 { margin:0 0 12px; }
        .eco-cta-row .eco-cta-fields { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .eco-cta-row label { display:block; font-weight:600; margin-bottom:4px; font-size:13px; }
        .eco-cta-row input[type=text], .eco-cta-row textarea, .eco-cta-row select, .eco-cta-row input[type=url], .eco-cta-row input[type=color] { width:100%; }
        .eco-remove-cta { float:right; color:#a00 !important; cursor:pointer; }
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
                e.target.closest('.eco-cta-row').remove();
            }
        });
    })();
    </script>
    <?php
}

function eco_cta_row( $i, array $cta, array $categories ) {
    $defaults = [
        'category_id'  => 0,
        'title'        => '',
        'text'         => '',
        'button_label' => '',
        'button_url'   => '',
        'button_type'  => 'link',
        'accent_color' => '#FF6B35',
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
                <label>Categoría</label>
                <select name="<?= $n ?>[category_id]">
                    <option value="0">— Todas las categorías —</option>
                    <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?= $cat->term_id ?>" <?= selected( $cta['category_id'], $cat->term_id, false ) ?>>
                            <?= esc_html( $cat->name ) ?> (<?= $cat->count ?> posts)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Color de acento</label>
                <input type="color" name="<?= $n ?>[accent_color]" value="<?= esc_attr( $cta['accent_color'] ) ?>">
            </div>
            <div>
                <label>Título del CTA</label>
                <input type="text" name="<?= $n ?>[title]" value="<?= esc_attr( $cta['title'] ) ?>"
                    placeholder="ej: ¿Buscas financiamiento?">
            </div>
            <div>
                <label>Tipo de botón</label>
                <select name="<?= $n ?>[button_type]">
                    <option value="link"      <?= selected( $cta['button_type'], 'link',      false ) ?>>🔗 Link externo</option>
                    <option value="newsletter"<?= selected( $cta['button_type'], 'newsletter',false ) ?>>📧 Newsletter</option>
                    <option value="community" <?= selected( $cta['button_type'], 'community', false ) ?>>👥 Comunidad</option>
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
    // Solo en posts singulares, en el loop principal
    if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $settings = eco_cta_get();
    if ( empty( $settings['enabled'] ) ) return $content;
    if ( empty( $settings['ctas'] ) )    return $content;

    // Encontrar CTA aplicable
    $cta = eco_cta_match( $settings['ctas'] );
    if ( ! $cta ) return $content;

    $html = eco_cta_render( $cta );
    return eco_cta_insert_after_paragraph( $content, $html, $settings['insert_after_paragraph'] );
}

function eco_cta_match( array $ctas ): ?array {
    // Primero: buscar CTA específico para alguna categoría del post
    $post_categories = wp_get_post_categories( get_the_ID(), [ 'fields' => 'ids' ] );

    foreach ( $ctas as $cta ) {
        if ( ! empty( $cta['category_id'] ) && in_array( $cta['category_id'], $post_categories ) ) {
            return $cta;
        }
    }

    // Fallback: CTA con category_id = 0 (todas las categorías)
    foreach ( $ctas as $cta ) {
        if ( empty( $cta['category_id'] ) ) {
            return $cta;
        }
    }

    return null;
}

function eco_cta_insert_after_paragraph( string $content, string $injection, int $after ): string {
    // Separar por </p> y reensamblar
    $parts = explode( '</p>', $content );
    $total = count( $parts );

    if ( $total <= 1 ) {
        // Contenido sin párrafos: append al final
        return $content . $injection;
    }

    $insert_at = min( $after, $total - 1 );

    $parts[ $insert_at ] .= '</p>' . $injection;

    // Eliminar el </p> extra que íbamos a agregar manualmente
    $result = implode( '</p>', $parts );

    // Corregir el último </p> que se duplicó
    return str_replace( '</p></p>', '</p>', $result );
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
    if ( ! is_singular( 'post' ) ) return;
    $settings = eco_cta_get();
    if ( empty( $settings['enabled'] ) || empty( $settings['ctas'] ) ) return;
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
   4. SHORTCODE — uso manual [eco_cta category="16"]
───────────────────────────────────────────── */

add_shortcode( 'eco_cta', function ( $atts ) {
    $atts     = shortcode_atts( [ 'category' => 0 ], $atts );
    $settings = eco_cta_get();
    if ( empty( $settings['ctas'] ) ) return '';

    $target_cat = intval( $atts['category'] );
    $cta        = null;

    foreach ( $settings['ctas'] as $c ) {
        if ( $target_cat && intval( $c['category_id'] ) === $target_cat ) {
            $cta = $c; break;
        }
        if ( ! $target_cat && empty( $c['category_id'] ) ) {
            $cta = $c; break;
        }
    }

    return $cta ? eco_cta_render( $cta ) : '';
} );
