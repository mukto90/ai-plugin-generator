<?php

namespace A_Plugin_Generator;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin_Manager {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'aipg_plugins';
	}

	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 20,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'name', 'slug', 'status', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			)
		);

		return $results ? $results : array();
	}

	public function get_total() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
	}

	public function get( $id ) {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
		);

		if ( ! $result ) {
			return new WP_Error( 'aipg_not_found', __( 'Plugin not found.', 'ai-plugin-generator' ), array( 'status' => 404 ) );
		}

		return $result;
	}

	public function get_by_slug( $slug ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE slug = %s", $slug )
		);
	}

	public function create( $data ) {
		global $wpdb;

		// Ensure unique slug.
		$slug          = sanitize_title( $data['slug'] );
		$original_slug = $slug;
		$counter       = 1;

		while ( $this->get_by_slug( $slug ) ) {
			$slug = $original_slug . '-' . $counter;
			$counter++;
		}

		$inserted = $wpdb->insert(
			$this->table_name,
			array(
				'name'         => sanitize_text_field( $data['name'] ),
				'slug'         => $slug,
				'version'      => sanitize_text_field( $data['version'] ?? '1.0.0' ),
				'author'       => sanitize_text_field( $data['author'] ?? '' ),
				'description'  => sanitize_textarea_field( $data['description'] ?? '' ),
				'requirements' => sanitize_textarea_field( $data['requirements'] ),
				'file_path'    => sanitize_text_field( $data['file_path'] ?? '' ),
				'status'       => 'generated',
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'aipg_db_error', __( 'Failed to save plugin.', 'ai-plugin-generator' ) );
		}

		return $this->get( $wpdb->insert_id );
	}

	public function update( $id, $data ) {
		global $wpdb;

		$existing = $this->get( $id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);
		$formats = array( '%s' );

		$allowed_fields = array(
			'name'         => '%s',
			'version'      => '%s',
			'author'       => '%s',
			'description'  => '%s',
			'requirements' => '%s',
			'file_path'    => '%s',
			'status'       => '%s',
		);

		foreach ( $allowed_fields as $field => $format ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $field === 'requirements' || $field === 'description'
					? sanitize_textarea_field( $data[ $field ] )
					: sanitize_text_field( $data[ $field ] );
				$formats[] = $format;
			}
		}

		$result = $wpdb->update(
			$this->table_name,
			$update_data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'aipg_db_error', __( 'Failed to update plugin.', 'ai-plugin-generator' ) );
		}

		return $this->get( $id );
	}

	public function delete( $id ) {
		global $wpdb;

		$plugin = $this->get( $id );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		// Delete zip file if it exists.
		if ( ! empty( $plugin->file_path ) && file_exists( $plugin->file_path ) ) {
			wp_delete_file( $plugin->file_path );
		}

		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'aipg_db_error', __( 'Failed to delete plugin.', 'ai-plugin-generator' ) );
		}

		return true;
	}

	public function get_upload_dir() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/ai-plugin-generator';
	}
}
