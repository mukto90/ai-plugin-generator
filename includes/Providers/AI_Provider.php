<?php
/**
 * Abstract base class for AI providers.
 *
 * @package A_Plugin_Generator
 */

namespace A_Plugin_Generator\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AI_Provider {

	/**
	 * Timeout in seconds for generation API calls.
	 *
	 * @var int
	 */
	protected $generate_timeout = 300;

	/**
	 * Timeout in seconds for validation API calls.
	 *
	 * @var int
	 */
	protected $validate_timeout = 30;

	/**
	 * API key.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Model identifier.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * Constructor. Loads API key and model from settings.
	 */
	public function __construct() {
		$settings      = get_option( 'aipg_settings', array() );
		$this->api_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';

		$default_model = $this->get_default_model();
		$this->model   = ! empty( $settings['model'] ) ? $settings['model'] : $default_model;
	}

	/**
	 * Get the provider display name.
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Get the provider slug/identifier.
	 *
	 * @return string
	 */
	abstract public function get_slug();

	/**
	 * Get the default model for this provider.
	 *
	 * @return string
	 */
	abstract protected function get_default_model();

	/**
	 * Generate plugin code from requirements.
	 *
	 * @param array $plugin_data Plugin details.
	 * @return string|WP_Error Generated code or error.
	 */
	abstract public function generate( $plugin_data );

	/**
	 * Validate an API key.
	 *
	 * @param string $api_key The API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	abstract public function validate_api_key( $api_key );

	/**
	 * Check that the API key is configured.
	 *
	 * @return true|WP_Error
	 */
	protected function check_api_key() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'aipg_no_api_key',
				/* translators: %s: provider name */
				sprintf( __( '%s API key is not configured.', 'ai-plugin-generator' ), $this->get_name() )
			);
		}
		return true;
	}

	/**
	 * Parse API response and return error if status is not 200.
	 *
	 * @param array|WP_Error $response  The wp_remote response.
	 * @return array|WP_Error Decoded body array or error.
	 */
	protected function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unknown API error.', 'ai-plugin-generator' );
			return new WP_Error( 'aipg_api_error', $message );
		}

		return $body;
	}

	/**
	 * Get the system prompt shared by all providers.
	 *
	 * @return string
	 */
	protected function get_system_prompt() {
		return 'You are a WordPress plugin developer. Generate complete, production-ready WordPress plugin code. ' .
			'Follow WordPress Coding Standards (WPCS) strictly for PHP, CSS, and JavaScript. ' .
			'Use proper sanitization, escaping, and nonces. ' .
			'If the plugin needs frontend output, use clean, modern UI/UX with good typography, spacing, and colors. ' .
			'Do NOT use Composer or any external dependencies — the generated plugin must be fully self-contained. ' .
			'For each file, output a heading line like: === filename.php === followed by a ```php code block. ' .
			'If the plugin is simple enough for a single file, output: === plugin-slug.php === followed by the code block. ' .
			'Include the plugin header comment with all provided metadata. ' .
			'The code must be secure, well-structured, and ready to use. ' .
			'CRITICAL: Before responding, carefully review your generated code for syntax errors, undefined functions, ' .
			'missing closing brackets, incorrect hook usage, and any issues that could cause a fatal error or site crash. ' .
			'The plugin will be installed directly on a live WordPress site — it MUST work without errors.';
	}

	/**
	 * Build the user prompt from plugin data.
	 *
	 * @param array $plugin_data Plugin details.
	 * @return string
	 */
	protected function build_prompt( $plugin_data ) {
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

	/**
	 * Extract code from AI response.
	 *
	 * @param string $content Raw AI response.
	 * @return string Trimmed content.
	 */
	protected function extract_code( $content ) {
		return trim( $content );
	}
}
