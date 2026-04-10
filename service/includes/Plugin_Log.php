<?php
/**
 * Writes rows to the plugindaddy_plugins table. Only successful generations
 * are logged — a row here doubles as the "this request consumed a credit"
 * marker that Credit_Manager reads to compute remaining balance.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Plugin_Log {

	/**
	 * @param array $data {
	 *     @type int    $user_id
	 *     @type string $plugin_name
	 *     @type string $plugin_slug
	 *     @type string $description
	 *     @type string $tier     'free' | 'paid'
	 *     @type string $provider
	 * }
	 * @return int Inserted row id (0 on failure).
	 */
	public function record( array $data ) {
		global $wpdb;

		$row = array(
			'user_id'     => isset( $data['user_id'] ) ? (int) $data['user_id'] : 0,
			'plugin_name' => isset( $data['plugin_name'] ) ? substr( (string) $data['plugin_name'], 0, 255 ) : '',
			'plugin_slug' => isset( $data['plugin_slug'] ) ? substr( (string) $data['plugin_slug'], 0, 200 ) : '',
			'description' => isset( $data['description'] ) ? (string) $data['description'] : '',
			'tier'        => isset( $data['tier'] ) ? substr( (string) $data['tier'], 0, 16 ) : 'free',
			'provider'    => isset( $data['provider'] ) ? substr( (string) $data['provider'], 0, 32 ) : '',
			'created_at'  => current_time( 'mysql', true ),
		);

		$inserted = $wpdb->insert(
			Installer::plugins_table(),
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}
}
