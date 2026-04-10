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
		$email   = sanitize_email( $request->get_param( 'email' ) );
		$api_key = sanitize_text_field( $request->get_param( 'api_key' ) );
		$reqs    = wp_kses_post( $request->get_param( 'requirements' ) );
		$meta    = (array) $request->get_param( 'meta' );

		$users   = new User_Manager();
		$user_id = $users->authenticate( $email, $api_key );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$credits = new Credit_Manager();
		$tier    = $credits->select_tier_to_charge( $user_id );
		if ( null === $tier ) {
			return new \WP_Error(
				'plugindaddy_quota',
				__( 'You have no credits remaining. Please purchase a plan or wait for your free allowance to refresh.', 'plugindaddy-service' ),
				array( 'status' => 429 )
			);
		}

		$prompt   = ( new Prompt_Builder() )->build( $reqs, $meta );
		$router   = new AI_Router();
		$dispatch = $router->dispatch( $tier, $prompt );
		if ( is_wp_error( $dispatch ) ) {
			return $dispatch;
		}

		// Only successful generations are logged; a log row is the credit
		// consumption record that Credit_Manager reads.
		( new Plugin_Log() )->record(
			array(
				'user_id'     => $user_id,
				'plugin_name' => isset( $meta['name'] ) ? (string) $meta['name'] : '',
				'plugin_slug' => isset( $meta['slug'] ) ? (string) $meta['slug'] : '',
				'description' => $reqs,
				'tier'        => $tier,
				'provider'    => $dispatch['provider_slug'],
			)
		);

		return rest_ensure_response(
			array( 'code' => $dispatch['text'] )
		);
	}

	public function request_key( \WP_REST_Request $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );

		$result = ( new User_Manager() )->issue_and_email( $email );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function verify_key( \WP_REST_Request $request ) {
		$email   = sanitize_email( $request->get_param( 'email' ) );
		$api_key = sanitize_text_field( $request->get_param( 'api_key' ) );

		$users  = new User_Manager();
		$result = $users->verify_and_provision( $email, $api_key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$credits = new Credit_Manager();
		return rest_ensure_response(
			array(
				'valid'          => true,
				'user_id'        => $result['user_id'],
				'free_available' => $credits->free_available( $result['user_id'] ),
				'paid_available' => $credits->paid_available( $result['user_id'] ),
			)
		);
	}
}
