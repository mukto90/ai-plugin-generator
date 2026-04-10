<?php
/**
 * Abstract base class for AI providers on the service side.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service\Providers;

defined( 'ABSPATH' ) || exit;

abstract class AI_Provider {

	protected $api_key = '';
	protected $model   = '';
	protected $generate_timeout = 300;

	public function __construct( array $settings = array() ) {
		$slug = $this->get_slug();
		$this->api_key = isset( $settings[ $slug . '_api_key' ] ) ? $settings[ $slug . '_api_key' ] : '';
		$this->model   = isset( $settings[ $slug . '_model' ] ) ? $settings[ $slug . '_model' ] : $this->get_default_model();
	}

	abstract public function get_name();
	abstract public function get_slug();
	abstract public function get_default_model();

	/**
	 * Generate plugin code from a system + user prompt.
	 *
	 * @param string $system_prompt
	 * @param string $user_prompt
	 * @return string|\WP_Error Raw generated text on success.
	 */
	abstract public function generate( $system_prompt, $user_prompt );

	protected function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'plugindaddy_provider_http_' . $code,
				sprintf(
					/* translators: 1: provider name, 2: HTTP status, 3: body */
					__( '%1$s returned HTTP %2$d: %3$s', 'plugindaddy-service' ),
					$this->get_name(),
					$code,
					wp_strip_all_tags( $body )
				)
			);
		}
		return json_decode( $body, true );
	}
}
