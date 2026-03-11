<?php
/**
 * Admin settings page.
 *
 * @package EcoCTA
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace EcoCTA\Admin;

use function EcoCTA\Helpers\get_settings;
use function EcoCTA\Helpers\get_taxonomy_options;
use function EcoCTA\Helpers\cta_defaults;

use const EcoCTA\OPTION_KEY;
use const EcoCTA\VERSION;

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );
add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );
add_filter( 'pre_update_option_' . OPTION_KEY, __NAMESPACE__ . '\\parse_combo_values', 5 );
add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/eco-cta-plugin.php' ), __NAMESPACE__ . '\\add_settings_link' );

/**
 * Add "Settings" link in the plugins list.
 *
 * @param string[] $links Existing links.
 * @return string[]
 */
function add_settings_link( array $links ): array {
    $url  = admin_url( 'options-general.php?page=eco-cta' );
    $link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'eco-cta' ) . '</a>';
    array_unshift( $links, $link );
    return $links;
}

function register_page(): void {
    add_options_page(
        __( 'Eco CTA Settings', 'eco-cta' ),
        __( 'Eco CTA', 'eco-cta' ),
        'manage_options',
        'eco-cta',
        __NAMESPACE__ . '\\render_page'
    );
}

function register_settings(): void {
    register_setting(
        'eco_cta_group',
        OPTION_KEY,
        [
            'type'              => 'array',
            'sanitize_callback' => 'EcoCTA\\Helpers\\sanitize',
            'default'           => \EcoCTA\Helpers\defaults(),
        ]
    );
}

/**
 * Parse the combo "taxonomy:term_id" values from the select element before
 * the main sanitize callback runs.
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

    $settings     = get_settings();
    $tax_options  = get_taxonomy_options();
    $public_types = get_post_types( [ 'public' => true ], 'objects' );
    unset( $public_types['attachment'] );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( '⚡ Eco CTA Plugin', 'eco-cta' ); ?>
            <span style="font-size:12px;color:#999;margin-left:8px">v<?php echo esc_html( VERSION ); ?></span>
        </h1>
        <p style="color:#666">
            <?php esc_html_e( 'Inject dynamic CTAs into content by post type, taxonomy, or category.', 'eco-cta' ); ?>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields( 'eco_cta_group' ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enabled', 'eco-cta' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( OPTION_KEY ); ?>[enabled]" value="1"
                                <?php checked( $settings['enabled'] ); ?>>
                            <?php esc_html_e( 'Inject CTAs into content', 'eco-cta' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enabled Post Types', 'eco-cta' ); ?></th>
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
                            <?php esc_html_e( 'The plugin only runs on selected post types.', 'eco-cta' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Inline insert after paragraph #', 'eco-cta' ); ?></th>
                    <td>
                        <input type="number" min="1" max="50"
                            name="<?php echo esc_attr( OPTION_KEY ); ?>[insert_after_paragraph]"
                            value="<?php echo esc_attr( (string) $settings['insert_after_paragraph'] ); ?>"
                            style="width:80px">
                        <p class="description">
                            <?php esc_html_e( 'If the post has fewer paragraphs, the CTA is appended at the end.', 'eco-cta' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 style="margin-top:2em"><?php esc_html_e( 'Configured CTAs', 'eco-cta' ); ?></h2>
            <p>
                <?php esc_html_e( 'Each CTA can filter by post type and/or taxonomy. No filters = applies to all enabled content.', 'eco-cta' ); ?>
            </p>

            <div id="eco-cta-list">
                <?php foreach ( $settings['ctas'] as $i => $cta ) : ?>
                    <?php render_cta_row( $i, $cta, $tax_options, $public_types ); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" id="eco-add-cta" class="button" style="margin-top:1em">
                + <?php esc_html_e( 'Add CTA', 'eco-cta' ); ?>
            </button>

            <p style="margin-top:2em">
                <?php submit_button( __( 'Save Changes', 'eco-cta' ), 'primary', 'submit', false ); ?>
            </p>
        </form>
    </div>

    <template id="eco-cta-template">
        <?php render_cta_row( '__INDEX__', [], $tax_options, $public_types ); ?>
    </template>

    <style>
        .eco-cta-row{border:1px solid #ddd;padding:16px;margin-bottom:16px;border-radius:4px;background:#fafafa}
        .eco-cta-row h3{margin:0 0 12px}
        .eco-cta-row .eco-cta-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .eco-cta-row label{display:block;font-weight:600;margin-bottom:4px;font-size:13px}
        .eco-cta-row input[type=text],.eco-cta-row textarea,.eco-cta-row select,.eco-cta-row input[type=url],.eco-cta-row input[type=color]{width:100%}
        .eco-remove-cta{float:right;color:#a00!important;cursor:pointer;text-decoration:none!important}
    </style>
    <script>
    (function(){
        let idx=<?php echo count( $settings['ctas'] ); ?>;
        document.getElementById('eco-add-cta').addEventListener('click',function(){
            const h=document.getElementById('eco-cta-template').innerHTML.replace(/__INDEX__/g,idx++);
            const d=document.createElement('div');d.innerHTML=h;
            document.getElementById('eco-cta-list').appendChild(d.firstElementChild);
        });
        document.getElementById('eco-cta-list').addEventListener('click',function(e){
            if(e.target.classList.contains('eco-remove-cta')){e.preventDefault();e.target.closest('.eco-cta-row').remove();}
        });
    })();
    </script>
    <?php
}

/* ─────────────────────────────────────────────
   Render — Single CTA row
───────────────────────────────────────────── */

