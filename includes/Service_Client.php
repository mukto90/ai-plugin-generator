<?php
/**
 * HTTP client for the PluginDaddy service.
 * All AI-related calls from this plugin go through here.
 *
 * @package A_Plugin_Generator
 */

namespace A_Plugin_Generator;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Service_Client {

	const NAMESPACE_V1 = '/wp-json/plugindaddy/v1';

	protected $email;
	protected $api_key;
	protected $generate_timeout = 300;
	protected $verify_timeout   = 30;

	public function __construct() {
		$settings      = get_option( 'aipg_settings', array() );
		$this->email   = isset( $settings['email'] ) ? $settings['email'] : '';
		$this->api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
	}

	/**
	 * Send requirements to PluginDaddy and return raw generated text.
	 *
	 * @param array $plugin_data Plugin metadata + requirements.
	 * @return string|WP_Error
	 */
	public function generate( array $plugin_data ) {
		if ( empty( $this->email ) || empty( $this->api_key ) ) {
			return new WP_Error(
				'aipg_no_credentials',
				__( 'PluginDaddy API key is not configured. Please go to Settings and request an API key.', 'ai-plugin-generator' )
			);
		}

		$body = array(
			'email'        => $this->email,
			'api_key'      => $this->api_key,
			'requirements' => isset( $plugin_data['requirements'] ) ? $plugin_data['requirements'] : '',
			'meta'         => array(
				'name'        => isset( $plugin_data['name'] ) ? $plugin_data['name'] : '',
				'slug'        => isset( $plugin_data['slug'] ) ? $plugin_data['slug'] : '',
				'version'     => isset( $plugin_data['version'] ) ? $plugin_data['version'] : '1.0.0',
				'author'      => isset( $plugin_data['author'] ) ? $plugin_data['author'] : '',
				'description' => isset( $plugin_data['description'] ) ? $plugin_data['description'] : '',
			),
		);

		$response = wp_remote_post(
			$this->endpoint( '/plugin/generate' ),
			array(
				'timeout' => $this->generate_timeout,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		$data = $this->parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( isset( $data['code'] ) ) {
			return (string) $data['code'];
		}

		return new WP_Error( 'aipg_service_empty', __( 'The service returned an empty response.', 'ai-plugin-generator' ) );
	}

	/**
	 * Ask PluginDaddy to email a new API key to the given address.
	 *
	 * @param string $email
	 * @return true|WP_Error
	 */
	public function request_key( $email ) {
		$response = wp_remote_post(
			$this->endpoint( '/keys/request' ),
			array(
				'timeout' => $this->verify_timeout,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'email' => $email ) ),
			)
		);

		$data = $this->parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return true;
	}

	/**
	 * Verify an email + API key pair against the service.
	 *
	 * @param string $email
	 * @param string $api_key
	 * @return array|WP_Error The verification payload on success.
	 */
	public function verify_key( $email, $api_key ) {
		$response = wp_remote_post(
			$this->endpoint( '/keys/verify' ),
			array(
				'timeout' => $this->verify_timeout,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'email'   => $email,
						'api_key' => $api_key,
					)
				),
			)
		);

		$data = $this->parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['valid'] ) ) {
			return new WP_Error( 'aipg_invalid_key', __( 'The API key could not be verified.', 'ai-plugin-generator' ) );
		}

		return $data;
	}

	protected function endpoint( $path ) {
		return rtrim( AIPG_SERVICE_URL, '/' ) . self::NAMESPACE_V1 . $path;
	}

	protected function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 ) {
			$message = isset( $body['message'] ) ? $body['message'] : __( 'PluginDaddy service returned an error.', 'ai-plugin-generator' );
			return new WP_Error( 'aipg_service_http_' . $status, $message, array( 'status' => $status ) );
		}

		return is_array( $body ) ? $body : array();
	}
}
