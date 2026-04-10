<?php
/**
 * Creates custom tables on activation.
 *
 * - {prefix}plugindaddy_keys — persistent API keys + quota counters (EDD-issued).
 * - {prefix}plugindaddy_requests — log of every generate request.
 *
 * Trial keys issued via /keys/request still live in short-lived transients.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Installer {

	const DB_VERSION_OPTION = 'plugindaddy_service_db_version';
	const DB_VERSION        = '1.0.0';

	public static function activate() {
		self::create_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	public static function keys_table() {
		global $wpdb;
		return $wpdb->prefix . 'plugindaddy_keys';
	}

	public static function requests_table() {
		global $wpdb;
		return $wpdb->prefix . 'plugindaddy_requests';
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$keys    = self::keys_table();
		$reqs    = self::requests_table();

		$sql_keys = "CREATE TABLE {$keys} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL,
			key_hash CHAR(64) NOT NULL,
			plan VARCHAR(32) NOT NULL DEFAULT 'trial',
			quota_limit INT UNSIGNED NOT NULL DEFAULT 0,
			quota_used INT UNSIGNED NOT NULL DEFAULT 0,
			edd_customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			edd_subscription_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			expires_at DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY key_hash (key_hash),
			KEY email (email),
			KEY edd_customer_id (edd_customer_id)
		) {$charset};";

		$sql_reqs = "CREATE TABLE {$reqs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			key_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			email VARCHAR(190) NOT NULL,
			plan VARCHAR(32) NOT NULL DEFAULT '',
			plugin_name VARCHAR(255) NOT NULL DEFAULT '',
			plugin_slug VARCHAR(200) NOT NULL DEFAULT '',
			requirements LONGTEXT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'pending',
			error_code VARCHAR(64) NOT NULL DEFAULT '',
			error_message TEXT NULL,
			response_bytes INT UNSIGNED NOT NULL DEFAULT 0,
			duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
			provider VARCHAR(32) NOT NULL DEFAULT '',
			ip VARCHAR(45) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY key_id (key_id),
			KEY created_at (created_at),
			KEY status (status)
		) {$charset};";

		dbDelta( $sql_keys );
		dbDelta( $sql_reqs );
	}
}
