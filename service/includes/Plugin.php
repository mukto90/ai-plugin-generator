<?php
/**
 * Core plugin bootstrap.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( new Rest_Controller(), 'register_routes' ) );
		new EDD_Integration();

		if ( is_admin() ) {
			new Admin();
		}
	}
}
