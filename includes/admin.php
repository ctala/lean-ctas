<?php

declare( strict_types=1 );
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
                <tr>
                    <th scope="row"><?php esc_html_e( 'Listmonk URL', 'lean-ctas' ); ?></th>
                    <td>
                        <input type="url"
                            name="<?php echo esc_attr( OPTION_KEY ); ?>[listmonk_url]"
                            value="<?php echo esc_attr( $settings['listmonk_url'] ?? '' ); ?>"
                            placeholder="https://listmonk.example.com"
                            style="width:360px">
                        <p class="description">
                            <?php esc_html_e( 'Base URL of your Listmonk instance. Used by opt-in form CTAs. Editable so the same plugin works across sites and survives a Listmonk migration.', 'lean-ctas' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Capture webhook URL (n8n)', 'lean-ctas' ); ?></th>
                    <td>
                        <input type="url"
                            name="<?php echo esc_attr( OPTION_KEY ); ?>[capture_webhook_url]"
                            value="<?php echo esc_attr( $settings['capture_webhook_url'] ?? '' ); ?>"
                            placeholder="https://n8n.example.com/webhook/…"
                            autocomplete="off"
                            style="width:360px">
                        <p class="description">
                            <?php esc_html_e( 'Optional. If set, opt-in submissions POST here (n8n handles Listmonk + GA4 + plumbing). Falls back to direct Listmonk if the webhook fails. Server-side only — never exposed to the browser.', 'lean-ctas' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Listmonk API user', 'lean-ctas' ); ?></th>
                    <td>
                        <input type="text"
                            name="<?php echo esc_attr( OPTION_KEY ); ?>[listmonk_api_user]"
                            value="<?php echo esc_attr( $settings['listmonk_api_user'] ?? '' ); ?>"
                            autocomplete="off"
                            style="width:360px">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Listmonk API token', 'lean-ctas' ); ?></th>
                    <td>
                        <input type="password"
                            name="<?php echo esc_attr( OPTION_KEY ); ?>[listmonk_api_token]"
                            value="<?php echo esc_attr( $settings['listmonk_api_token'] ?? '' ); ?>"
                            autocomplete="new-password"
                            style="width:360px">
                        <p class="description">
                            <?php esc_html_e( 'Optional. The opt-in subscription flow uses the public endpoint (no auth). These credentials are stored for admin features / future use (e.g. fetching list names). Stored server-side, never sent to the browser.', 'lean-ctas' ); ?>
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

        // Toggle URL vs optin fields based on button_type selection.
        function syncOptinFields(row){
            var sel=row.querySelector('.lean-btn-type-select');
            if(!sel)return;
            var isForm=sel.value==='optin_form';
            row.querySelectorAll('.lean-field-url').forEach(function(el){el.style.display=isForm?'none':'';});
            row.querySelectorAll('.lean-field-optin').forEach(function(el){el.style.display=isForm?'':'none';});
        }

        document.getElementById('lean-cta-list').addEventListener('change',function(e){
            if(e.target.classList.contains('lean-btn-type-select')){
                syncOptinFields(e.target.closest('.lean-cta-row'));
            }
        });

        document.getElementById('lean-add-cta').addEventListener('click',function(){
            const h=document.getElementById('lean-cta-template').innerHTML.replace(/__INDEX__/g,idx++);
            const d=document.createElement('div');d.innerHTML=h;
            var row=d.firstElementChild;
            document.getElementById('lean-cta-list').appendChild(row);
            syncOptinFields(row);
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
                    <option value="manual" <?php selected( $cta['position'], 'manual' ); ?>>🧩 <?php esc_html_e( 'Manual — shortcode only [lean_cta optin="1"]', 'lean-ctas' ); ?></option>
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
                <select name="<?php echo esc_attr( $n ); ?>[button_type]" class="lean-btn-type-select"
                    data-row="<?php echo esc_attr( (string) $i ); ?>">
                    <option value="link"       <?php selected( $cta['button_type'], 'link' ); ?>>🔗 <?php esc_html_e( 'External link', 'lean-ctas' ); ?></option>
                    <option value="newsletter" <?php selected( $cta['button_type'], 'newsletter' ); ?>>📧 <?php esc_html_e( 'Newsletter', 'lean-ctas' ); ?></option>
                    <option value="community"  <?php selected( $cta['button_type'], 'community' ); ?>>👥 <?php esc_html_e( 'Community', 'lean-ctas' ); ?></option>
                    <option value="optin_form" <?php selected( $cta['button_type'], 'optin_form' ); ?>>✉️ <?php esc_html_e( 'Opt-in form (Listmonk)', 'lean-ctas' ); ?></option>
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
            <div class="lean-field-url" <?php echo $cta['button_type'] === 'optin_form' ? 'style="display:none"' : ''; ?>>
                <label><?php esc_html_e( 'Button URL', 'lean-ctas' ); ?></label>
                <input type="url" name="<?php echo esc_attr( $n ); ?>[button_url]"
                    value="<?php echo esc_attr( $cta['button_url'] ); ?>"
                    placeholder="https://...">
            </div>
            <div class="lean-field-optin" <?php echo $cta['button_type'] !== 'optin_form' ? 'style="display:none"' : ''; ?>>
                <label><?php esc_html_e( 'Listmonk List UUID', 'lean-ctas' ); ?></label>
                <input type="text" name="<?php echo esc_attr( $n ); ?>[optin_list_uuid]"
                    value="<?php echo esc_attr( $cta['optin_list_uuid'] ?? '' ); ?>"
                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                <p class="description" style="font-size:11px;margin-top:4px">
                    <?php esc_html_e( 'UUID of the Listmonk list. Found in Listmonk → Lists → Edit.', 'lean-ctas' ); ?>
                </p>
            </div>
            <div class="lean-field-optin" style="grid-column:span 2;<?php echo $cta['button_type'] !== 'optin_form' ? 'display:none' : ''; ?>">
                <label><?php esc_html_e( 'Success message', 'lean-ctas' ); ?></label>
                <input type="text" name="<?php echo esc_attr( $n ); ?>[optin_success_msg]"
                    value="<?php echo esc_attr( $cta['optin_success_msg'] ?? '' ); ?>"
                    placeholder="<?php esc_attr_e( 'Check your email to confirm your subscription.', 'lean-ctas' ); ?>">
            </div>
        </div>
    </div>
    <?php
}
