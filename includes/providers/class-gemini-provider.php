<?php

namespace A_Plugin_Generator\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gemini_Provider implements AI_Provider {

	private $api_key;
	private $model;
	private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/';

	public function __construct() {
		$settings      = get_option( 'aipg_settings', array() );
		$this->api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$this->model   = isset( $settings['model'] ) ? $settings['model'] : 'gemini-2.0-flash';
	}

	public function get_name() {
		return 'Google Gemini';
	}

	public function get_slug() {
		return 'gemini';
	}

	public function generate( $plugin_data ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'aipg_no_api_key', __( 'Gemini API key is not configured.', 'ai-plugin-generator' ) );
		}

		$prompt   = $this->get_system_prompt() . "\n\n" . $this->build_prompt( $plugin_data );
		$endpoint = $this->api_url . $this->model . ':generateContent?key=' . $this->api_key;

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 120,
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
							'temperature'   => 0.3,
							'maxOutputTokens' => 16000,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unknown API error.', 'ai-plugin-generator' );
			return new WP_Error( 'aipg_api_error', $message );
		}

		if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return new WP_Error( 'aipg_empty_response', __( 'AI returned an empty response.', 'ai-plugin-generator' ) );
		}

		return $this->extract_code( $body['candidates'][0]['content']['parts'][0]['text'] );
	}

	public function validate_api_key( $api_key ) {
		$endpoint = $this->api_url . 'gemini-2.0-flash:generateContent?key=' . $api_key;

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'contents' => array(
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

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 200 ) {
			return true;
		}

		return new WP_Error( 'aipg_invalid_key', __( 'Invalid Gemini API key.', 'ai-plugin-generator' ) );
	}

	private function get_system_prompt() {
		return 'You are a WordPress plugin developer. Generate complete, production-ready WordPress plugin code. ' .
			'Follow WordPress Coding Standards (WPCS). Use proper sanitization, escaping, and nonces. ' .
			'Return ONLY the PHP code for the main plugin file wrapped in ```php code blocks. ' .
			'Include the plugin header comment with all provided metadata. ' .
			'The code must be secure, well-structured, and ready to use.';
	}

	private function build_prompt( $plugin_data ) {
		$prompt = "Generate a WordPress plugin with the following details:\n\n";
		$prompt .= "Plugin Name: {$plugin_data['name']}\n";
		$prompt .= "Plugin Slug: {$plugin_data['slug']}\n";
		$prompt .= "Version: {$plugin_data['version']}\n";

		if ( ! empty( $plugin_data['author'] ) ) {
			$prompt .= "Author: {$plugin_data['author']}\n";
		}

		if ( ! empty( $plugin_data['description'] ) ) {
			$prompt .= "Description: {$plugin_data['description']}\n";
		}

		$prompt .= "\nRequirements:\n{$plugin_data['requirements']}\n";
		$prompt .= "\nIMPORTANT: Return the complete plugin code in a single PHP file. ";
		$prompt .= "Include activation/deactivation hooks if needed. Use proper WordPress APIs.";

		return $prompt;
	}

	private function extract_code( $content ) {
		if ( preg_match( '/```php\s*(.*?)\s*```/s', $content, $matches ) ) {
			return $matches[1];
		}

		if ( strpos( trim( $content ), '<?php' ) === 0 ) {
			return trim( $content );
		}

		return $content;
	}
}
