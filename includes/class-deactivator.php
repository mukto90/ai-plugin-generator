<?php

namespace A_Plugin_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deactivator {

	public static function deactivate() {
		// Nothing to do on deactivation for now.
		// Cleanup happens in uninstall.php.
	}
}