/**
 * @param int|string                 $i            Row index (or '__INDEX__' for template).
 * @param array<string, mixed>       $cta          CTA data.
 * @param array<string, array>       $tax_options  Taxonomy options.
 * @param array<string, \WP_Post_Type> $public_types Public post types.
 */
function render_cta_row( int|string $i, array $cta, array $tax_options, array $public_types ): void {
    $cta = wp_parse_args( $cta, cta_defaults() );
    $n   = OPTION_KEY . "[ctas][{$i}]";
    ?>
    <div class="eco-cta-row">
        <h3>
            CTA #<?php echo is_numeric( $i ) ? (int) $i + 1 : '?'; ?>
            <a href="#" class="eco-remove-cta">✕ <?php esc_html_e( 'Remove', 'eco-cta' ); ?></a>
        </h3>
        <div class="eco-cta-fields">
            <div>
                <label><?php esc_html_e( 'Filter by Post Type', 'eco-cta' ); ?></label>
                <select name="<?php echo esc_attr( $n ); ?>[post_type]">
                    <option value=""><?php esc_html_e( '— All enabled —', 'eco-cta' ); ?></option>
                    <?php foreach ( $public_types as $pt ) : ?>
                        <option value="<?php echo esc_attr( $pt->name ); ?>"
                            <?php selected( $cta['post_type'], $pt->name ); ?>>
                            <?php echo esc_html( $pt->labels->singular_name ); ?> (<?php echo esc_html( $pt->name ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label><?php esc_html_e( 'Filter by Taxonomy / Term', 'eco-cta' ); ?></label>
                <select name="<?php echo esc_attr( $n ); ?>[term_id]">
                    <option value="0"><?php esc_html_e( '— No taxonomy filter —', 'eco-cta' ); ?></option>
                    <?php foreach ( $tax_options as $tax_name => $tax_data ) : ?>
                        <optgroup label="<?php echo esc_attr( $tax_data['label'] . ' (' . $tax_name . ')' ); ?>">
                            <?php foreach ( $tax_data['terms'] as $term ) :
                                $combo = $tax_name . ':' . $term->term_id;
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
                <label><?php esc_html_e( 'Accent color', 'eco-cta' ); ?></label>
                <input type="color" name="<?php echo esc_attr( $n ); ?>[accent_color]"
                    value="<?php echo esc_attr( $cta['accent_color'] ); ?>">
            </div>
            <div>
                <label><?php esc_html_e( 'Position', 'eco-cta' ); ?></label>
                <select name="<?php echo esc_attr( $n ); ?>[position]">
                    <option value="inline" <?php selected( $cta['position'], 'inline' ); ?>>📍 <?php esc_html_e( 'Inline (after paragraph N)', 'eco-cta' ); ?></option>
                    <option value="end"    <?php selected( $cta['position'], 'end' ); ?>>⬇️ <?php esc_html_e( 'End of post', 'eco-cta' ); ?></option>
                    <option value="both"   <?php selected( $cta['position'], 'both' ); ?>>📍⬇️ <?php esc_html_e( 'Both', 'eco-cta' ); ?></option>
                </select>
            </div>
            <div>
                <label><?php esc_html_e( 'CTA Title', 'eco-cta' ); ?></label>
                <input type="text" name="<?php echo esc_attr( $n ); ?>[title]"
                    value="<?php echo esc_attr( $cta['title'] ); ?>"
                    placeholder="<?php esc_attr_e( 'e.g. Looking for funding?', 'eco-cta' ); ?>">
            </div>
            <div>
                <label><?php esc_html_e( 'Button type', 'eco-cta' ); ?></label>
                <select name="<?php echo esc_attr( $n ); ?>[button_type]">
                    <option value="link"       <?php selected( $cta['button_type'], 'link' ); ?>>🔗 <?php esc_html_e( 'External link', 'eco-cta' ); ?></option>
                    <option value="newsletter" <?php selected( $cta['button_type'], 'newsletter' ); ?>>📧 <?php esc_html_e( 'Newsletter', 'eco-cta' ); ?></option>
                    <option value="community"  <?php selected( $cta['button_type'], 'community' ); ?>>👥 <?php esc_html_e( 'Community', 'eco-cta' ); ?></option>
                </select>
            </div>
            <div style="grid-column:span 2">
                <label><?php esc_html_e( 'CTA Text', 'eco-cta' ); ?></label>
                <textarea name="<?php echo esc_attr( $n ); ?>[text]" rows="2"
                    placeholder="<?php esc_attr_e( 'e.g. We send open calls directly to your inbox every week.', 'eco-cta' ); ?>"><?php echo esc_textarea( $cta['text'] ); ?></textarea>
            </div>
            <div>
                <label><?php esc_html_e( 'Button label', 'eco-cta' ); ?></label>
                <input type="text" name="<?php echo esc_attr( $n ); ?>[button_label]"
                    value="<?php echo esc_attr( $cta['button_label'] ); ?>"
                    placeholder="<?php esc_attr_e( 'e.g. Subscribe free', 'eco-cta' ); ?>">
            </div>
            <div>
                <label><?php esc_html_e( 'Button URL', 'eco-cta' ); ?></label>
                <input type="url" name="<?php echo esc_attr( $n ); ?>[button_url]"
                    value="<?php echo esc_attr( $cta['button_url'] ); ?>"
                    placeholder="https://...">
            </div>
        </div>
    </div>
    <?php
}
