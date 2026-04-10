<?php
/**
 * Plugin Name: PluginDaddy Service
 * Description: Backend service that accepts plugin generation requests from the AI Plugin Generator plugin, authenticates via API key, and forwards to a real AI provider (OpenAI/DeepSeek/Claude). Integrates with EDD + EDD Recurring for API key lifecycle.
 * Version:     0.1.0
 * Author:      PluginDaddy
 * License:     GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Requires Plugins: easy-digital-downloads
 *
 * @package PluginDaddy_Service
 */

defined( 'ABSPATH' ) || exit;

define( 'PLUGINDADDY_SERVICE_VERSION', '0.1.0' );
define( 'PLUGINDADDY_SERVICE_FILE', __FILE__ );
define( 'PLUGINDADDY_SERVICE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PLUGINDADDY_SERVICE_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class ) {
		$prefix = 'PluginDaddy_Service\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = PLUGINDADDY_SERVICE_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook(
	__FILE__,
	function () {
		\PluginDaddy_Service\Installer::activate();
	}
);

add_action(
	'plugins_loaded',
	function () {
		\PluginDaddy_Service\Installer::maybe_upgrade();

		if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'PluginDaddy Service requires Easy Digital Downloads to be installed and active.', 'plugindaddy-service' );
					echo '</p></div>';
				}
			);
			return;
		}
		\PluginDaddy_Service\Plugin::instance();
	}
);
