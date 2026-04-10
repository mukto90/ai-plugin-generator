<?php
/**
 * Inserts rows into the plugindaddy_requests table.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Request_Log {

	/**
	 * @param array $data {
	 *     @type int    $key_id
	 *     @type string $email
	 *     @type string $plan
	 *     @type string $plugin_name
	 *     @type string $plugin_slug
	 *     @type string $requirements
	 *     @type string $status         'success' | 'error' | 'pending'
	 *     @type string $error_code
	 *     @type string $error_message
	 *     @type int    $response_bytes
	 *     @type int    $duration_ms
	 *     @type string $provider
	 * }
	 * @return int Inserted row id (0 on failure).
	 */
	public function record( array $data ) {
		global $wpdb;

		$row = array(
			'key_id'         => isset( $data['key_id'] ) ? (int) $data['key_id'] : 0,
			'email'          => isset( $data['email'] ) ? substr( (string) $data['email'], 0, 190 ) : '',
			'plan'           => isset( $data['plan'] ) ? substr( (string) $data['plan'], 0, 32 ) : '',
			'plugin_name'    => isset( $data['plugin_name'] ) ? substr( (string) $data['plugin_name'], 0, 255 ) : '',
			'plugin_slug'    => isset( $data['plugin_slug'] ) ? substr( (string) $data['plugin_slug'], 0, 200 ) : '',
			'requirements'   => isset( $data['requirements'] ) ? (string) $data['requirements'] : '',
			'status'         => isset( $data['status'] ) ? substr( (string) $data['status'], 0, 32 ) : 'pending',
			'error_code'     => isset( $data['error_code'] ) ? substr( (string) $data['error_code'], 0, 64 ) : '',
			'error_message'  => isset( $data['error_message'] ) ? (string) $data['error_message'] : '',
			'response_bytes' => isset( $data['response_bytes'] ) ? (int) $data['response_bytes'] : 0,
			'duration_ms'    => isset( $data['duration_ms'] ) ? (int) $data['duration_ms'] : 0,
			'provider'       => isset( $data['provider'] ) ? substr( (string) $data['provider'], 0, 32 ) : '',
			'ip'             => $this->client_ip(),
			'created_at'     => current_time( 'mysql', true ),
		);

		$inserted = $wpdb->insert(
			Installer::requests_table(),
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return substr( sanitize_text_field( $ip ), 0, 45 );
	}
}
