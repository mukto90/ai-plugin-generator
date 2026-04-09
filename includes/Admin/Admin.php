<?php

namespace A_Plugin_Generator\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menus() {
		add_menu_page(
			__( 'AI Plugin Generator', 'ai-plugin-generator' ),
			__( 'AI Plugins', 'ai-plugin-generator' ),
			'manage_options',
			'aipg-plugins',
			array( $this, 'render_list_page' ),
			'dashicons-superhero-alt',
			65
		);

		add_submenu_page(
			'aipg-plugins',
			__( 'All Plugins', 'ai-plugin-generator' ),
			__( 'All Plugins', 'ai-plugin-generator' ),
			'manage_options',
			'aipg-plugins',
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			'aipg-plugins',
			__( 'Create Plugin', 'ai-plugin-generator' ),
			__( 'Create New', 'ai-plugin-generator' ),
			'manage_options',
			'aipg-create',
			array( $this, 'render_create_page' )
		);

		add_submenu_page(
			'aipg-plugins',
			__( 'Settings', 'ai-plugin-generator' ),
			__( 'Settings', 'ai-plugin-generator' ),
			'manage_options',
			'aipg-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		$screens = array(
			'toplevel_page_aipg-plugins',
			'ai-plugins_page_aipg-create',
			'ai-plugins_page_aipg-settings',
		);

		if ( ! in_array( $hook, $screens, true ) ) {
			return;
		}

		wp_enqueue_style(
			'aipg-admin',
			AIPG_PLUGIN_URL . 'admin/css/admin-style.css',
			array(),
			AIPG_VERSION
		);

		// Localize common data for all scripts.
		$localize_data = array(
			'restUrl' => esc_url_raw( rest_url( 'aipg/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'error'          => __( 'An error occurred.', 'ai-plugin-generator' ),
				'confirm_delete' => __( 'Are you sure you want to delete this plugin?', 'ai-plugin-generator' ),
				'generating'     => __( 'Generating plugin code...', 'ai-plugin-generator' ),
				'saving'         => __( 'Saving...', 'ai-plugin-generator' ),
				'installing'     => __( 'Installing...', 'ai-plugin-generator' ),
				'success'        => __( 'Success!', 'ai-plugin-generator' ),
				'saved'          => __( 'Settings saved.', 'ai-plugin-generator' ),
			),
		);

		if ( $hook === 'ai-plugins_page_aipg-create' ) {
			wp_enqueue_script(
				'aipg-create',
				AIPG_PLUGIN_URL . 'admin/js/create-plugin.js',
				array( 'jquery' ),
				AIPG_VERSION,
				true
			);
			wp_localize_script( 'aipg-create', 'aipgData', $localize_data );
		}

		if ( $hook === 'toplevel_page_aipg-plugins' ) {
			wp_enqueue_script(
				'aipg-list',
				AIPG_PLUGIN_URL . 'admin/js/list-plugins.js',
				array( 'jquery' ),
				AIPG_VERSION,
				true
			);
			wp_localize_script( 'aipg-list', 'aipgData', $localize_data );
		}

		if ( $hook === 'ai-plugins_page_aipg-settings' ) {
			wp_enqueue_script(
				'aipg-settings',
				AIPG_PLUGIN_URL . 'admin/js/settings.js',
				array( 'jquery' ),
				AIPG_VERSION,
				true
			);
			wp_localize_script( 'aipg-settings', 'aipgData', $localize_data );
		}
	}

	public function render_list_page() {
		require AIPG_PLUGIN_DIR . 'admin/views/list-plugins.php';
	}

	public function render_create_page() {
		require AIPG_PLUGIN_DIR . 'admin/views/create-plugin.php';
	}

	public function render_settings_page() {
		require AIPG_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
