<?php
/**
 * OpenAI provider implementation.
 *
 * @package A_Plugin_Generator
 */

namespace A_Plugin_Generator\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenAI extends AI_Provider {

	/**
	 * API endpoint URL.
	 *
	 * @var string
	 */
	private $api_url = 'https://api.openai.com/v1/chat/completions';

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'OpenAI';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_slug() {
		return 'openai';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_default_model() {
		return 'gpt-4o';
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate( $plugin_data ) {
		$check = $this->check_api_key();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$response = wp_remote_post(
			$this->api_url,
			array(
				'timeout' => $this->generate_timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $this->model,
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => $this->get_system_prompt(),
							),
							array(
								'role'    => 'user',
								'content' => $this->build_prompt( $plugin_data ),
							),
						),
						'temperature' => 0.3,
						'max_tokens'  => 16000,
					)
				),
			)
		);

		$body = $this->parse_response( $response );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( empty( $body['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'aipg_empty_response', __( 'AI returned an empty response.', 'ai-plugin-generator' ) );
		}

		return $this->extract_code( $body['choices'][0]['message']['content'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate_api_key( $api_key ) {
		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'timeout' => $this->validate_timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			return true;
		}

		return new WP_Error( 'aipg_invalid_key', __( 'Invalid OpenAI API key.', 'ai-plugin-generator' ) );
	}
}
