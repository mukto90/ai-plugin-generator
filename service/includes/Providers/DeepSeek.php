<?php
/**
 * DeepSeek provider (service-side).
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service\Providers;

defined( 'ABSPATH' ) || exit;

class DeepSeek extends AI_Provider {

	public function get_name() {
		return 'DeepSeek';
	}

	public function get_slug() {
		return 'deepseek';
	}

	public function get_default_model() {
		return 'deepseek-chat';
	}

	public function generate( $system_prompt, $user_prompt ) {
		$response = wp_remote_post(
			'https://api.deepseek.com/chat/completions',
			array(
				'timeout' => $this->generate_timeout,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $this->model,
						'max_tokens' => 8192,
						'messages'   => array(
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
