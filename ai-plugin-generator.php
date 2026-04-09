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
 * Autoload plugin classes.
 */
spl_autoload_register( function ( $class ) {
	$namespace = 'A_Plugin_Generator\\';

	if ( strpos( $class, $namespace ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, strlen( $namespace ) );
	$parts          = explode( '\\', $relative_class );
	$class_name     = array_pop( $parts );

	// Convert class name: Provider_Interface -> interface-provider, OpenAI_Provider -> class-openai-provider.
	$is_interface = strpos( $class_name, '_Interface' ) !== false || $class_name === 'AI_Provider';
	if ( $is_interface ) {
		$file_name = 'interface-' . strtolower( str_replace( '_', '-', str_replace( '_Interface', '', $class_name ) ) ) . '.php';
	} else {
		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
	}

	// Build path from sub-namespaces.
	$sub_path = '';
	if ( ! empty( $parts ) ) {
		$sub_path = strtolower( implode( '/', $parts ) ) . '/';
	}

	// Admin classes live in admin/ directory, everything else in includes/.
	$base_dir = AIPG_PLUGIN_DIR;
	if ( ! empty( $parts ) && strtolower( $parts[0] ) === 'admin' ) {
		$base_dir .= strtolower( array_shift( $parts ) ) . '/';
		$sub_path  = ! empty( $parts ) ? strtolower( implode( '/', $parts ) ) . '/' : '';
	} else {
		$base_dir .= 'includes/';
	}

	$file = $base_dir . $sub_path . $file_name;

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

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
