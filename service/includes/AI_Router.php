<?php
/**
 * Picks an AI provider + model based on whether the caller is on the
 * free or paid tier, then dispatches the prompt.
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

	/**
	 * @param string $tier   'free' | 'paid'
	 * @param array  $prompt { system, user }
	 * @return array|\WP_Error { provider_slug, text }
	 */
	public function dispatch( $tier, array $prompt ) {
		$provider = $this->pick_provider( $tier );
		if ( ! $provider ) {
			return new \WP_Error( 'plugindaddy_no_provider', __( 'No AI provider is configured for this tier. Please contact the administrator.', 'plugindaddy-service' ), array( 'status' => 500 ) );
		}

		$text = $provider->generate( $prompt['system'], $prompt['user'] );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return array(
			'provider_slug' => $provider->get_slug(),
			'text'          => $text,
		);
	}

	private function pick_provider( $tier ) {
		$settings = get_option( 'plugindaddy_service_settings', array() );

		$provider_key = ( 'paid' === $tier ) ? 'paid_provider' : 'free_provider';
		$model_key    = ( 'paid' === $tier ) ? 'paid_model' : 'free_model';

		$slug  = isset( $settings[ $provider_key ] ) ? $settings[ $provider_key ] : '';
		$model = isset( $settings[ $model_key ] ) ? $settings[ $model_key ] : '';

		$class = $this->class_for_slug( $slug );
		if ( ! $class ) {
			return null;
		}

		// The tier's model override is injected as {slug}_model so the
		// base class picks it up in its constructor.
		if ( ! empty( $model ) ) {
			$settings[ $slug . '_model' ] = $model;
		}

		return new $class( $settings );
	}

	private function class_for_slug( $slug ) {
		switch ( $slug ) {
			case 'openai':
				return OpenAI::class;
			case 'deepseek':
				return DeepSeek::class;
			case 'claude':
				return Claude::class;
		}
		return null;
	}
}
