<?php
/**
 * OpenAI provider (service-side).
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service\Providers;

defined( 'ABSPATH' ) || exit;

class OpenAI extends AI_Provider {

	public function get_name() {
		return 'OpenAI';
	}

	public function get_slug() {
		return 'openai';
	}

	public function get_default_model() {
		return 'gpt-4o';
	}

	public function generate( $system_prompt, $user_prompt ) {
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => $this->generate_timeout,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $this->model,
						'max_tokens'  => 16000,
						'messages'    => array(
							array( 'role' => 'system', 'content' => $system_prompt ),
							array( 'role' => 'user', 'content' => $user_prompt ),
						),
					)
				),
			)
		);

		$data = $this->parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
	}
}
