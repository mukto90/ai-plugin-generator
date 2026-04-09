<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom table.
$table_name = $wpdb->prefix . 'aipg_plugins';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Remove options.
delete_option( 'aipg_settings' );
delete_option( 'aipg_db_version' );

// Remove upload directory.
$upload_dir = wp_upload_dir();
$aipg_dir   = $upload_dir['basedir'] . '/ai-plugin-generator';

if ( is_dir( $aipg_dir ) ) {
	$files = glob( $aipg_dir . '/*' );
	if ( $files ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
	rmdir( $aipg_dir );
}
