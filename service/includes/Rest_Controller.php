<?php
/**
 * REST API endpoints for the PluginDaddy service.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Rest_Controller {

	const NAMESPACE_V1 = 'plugindaddy/v1';

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/plugin/generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'api_key'      => array( 'required' => true, 'type' => 'string' ),
					'email'        => array( 'required' => true, 'type' => 'string' ),
					'requirements' => array( 'required' => true, 'type' => 'string' ),
					'meta'         => array( 'required' => false, 'type' => 'object' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/keys/request',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'request_key' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/keys/verify',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'verify_key' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'   => array( 'required' => true, 'type' => 'string' ),
					'api_key' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
	}

	public function generate( \WP_REST_Request $request ) {
		$started = microtime( true );
		$email   = sanitize_email( $request->get_param( 'email' ) );
		$api_key = sanitize_text_field( $request->get_param( 'api_key' ) );
		$reqs    = wp_kses_post( $request->get_param( 'requirements' ) );
		$meta    = (array) $request->get_param( 'meta' );

		$log = new Request_Log();
		$log_base = array(
			'email'        => $email,
			'plugin_name'  => isset( $meta['name'] ) ? (string) $meta['name'] : '',
			'plugin_slug'  => isset( $meta['slug'] ) ? (string) $meta['slug'] : '',
			'requirements' => $reqs,
		);

		$keys = new Key_Manager();
		$ctx  = $keys->verify( $email, $api_key );
		if ( is_wp_error( $ctx ) ) {
			$log->record(
				array_merge(
					$log_base,
					array(
						'status'        => 'error',
						'error_code'    => $ctx->get_error_code(),
						'error_message' => $ctx->get_error_message(),
						'duration_ms'   => (int) ( ( microtime( true ) - $started ) * 1000 ),
					)
				)
			);
			return $ctx;
		}

		$log_base['key_id'] = isset( $ctx['id'] ) && is_int( $ctx['id'] ) ? (int) $ctx['id'] : 0;
		$log_base['plan']   = isset( $ctx['plan'] ) ? (string) $ctx['plan'] : '';

		if ( ! $keys->consume_quota( $ctx ) ) {
			$log->record(
				array_merge(
					$log_base,
					array(
						'status'        => 'error',
						'error_code'    => 'plugindaddy_quota',
						'error_message' => 'Quota exceeded',
						'duration_ms'   => (int) ( ( microtime( true ) - $started ) * 1000 ),
					)
				)
			);
			return new \WP_Error( 'plugindaddy_quota', __( 'Quota exceeded for this API key.', 'plugindaddy-service' ), array( 'status' => 429 ) );
		}

		$prompt = ( new Prompt_Builder() )->build( $reqs, $meta );
		$router = new AI_Router();
		$result = $router->dispatch( $ctx, $prompt );

		if ( is_wp_error( $result ) ) {
			$log->record(
				array_merge(
					$log_base,
					array(
						'status'        => 'error',
						'error_code'    => $result->get_error_code(),
						'error_message' => $result->get_error_message(),
						'duration_ms'   => (int) ( ( microtime( true ) - $started ) * 1000 ),
					)
				)
			);
			return $result;
		}

		$log->record(
			array_merge(
				$log_base,
				array(
					'status'         => 'success',
					'response_bytes' => strlen( (string) $result ),
					'duration_ms'    => (int) ( ( microtime( true ) - $started ) * 1000 ),
				)
			)
		);

		return rest_ensure_response(
			array(
				'code' => $result,
			)
		);
	}

	public function request_key( \WP_REST_Request $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'plugindaddy_invalid_email', __( 'Invalid email address.', 'plugindaddy-service' ), array( 'status' => 400 ) );
		}

		$result = ( new Key_Manager() )->issue_and_email( $email );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array( 'ok' => true )
		);
	}

	public function verify_key( \WP_REST_Request $request ) {
		$email   = sanitize_email( $request->get_param( 'email' ) );
		$api_key = sanitize_text_field( $request->get_param( 'api_key' ) );

		$ctx = ( new Key_Manager() )->verify( $email, $api_key );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}

		return rest_ensure_response(
			array(
				'valid'      => true,
				'plan'       => $ctx['plan'],
				'expires_at' => $ctx['expires_at'],
			)
		);
	}
}
