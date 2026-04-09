<?php

namespace A_Plugin_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init();
	}

	private function init() {
		// REST API.
		add_action( 'rest_api_init', array( new Rest_Controller(), 'register_routes' ) );

		// Admin.
		if ( is_admin() ) {
			new Admin\Admin();
		}
	}
}
