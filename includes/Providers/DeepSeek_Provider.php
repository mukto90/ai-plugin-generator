<?php

namespace A_Plugin_Generator\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeepSeek_Provider implements AI_Provider {

	private $api_key;
	private $model;
	private $api_url = 'https://api.deepseek.com/chat/completions';

	public function __construct() {
		$settings      = get_option( 'aipg_settings', array() );
		$this->api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$this->model   = isset( $settings['model'] ) ? $settings['model'] : 'deepseek-chat';
	}

	public function get_name() {
		return 'DeepSeek';
	}

	public function get_slug() {
		return 'deepseek';
	}

	public function generate( $plugin_data ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'aipg_no_api_key', __( 'DeepSeek API key is not configured.', 'ai-plugin-generator' ) );
		}

		$prompt = $this->build_prompt( $plugin_data );

		$response = wp_remote_post(
			$this->api_url,
			array(
				'timeout' => 120,
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
								'content' => $prompt,
							),
						),
						'temperature' => 0.3,
						'max_tokens'  => 8192,
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

		if ( empty( $body['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'aipg_empty_response', __( 'AI returned an empty response.', 'ai-plugin-generator' ) );
		}

		return $this->extract_code( $body['choices'][0]['message']['content'] );
	}

	public function validate_api_key( $api_key ) {
		$response = wp_remote_post(
			$this->api_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'    => 'deepseek-chat',
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => 'Hi',
							),
						),
						'max_tokens' => 5,
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

		return new WP_Error( 'aipg_invalid_key', __( 'Invalid DeepSeek API key.', 'ai-plugin-generator' ) );
	}

	private function get_system_prompt() {
		return 'You are a WordPress plugin developer. Generate complete, production-ready WordPress plugin code. ' .
			'Follow WordPress Coding Standards (WPCS) strictly for PHP, CSS, and JavaScript. ' .
			'Use proper sanitization, escaping, and nonces. ' .
			'If the plugin needs frontend output, use clean, modern UI/UX with good typography, spacing, and colors. ' .
			'Do NOT use Composer or any external dependencies — the generated plugin must be fully self-contained. ' .
			'For each file, output a heading line like: === filename.php === followed by a ```php code block. ' .
			'If the plugin is simple enough for a single file, output: === plugin-slug.php === followed by the code block. ' .
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
		$prompt .= "\nIMPORTANT: Use === filename.php === headings before each file's code block. ";
		$prompt .= "Follow WordPress Coding Standards (WPCS) for all PHP, CSS, and JavaScript. ";
		$prompt .= "Do NOT use Composer or any external package manager — the plugin must be fully self-contained. ";
		$prompt .= "Include activation/deactivation hooks if needed. Use proper WordPress APIs. ";
		$prompt .= "If the plugin has any user-facing output, use modern, clean UI/UX with good design.";

		return $prompt;
	}

	private function extract_code( $content ) {
		return trim( $content );
	}
}
