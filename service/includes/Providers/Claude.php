<?php
/**
 * Anthropic Claude provider (service-side).
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service\Providers;

defined( 'ABSPATH' ) || exit;

class Claude extends AI_Provider {

	public function get_name() {
		return 'Claude';
	}

	public function get_slug() {
		return 'claude';
	}

	public function get_default_model() {
		return 'claude-sonnet-4-6';
	}

	public function generate( $system_prompt, $user_prompt ) {
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => $this->generate_timeout,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $this->model,
						'max_tokens' => 16000,
						'system'     => $system_prompt,
						'messages'   => array(
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
		if ( ! empty( $data['content'][0]['text'] ) ) {
			return $data['content'][0]['text'];
		}
		return '';
	}
}
