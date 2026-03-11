<?php
/**
 * Plugin Name:       Lean CTAs
 * Plugin URI:        https://github.com/ctala/lean-ctas
 * Description:       Lightweight dynamic CTAs injected into post content by post type, taxonomy, or category. Zero dependencies.
 * Version:           2.1.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Cristian Tala
 * Author URI:        https://cristiantala.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lean-ctas
 * Domain Path:       /languages
 */


namespace LeanCTAs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERSION    = '2.1.1';
const OPTION_KEY = 'lean_ctas_settings';
const SLUG       = 'lean-ctas';

/* ─────────────────────────────────────────────
   Bootstrap
───────────────────────────────────────────── */

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/frontend.php';

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

function activate(): void {
    if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__( 'Lean CTAs requires PHP 8.1 or higher.', 'lean-ctas' ),
            'Plugin Activation Error',
            [ 'back_link' => true ]
        );
    }

    if ( false === get_option( OPTION_KEY ) ) {
        add_option( OPTION_KEY, Helpers\defaults() );
    }

    // Migrate from eco-cta if upgrading.
    $legacy = get_option( 'eco_cta_settings' );
    if ( false !== $legacy && false === get_option( OPTION_KEY ) ) {
        add_option( OPTION_KEY, $legacy );
        delete_option( 'eco_cta_settings' );
    }
}
