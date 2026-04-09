<?php
/**
 * Plugin Name: AI Plugin Generator
 * Plugin URI:  https://example.com/ai-plugin-generator
 * Description: Generate WordPress plugins using AI. Describe what you need, and AI writes the code.
 * Version:     1.0.0
 * Author:      AI Plugin Generator
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-plugin-generator
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package A_Plugin_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIPG_VERSION', '1.0.0' );
define( 'AIPG_PLUGIN_FILE', __FILE__ );
define( 'AIPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIPG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Composer autoloader.
 */
if ( file_exists( AIPG_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once AIPG_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, function () {
	A_Plugin_Generator\Activator::activate();
} );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, function () {
	A_Plugin_Generator\Deactivator::deactivate();
} );

/**
 * Boot the plugin.
 */
add_action( 'plugins_loaded', function () {
	A_Plugin_Generator\Plugin::get_instance();
} );
