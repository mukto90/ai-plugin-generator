<?php
/**
 * Google Gemini provider implementation.
 *
 * @package A_Plugin_Generator
 */

namespace A_Plugin_Generator\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gemini extends AI_Provider {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'Google Gemini';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'gemini';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_default_model() {
		return 'gemini-2.0-flash';
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate( $plugin_data ) {
		$check = $this->check_api_key();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$prompt   = $this->get_system_prompt() . "\n\n" . $this->build_prompt( $plugin_data );
		$endpoint = $this->api_url . $this->model . ':generateContent?key=' . $this->api_key;

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $this->generate_timeout,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'contents'         => array(
							array(
								'parts' => array(
									array( 'text' => $prompt ),
								),
							),
						),
						'generationConfig' => array(
							'temperature'     => 0.3,
							'maxOutputTokens' => 16000,
						),
					)
				),
			)
		);

		$body = $this->parse_response( $response );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return new WP_Error( 'aipg_empty_response', __( 'AI returned an empty response.', 'ai-plugin-generator' ) );
		}

		return $this->extract_code( $body['candidates'][0]['content']['parts'][0]['text'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate_api_key( $api_key ) {
		$endpoint = $this->api_url . 'gemini-2.0-flash:generateContent?key=' . $api_key;

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $this->validate_timeout,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'contents'         => array(
							array(
								'parts' => array(
									array( 'text' => 'Hi' ),
								),
							),
						),
						'generationConfig' => array(
							'maxOutputTokens' => 5,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			return true;
		}

		return new WP_Error( 'aipg_invalid_key', __( 'Invalid Gemini API key.', 'ai-plugin-generator' ) );
	}
}
