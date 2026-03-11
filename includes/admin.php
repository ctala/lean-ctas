<?php
/**
 * Admin settings page.
 *
 * @package LeanCTAs
 * @since   2.0.0
 */


namespace LeanCTAs\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use function LeanCTAs\Helpers\get_plugin_settings;
use function LeanCTAs\Helpers\get_taxonomy_options;
use function LeanCTAs\Helpers\cta_defaults;

use const LeanCTAs\OPTION_KEY;
use const LeanCTAs\SLUG;
use const LeanCTAs\VERSION;

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );
add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );
add_filter( 'pre_update_option_' . OPTION_KEY, __NAMESPACE__ . '\\parse_combo_values', 5 );
add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/lean-ctas.php' ), __NAMESPACE__ . '\\add_settings_link' );

/**
 * Add "Settings" link in the plugins list.
 *
 * @param string[] $links Existing links.
 * @return string[]
 */
function add_settings_link( array $links ): array {
    $url  = admin_url( 'options-general.php?page=' . SLUG );
    $link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'lean-ctas' ) . '</a>';
    array_unshift( $links, $link );
    return $links;
}

function register_page(): void {
    add_options_page(
        __( 'Lean CTAs', 'lean-ctas' ),
        __( 'Lean CTAs', 'lean-ctas' ),
        'manage_options',
        SLUG,
        __NAMESPACE__ . '\\render_page'
    );
}

function register_settings(): void {
    register_setting(
        'lean_ctas_group',
        OPTION_KEY,
        [
            'type'              => 'array',
            'sanitize_callback' => 'LeanCTAs\\Helpers\\sanitize',
            'default'           => \LeanCTAs\Helpers\defaults(),
        ]
    );
}

/**
 * Parse the combo "taxonomy:term_id" values from the select element.
 *
 * @param mixed $value Raw option value.
 * @return mixed
 */
function parse_combo_values( mixed $value ): mixed {
    if ( ! is_array( $value ) || empty( $value['ctas'] ) ) {
        return $value;
    }

    foreach ( $value['ctas'] as &$cta ) {
        if (
            is_array( $cta )
            && ! empty( $cta['term_id'] )
            && is_string( $cta['term_id'] )
            && str_contains( $cta['term_id'], ':' )
        ) {
            [ $tax, $tid ]   = explode( ':', $cta['term_id'], 2 );
            $cta['taxonomy'] = $tax;
            $cta['term_id']  = (int) $tid;
        }
    }
    unset( $cta );

    return $value;
}

/* ─────────────────────────────────────────────
   Render — Settings page
───────────────────────────────────────────── */

