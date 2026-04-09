<?php

namespace A_Plugin_Generator;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin_Installer {

	public function install( $plugin ) {
		if ( empty( $plugin->file_path ) || ! file_exists( $plugin->file_path ) ) {
			return new WP_Error( 'aipg_no_zip', __( 'Plugin zip file not found.', 'ai-plugin-generator' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		WP_Filesystem();

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $plugin->file_path );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}

		if ( ! $result ) {
			return new WP_Error( 'aipg_install_failed', __( 'Plugin installation failed.', 'ai-plugin-generator' ) );
		}

		return true;
	}

	public function activate_plugin( $plugin ) {
		$plugin_file = $plugin->slug . '/' . $plugin->slug . '.php';

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			return new WP_Error( 'aipg_not_installed', __( 'Plugin is not installed. Please install it first.', 'ai-plugin-generator' ) );
		}

		$result = activate_plugin( $plugin_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	public function deactivate_plugin( $plugin ) {
		$plugin_file = $plugin->slug . '/' . $plugin->slug . '.php';

		if ( ! is_plugin_active( $plugin_file ) ) {
			return new WP_Error( 'aipg_not_active', __( 'Plugin is not active.', 'ai-plugin-generator' ) );
		}

		deactivate_plugins( $plugin_file );

		return true;
	}

	public function is_installed( $plugin ) {
		$plugin_file = $plugin->slug . '/' . $plugin->slug . '.php';
		return file_exists( WP_PLUGIN_DIR . '/' . $plugin_file );
	}

	public function is_active( $plugin ) {
		$plugin_file = $plugin->slug . '/' . $plugin->slug . '.php';
		return is_plugin_active( $plugin_file );
	}
}
