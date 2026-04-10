<?php
/**
 * Chooses an AI provider based on the API key's plan and forwards the prompt.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

use PluginDaddy_Service\Providers\AI_Provider;
use PluginDaddy_Service\Providers\OpenAI;
use PluginDaddy_Service\Providers\DeepSeek;
use PluginDaddy_Service\Providers\Claude;

defined( 'ABSPATH' ) || exit;

class AI_Router {

	public function dispatch( array $ctx, array $prompt ) {
		$provider = $this->pick_provider( $ctx );
		if ( ! $provider ) {
			return new \WP_Error( 'plugindaddy_no_provider', __( 'No AI provider is configured.', 'plugindaddy-service' ), array( 'status' => 500 ) );
		}
		return $provider->generate( $prompt['system'], $prompt['user'] );
	}

	private function pick_provider( array $ctx ) {
		$settings = get_option( 'plugindaddy_service_settings', array() );

		switch ( $ctx['plan'] ) {
			case 'studio':
				return new Claude( $settings );
			case 'pro':
				return new OpenAI( $settings );
			case 'trial':
			default:
				return new DeepSeek( $settings );
		}
	}
}