function render_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings     = get_plugin_settings();
    $tax_options  = get_taxonomy_options();
    $public_types = get_post_types( [ 'public' => true ], 'objects' );
    unset( $public_types['attachment'] );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( '⚡ Lean CTAs', 'lean-ctas' ); ?>
            <span style="font-size:12px;color:#999;margin-left:8px">v<?php echo esc_html( VERSION ); ?></span>
        </h1>
        <p style="color:#666">
            <?php esc_html_e( 'Inject dynamic CTAs into content by post type, taxonomy, or category.', 'lean-ctas' ); ?>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields( 'lean_ctas_group' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enabled', 'lean-ctas' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( OPTION_KEY ); ?>[enabled]" value="1"
                                <?php checked( $settings['enabled'] ); ?>>
                            <?php esc_html_e( 'Inject CTAs into content', 'lean-ctas' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enabled Post Types', 'lean-ctas' ); ?></th>
                    <td>
                        <?php foreach ( $public_types as $pt ) : ?>
                            <label style="display:inline-block;margin-right:16px">
                                <input type="checkbox"
                                    name="<?php echo esc_attr( OPTION_KEY ); ?>[post_types][]"
                                    value="<?php echo esc_attr( $pt->name ); ?>"
                                    <?php checked( in_array( $pt->name, $settings['post_types'], true ) ); ?>>
                                <?php echo esc_html( $pt->labels->singular_name ); ?>
                                <code style="font-size:11px;color:#999">(<?php echo esc_html( $pt->name ); ?>)</code>
                            </label><br>
                        <?php endforeach; ?>
                        <p class="description">
                            <?php esc_html_e( 'The plugin only runs on selected post types.', 'lean-ctas' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Default accent color', 'lean-ctas' ); ?></th>
                    <td>
                        <input type="color"
                            name="<?php echo esc_attr( OPTION_KEY ); ?>[default_color]"
                            value="<?php echo esc_attr( $settings['default_color'] ); ?>"
                            style="width:60px;height:36px">
                        <code style="margin-left:8px;color:#666"><?php echo esc_html( $settings['default_color'] ); ?></code>
                        <p class="description">
                            <?php esc_html_e( 'Used when a CTA does not have its own color set.', 'lean-ctas' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Inline insert after paragraph #', 'lean-ctas' ); ?></th>
                    <td>
                        <input type="number" min="1" max="50"
                            name="<?php echo esc_attr( OPTION_KEY ); ?>[insert_after_paragraph]"
                            value="<?php echo esc_attr( (string) $settings['insert_after_paragraph'] ); ?>"
                            style="width:80px">
                        <p class="description">
                            <?php esc_html_e( 'If the post has fewer paragraphs, the CTA is appended at the end.', 'lean-ctas' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 style="margin-top:2em"><?php esc_html_e( 'Configured CTAs', 'lean-ctas' ); ?></h2>
            <p>
                <?php esc_html_e( 'Each CTA can filter by post type and/or taxonomy. No filters = applies to all enabled content.', 'lean-ctas' ); ?>
            </p>

            <div id="lean-cta-list">
                <?php foreach ( $settings['ctas'] as $i => $cta ) : ?>
                    <?php render_cta_row( $i, $cta, $tax_options, $public_types ); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" id="lean-add-cta" class="button" style="margin-top:1em">
                + <?php esc_html_e( 'Add CTA', 'lean-ctas' ); ?>
            </button>

            <p style="margin-top:2em">
                <?php submit_button( __( 'Save Changes', 'lean-ctas' ), 'primary', 'submit', false ); ?>
            </p>
        </form>
    </div>

    <template id="lean-cta-template">
        <?php render_cta_row( '__INDEX__', [], $tax_options, $public_types ); ?>
    </template>

    <style>
        .lean-cta-row{border:1px solid #ddd;padding:16px;margin-bottom:16px;border-radius:4px;background:#fafafa}
        .lean-cta-row h3{margin:0 0 12px}
        .lean-cta-row .lean-cta-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .lean-cta-row label{display:block;font-weight:600;margin-bottom:4px;font-size:13px}
        .lean-cta-row input[type=text],.lean-cta-row textarea,.lean-cta-row select,.lean-cta-row input[type=url],.lean-cta-row input[type=color]{width:100%}
        .lean-remove-cta{float:right;color:#a00!important;cursor:pointer;text-decoration:none!important}
    </style>
    <script>
    (function(){
        let idx=<?php echo count( $settings['ctas'] ); ?>;
        document.getElementById('lean-add-cta').addEventListener('click',function(){
            const h=document.getElementById('lean-cta-template').innerHTML.replace(/__INDEX__/g,idx++);
            const d=document.createElement('div');d.innerHTML=h;
            document.getElementById('lean-cta-list').appendChild(d.firstElementChild);
        });
        document.getElementById('lean-cta-list').addEventListener('click',function(e){
            if(e.target.classList.contains('lean-remove-cta')){e.preventDefault();e.target.closest('.lean-cta-row').remove();}
        });
    })();
    </script>
    <?php
}

/* ─────────────────────────────────────────────
   Render — Single CTA row
───────────────────────────────────────────── */

/**
 * @param int|string                       $i            Row index.
 * @param array<string, mixed>             $cta          CTA data.
 * @param array<string, array>             $tax_options  Taxonomy options.
 * @param array<string, \WP_Post_Type>     $public_types Public post types.
 */
function render_cta_row( int|string $i, array $cta, array $tax_options, array $public_types ): void {
    $cta = wp_parse_args( $cta, cta_defaults() );
    $n   = OPTION_KEY . "[ctas][{$i}]";
    ?>
    <div class="lean-cta-row">
        <h3>
            CTA #<?php echo is_numeric( $i ) ? (int) $i + 1 : '?'; ?>
            <a href="#" class="lean-remove-cta">✕ <?php esc_html_e( 'Remove', 'lean-ctas' ); ?></a>
        </h3>
        <div class="lean-cta-fields">
            <div>
                <label><?php esc_html_e( 'Filter by Post Type', 'lean-ctas' ); ?></label>
                <select name="<?php echo esc_attr( $n ); ?>[post_type]">
                    <option value=""><?php esc_html_e( '— All enabled —', 'lean-ctas' ); ?></option>
                    <?php foreach ( $public_types as $pt ) : ?>
                        <option value="<?php echo esc_attr( $pt->name ); ?>"
                            <?php selected( $cta['post_type'], $pt->name ); ?>>
                            <?php echo esc_html( $pt->labels->singular_name ); ?> (<?php echo esc_html( $pt->name ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label><?php esc_html_e( 'Filter by Taxonomy / Term', 'lean-ctas' ); ?></label>
                <select name="<?php echo esc_attr( $n ); ?>[term_id]">
                    <option value="0"><?php esc_html_e( '— No taxonomy filter —', 'lean-ctas' ); ?></option>
                    <?php foreach ( $tax_options as $tax_name => $tax_data ) : ?>
                        <optgroup label="<?php echo esc_attr( $tax_data['label'] . ' (' . $tax_name . ')' ); ?>">
                            <?php foreach ( $tax_data['terms'] as $term ) :
                                $combo   = $tax_name . ':' . $term->term_id;
                                $current = $cta['taxonomy'] . ':' . $cta['term_id'];
                            ?>
                                <option value="<?php echo esc_attr( $combo ); ?>"
                                    <?php selected( $combo, $current ); ?>>
                                    <?php echo esc_html( $term->name ); ?> (<?php echo (int) $term->count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label><?php esc_html_e( 'Accent color (override)', 'lean-ctas' ); ?></label>
                <input type="color" name="<?php echo esc_attr( $n ); ?>[accent_color]"
                    value="<?php echo esc_attr( $cta['accent_color'] ?: ( get_plugin_settings()['default_color'] ?? '#FF6B35' ) ); ?>">
                <p class="description" style="font-size:11px;margin-top:4px">
                    <?php esc_html_e( 'Leave default to use global color.', 'lean-ctas' ); ?>
                </p>
            </div>
            <div>
                <label><?php esc_html_e( 'Position', 'lean-ctas' ); ?></label>
                <select name="<?php echo esc_attr( $n ); ?>[position]">
                    <option value="inline" <?php selected( $cta['position'], 'inline' ); ?>>📍 <?php esc_html_e( 'Inline (after paragraph N)', 'lean-ctas' ); ?></option>
                    <option value="end"    <?php selected( $cta['position'], 'end' ); ?>>⬇️ <?php esc_html_e( 'End of post', 'lean-ctas' ); ?></option>
                    <option value="both"   <?php selected( $cta['position'], 'both' ); ?>>📍⬇️ <?php esc_html_e( 'Both', 'lean-ctas' ); ?></option>
                </select>
            </div>
            <div>
                <label><?php esc_html_e( 'CTA Title', 'lean-ctas' ); ?></label>
                <input type="text" name="<?php echo esc_attr( $n ); ?>[title]"
                    value="<?php echo esc_attr( $cta['title'] ); ?>"
                    placeholder="<?php esc_attr_e( 'e.g. Looking for funding?', 'lean-ctas' ); ?>">
            </div>
            <div>
                <label><?php esc_html_e( 'Button type', 'lean-ctas' ); ?></label>
                <select name="<?php echo esc_attr( $n ); ?>[button_type]">
                    <option value="link"       <?php selected( $cta['button_type'], 'link' ); ?>>🔗 <?php esc_html_e( 'External link', 'lean-ctas' ); ?></option>
                    <option value="newsletter" <?php selected( $cta['button_type'], 'newsletter' ); ?>>📧 <?php esc_html_e( 'Newsletter', 'lean-ctas' ); ?></option>
                    <option value="community"  <?php selected( $cta['button_type'], 'community' ); ?>>👥 <?php esc_html_e( 'Community', 'lean-ctas' ); ?></option>
                </select>
            </div>
            <div style="grid-column:span 2">
                <label><?php esc_html_e( 'CTA Text', 'lean-ctas' ); ?></label>
                <textarea name="<?php echo esc_attr( $n ); ?>[text]" rows="2"
                    placeholder="<?php esc_attr_e( 'e.g. We send open calls directly to your inbox every week.', 'lean-ctas' ); ?>"><?php echo esc_textarea( $cta['text'] ); ?></textarea>
            </div>
            <div>
                <label><?php esc_html_e( 'Button label', 'lean-ctas' ); ?></label>
                <input type="text" name="<?php echo esc_attr( $n ); ?>[button_label]"
                    value="<?php echo esc_attr( $cta['button_label'] ); ?>"
                    placeholder="<?php esc_attr_e( 'e.g. Subscribe free', 'lean-ctas' ); ?>">
            </div>
            <div>
                <label><?php esc_html_e( 'Button URL', 'lean-ctas' ); ?></label>
                <input type="url" name="<?php echo esc_attr( $n ); ?>[button_url]"
                    value="<?php echo esc_attr( $cta['button_url'] ); ?>"
                    placeholder="https://...">
            </div>
        </div>
    </div>
    <?php
}
