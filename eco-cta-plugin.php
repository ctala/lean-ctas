<?php
/**
 * Plugin Name:       Eco CTA Plugin
 * Plugin URI:        https://github.com/ctala/eco-cta-plugin
 * Description:       Lightweight dynamic CTAs injected into post content by post type, taxonomy, or category. Zero dependencies.
 * Version:           1.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Cristian Tala
 * Author URI:        https://cristiantala.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eco-cta
 * Domain Path:       /languages
 */

declare( strict_types=1 );

namespace EcoCTA;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERSION    = '1.3.0';
const OPTION_KEY = 'eco_cta_settings';
const SLUG       = 'eco-cta';

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
            esc_html__( 'Eco CTA Plugin requires PHP 8.1 or higher.', 'eco-cta' ),
            'Plugin Activation Error',
            [ 'back_link' => true ]
        );
    }

    if ( false === get_option( OPTION_KEY ) ) {
        add_option( OPTION_KEY, Helpers\defaults() );
    }
}
