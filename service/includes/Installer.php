<?php
/**
 * Creates custom tables on activation.
 *
 * - {prefix}plugindaddy_credits  — grants ledger (free + paid).
 * - {prefix}plugindaddy_plugins  — log of successfully generated plugins.
 *
 * Usage of credits is not stored here; it is derived from the plugins table.
 * Free allowance is computed via a rolling window from settings.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Installer {

	const DB_VERSION_OPTION = 'plugindaddy_service_db_version';
	const DB_VERSION        = '2.0.0';

	public static function activate() {
		self::create_tables();
		self::drop_legacy_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
			self::drop_legacy_tables();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	public static function credits_table() {
		global $wpdb;
		return $wpdb->prefix . 'plugindaddy_credits';
	}

	public static function plugins_table() {
		global $wpdb;
		return $wpdb->prefix . 'plugindaddy_plugins';
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$credits = self::credits_table();
		$plugins = self::plugins_table();

		$sql_credits = "CREATE TABLE {$credits} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			tier VARCHAR(16) NOT NULL DEFAULT 'paid',
			amount INT UNSIGNED NOT NULL DEFAULT 0,
			source VARCHAR(32) NOT NULL DEFAULT '',
			edd_payment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			edd_price_id INT UNSIGNED NOT NULL DEFAULT 0,
			note VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY tier (tier),
			KEY edd_payment_id (edd_payment_id)
		) {$charset};";

		$sql_plugins = "CREATE TABLE {$plugins} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			plugin_name VARCHAR(255) NOT NULL DEFAULT '',
			plugin_slug VARCHAR(200) NOT NULL DEFAULT '',
			description LONGTEXT NULL,
			tier VARCHAR(16) NOT NULL DEFAULT 'free',
			provider VARCHAR(32) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY tier_user (user_id, tier, created_at),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_credits );
		dbDelta( $sql_plugins );
	}

	/**
	 * Drop tables from the 1.x schema if they still exist.
	 */
	private static function drop_legacy_tables() {
		global $wpdb;
		$legacy = array(
			$wpdb->prefix . 'plugindaddy_keys',
			$wpdb->prefix . 'plugindaddy_requests',
		);
		foreach ( $legacy as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		delete_option( 'plugindaddy_keys' );
	}
}
