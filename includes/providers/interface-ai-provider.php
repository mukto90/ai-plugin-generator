<?php

namespace A_Plugin_Generator\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AI_Provider {

	/**
	 * Get the provider display name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Get the provider slug/identifier.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Generate plugin code from requirements.
	 *
	 * @param array $plugin_data {
	 *     @type string $name         Plugin name.
	 *     @type string $slug         Plugin slug.
	 *     @type string $version      Plugin version.
	 *     @type string $author       Plugin author.
	 *     @type string $description  Plugin description.
	 *     @type string $requirements Detailed requirements.
	 * }
	 * @return string|WP_Error Generated PHP code or error.
	 */
	public function generate( $plugin_data );

	/**
	 * Validate the API key.
	 *
	 * @param string $api_key The API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_api_key( $api_key );
}
