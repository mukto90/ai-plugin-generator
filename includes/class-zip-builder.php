<?php

namespace A_Plugin_Generator;

use WP_Error;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zip_Builder {

	public function build( $slug, $code, $upload_dir ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'aipg_no_zip', __( 'ZipArchive extension is not available on this server.', 'ai-plugin-generator' ) );
		}

		$zip_path = trailingslashit( $upload_dir ) . $slug . '.zip';

		$zip = new ZipArchive();
		$result = $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		if ( true !== $result ) {
			return new WP_Error( 'aipg_zip_error', __( 'Failed to create zip file.', 'ai-plugin-generator' ) );
		}

		// Add main plugin file inside a directory matching the slug.
		$zip->addFromString( $slug . '/' . $slug . '.php', $code );
		$zip->close();

		return $zip_path;
	}
}
