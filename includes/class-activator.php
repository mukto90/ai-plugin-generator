<?php

namespace A_Plugin_Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {

	public static function activate() {
		self::create_table();
		self::create_upload_dir();
	}

	private static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'aipg_plugins';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			version varchar(50) NOT NULL DEFAULT '1.0.0',
			author varchar(255) NOT NULL DEFAULT '',
			description text NOT NULL DEFAULT '',
			requirements text NOT NULL,
			file_path varchar(500) NOT NULL DEFAULT '',
			status varchar(50) NOT NULL DEFAULT 'generated',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'aipg_db_version', AIPG_VERSION );
	}

	private static function create_upload_dir() {
		$upload_dir = wp_upload_dir();
		$aipg_dir   = $upload_dir['basedir'] . '/ai-plugin-generator';

		if ( ! file_exists( $aipg_dir ) ) {
			wp_mkdir_p( $aipg_dir );
		}

		// Protect directory from direct access.
		$htaccess = $aipg_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Options -Indexes\n" );
		}
	}
}
