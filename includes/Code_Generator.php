<?php

namespace A_Plugin_Generator;

use A_Plugin_Generator\Providers\AI_Provider;
use A_Plugin_Generator\Providers\OpenAI_Provider;
use A_Plugin_Generator\Providers\DeepSeek_Provider;
use A_Plugin_Generator\Providers\Gemini_Provider;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Code_Generator {

	private static $providers = array(
		'openai'   => OpenAI_Provider::class,
		'deepseek' => DeepSeek_Provider::class,
		'gemini'   => Gemini_Provider::class,
	);

	public function generate( $plugin_data ) {
		$provider = $this->get_provider();

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		return $provider->generate( $plugin_data );
	}

	public function get_provider() {
		$settings = get_option( 'aipg_settings', array() );
		$slug     = isset( $settings['provider'] ) ? $settings['provider'] : '';

		if ( empty( $slug ) || ! isset( self::$providers[ $slug ] ) ) {
			return new WP_Error(
				'aipg_no_provider',
				__( 'No AI provider configured. Please go to Settings and select a provider.', 'ai-plugin-generator' )
			);
		}

		$class = self::$providers[ $slug ];
		return new $class();
	}

	public static function get_available_providers() {
		$list = array();
		foreach ( self::$providers as $slug => $class ) {
			$instance = new $class();
			$list[ $slug ] = $instance->get_name();
		}
		return $list;
	}

	public static function validate_provider_key( $provider_slug, $api_key ) {
		if ( ! isset( self::$providers[ $provider_slug ] ) ) {
			return new WP_Error( 'aipg_invalid_provider', __( 'Invalid provider.', 'ai-plugin-generator' ) );
		}

		$class    = self::$providers[ $provider_slug ];
		$instance = new $class();
		return $instance->validate_api_key( $api_key );
	}
}
