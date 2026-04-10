<?php
/**
 * Thin orchestrator around Service_Client.
 *
 * @package A_Plugin_Generator
 */

namespace A_Plugin_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Code_Generator {

	public function generate( $plugin_data ) {
		$client = new Service_Client();
		return $client->generate( (array) $plugin_data );
	}
}
