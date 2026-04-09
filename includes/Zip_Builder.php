<?php

namespace A_Plugin_Generator;

use WP_Error;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zip_Builder {

	/**
	 * Build a zip from files data.
	 *
	 * @param string       $slug       Plugin slug.
	 * @param array|string $files      Either a JSON-encoded array of {filename, code} objects,
	 *                                 or a raw code string (single file fallback).
	 * @param string       $upload_dir Upload directory path.
	 * @return string|WP_Error Zip file path or error.
	 */
	public function build( $slug, $files, $upload_dir ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'aipg_no_zip', __( 'ZipArchive extension is not available on this server.', 'ai-plugin-generator' ) );
		}

		$zip_path = trailingslashit( $upload_dir ) . $slug . '.zip';

		$zip    = new ZipArchive();
		$result = $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		if ( true !== $result ) {
			return new WP_Error( 'aipg_zip_error', __( 'Failed to create zip file.', 'ai-plugin-generator' ) );
		}

		// Handle array of files (multi-file).
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				$filename = isset( $file['filename'] ) ? $file['filename'] : $slug . '.php';
				$code     = isset( $file['code'] ) ? $file['code'] : '';
				$zip->addFromString( $slug . '/' . $filename, $code );
			}
		} else {
			// Single string fallback.
			$zip->addFromString( $slug . '/' . $slug . '.php', $files );
		}

		$zip->close();

		return $zip_path;
	}
}
