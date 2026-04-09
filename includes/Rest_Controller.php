<?php

namespace A_Plugin_Generator;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Controller {

	private $namespace = 'aipg/v1';

	public function register_routes() {

		// Plugins CRUD.
		register_rest_route(
			$this->namespace,
			'/plugins',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_plugins' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_plugin' ),
					'permission_callback' => array( $this, 'check_admin' ),
					'args'                => $this->get_generate_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/plugins/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_plugin' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_plugin' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_plugin' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		// Confirm (package zip).
		register_rest_route(
			$this->namespace,
			'/plugins/(?P<id>\d+)/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'confirm_plugin' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Download.
		register_rest_route(
			$this->namespace,
			'/plugins/(?P<id>\d+)/download',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'download_plugin' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Install.
		register_rest_route(
			$this->namespace,
			'/plugins/(?P<id>\d+)/install',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'install_plugin' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Activate.
		register_rest_route(
			$this->namespace,
			'/plugins/(?P<id>\d+)/activate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'activate_plugin' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Deactivate.
		register_rest_route(
			$this->namespace,
			'/plugins/(?P<id>\d+)/deactivate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'deactivate_plugin' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Replace (reinstall with updated zip).
		register_rest_route(
			$this->namespace,
			'/plugins/(?P<id>\d+)/replace',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'replace_plugin' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Settings.
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		// Providers list.
		register_rest_route(
			$this->namespace,
			'/providers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_providers' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
	}

	/**
	 * Permission check: current user can manage_options.
	 */
	public function check_admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /plugins
	 */
	public function get_plugins( WP_REST_Request $request ) {
		$manager = new Plugin_Manager();

		$args = array(
			'orderby' => $request->get_param( 'orderby' ) ?? 'created_at',
			'order'   => $request->get_param( 'order' ) ?? 'DESC',
			'limit'   => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
			'offset'  => (int) ( $request->get_param( 'offset' ) ?? 0 ),
			'search'  => $request->get_param( 'search' ) ?? '',
		);

		$plugins = $manager->get_all( $args );
		$total   = $manager->get_total( $args['search'] );

		$installer = new Plugin_Installer();
		$items     = array();

		foreach ( $plugins as $plugin ) {
			$item                  = (array) $plugin;
			$item['installed']     = $installer->is_installed( $plugin );
			$item['active']        = $installer->is_active( $plugin );
			$item['needs_replace'] = $item['installed'] && $installer->needs_replace( $plugin );
			$items[]               = $item;
		}

		return new WP_REST_Response(
			array(
				'items' => $items,
				'total' => $total,
			),
			200
		);
	}

	/**
	 * POST /plugins — Generate plugin code via AI.
	 */
	public function generate_plugin( WP_REST_Request $request ) {
		$plugin_data = array(
			'name'         => $request->get_param( 'name' ),
			'slug'         => $request->get_param( 'slug' ),
			'version'      => $request->get_param( 'version' ) ?? '1.0.0',
			'author'       => $request->get_param( 'author' ) ?? '',
			'description'  => $request->get_param( 'description' ) ?? '',
			'requirements' => $request->get_param( 'requirements' ),
		);

		$generator = new Code_Generator();
		$code      = $generator->generate( $plugin_data );

		if ( is_wp_error( $code ) ) {
			return $code;
		}

		return new WP_REST_Response(
			array(
				'code'        => $code,
				'plugin_data' => $plugin_data,
			),
			200
		);
	}

	/**
	 * POST /plugins/{id}/confirm — Save to DB and create zip.
	 * If id > 0, updates existing record. If id == 0, creates new.
	 */
	public function confirm_plugin( WP_REST_Request $request ) {
		$id          = (int) $request['id'];
		$files       = $request->get_param( 'files' );
		$plugin_data = $request->get_param( 'plugin_data' );

		if ( empty( $files ) || empty( $plugin_data ) ) {
			return new WP_Error( 'aipg_missing_data', __( 'Missing files or plugin data.', 'ai-plugin-generator' ), array( 'status' => 400 ) );
		}

		$manager     = new Plugin_Manager();
		$zip_builder = new Zip_Builder();
		$upload_dir  = $manager->get_upload_dir();

		// Build zip from files array.
		$slug     = sanitize_title( $plugin_data['slug'] );
		$zip_path = $zip_builder->build( $slug, $files, $upload_dir );
		if ( is_wp_error( $zip_path ) ) {
			return $zip_path;
		}

		if ( $id > 0 ) {
			// Update existing plugin record.
			$existing = $manager->get( $id );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}

			$update_data = array(
				'name'         => sanitize_text_field( $plugin_data['name'] ),
				'version'      => sanitize_text_field( $plugin_data['version'] ?? $existing->version ),
				'author'       => sanitize_text_field( $plugin_data['author'] ?? $existing->author ),
				'description'  => sanitize_textarea_field( $plugin_data['description'] ?? $existing->description ),
				'requirements' => sanitize_textarea_field( $plugin_data['requirements'] ?? $existing->requirements ),
				'file_path'    => $zip_path,
			);

			$plugin = $manager->update( $id, $update_data );
			if ( is_wp_error( $plugin ) ) {
				return $plugin;
			}

			return new WP_REST_Response( (array) $plugin, 200 );
		}

		// Create new record.
		$plugin_data['file_path'] = $zip_path;
		$plugin = $manager->create( $plugin_data );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		return new WP_REST_Response( (array) $plugin, 201 );
	}

	/**
	 * GET /plugins/{id}
	 */
	public function get_plugin( WP_REST_Request $request ) {
		$manager = new Plugin_Manager();
		$plugin  = $manager->get( (int) $request['id'] );

		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$installer          = new Plugin_Installer();
		$item               = (array) $plugin;
		$item['installed']  = $installer->is_installed( $plugin );
		$item['active']     = $installer->is_active( $plugin );

		return new WP_REST_Response( $item, 200 );
	}

	/**
	 * PUT /plugins/{id} — Update requirements and regenerate.
	 */
	public function update_plugin( WP_REST_Request $request ) {
		$manager = new Plugin_Manager();
		$id      = (int) $request['id'];

		$plugin = $manager->get( $id );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$update_data = array();

		foreach ( array( 'name', 'version', 'author', 'description', 'requirements' ) as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$update_data[ $field ] = $value;
			}
		}

		// If requirements changed, regenerate code.
		$regenerate = $request->get_param( 'regenerate' );
		if ( $regenerate ) {
			$plugin_data = array(
				'name'         => $update_data['name'] ?? $plugin->name,
				'slug'         => $plugin->slug,
				'version'      => $update_data['version'] ?? $plugin->version,
				'author'       => $update_data['author'] ?? $plugin->author,
				'description'  => $update_data['description'] ?? $plugin->description,
				'requirements' => $update_data['requirements'] ?? $plugin->requirements,
			);

			$generator = new Code_Generator();
			$code      = $generator->generate( $plugin_data );

			if ( is_wp_error( $code ) ) {
				return $code;
			}

			// Rebuild zip.
			$zip_builder = new Zip_Builder();
			$zip_path    = $zip_builder->build( $plugin->slug, $code, $manager->get_upload_dir() );

			if ( is_wp_error( $zip_path ) ) {
				return $zip_path;
			}

			$update_data['file_path'] = $zip_path;

			return new WP_REST_Response(
				array(
					'code'   => $code,
					'plugin' => (array) $manager->update( $id, $update_data ),
				),
				200
			);
		}

		$updated = $manager->update( $id, $update_data );

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return new WP_REST_Response( (array) $updated, 200 );
	}

	/**
	 * DELETE /plugins/{id}
	 */
	public function delete_plugin( WP_REST_Request $request ) {
		$manager = new Plugin_Manager();
		$result  = $manager->delete( (int) $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * GET /plugins/{id}/download
	 */
	public function download_plugin( WP_REST_Request $request ) {
		$manager = new Plugin_Manager();
		$plugin  = $manager->get( (int) $request['id'] );

		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		if ( empty( $plugin->file_path ) || ! file_exists( $plugin->file_path ) ) {
			return new WP_Error( 'aipg_no_file', __( 'Plugin zip file not found.', 'ai-plugin-generator' ), array( 'status' => 404 ) );
		}

		// Return download URL.
		$upload_dir  = wp_upload_dir();
		$relative    = str_replace( $upload_dir['basedir'], '', $plugin->file_path );
		$download_url = $upload_dir['baseurl'] . $relative;

		return new WP_REST_Response(
			array(
				'download_url' => $download_url,
				'file_name'    => basename( $plugin->file_path ),
			),
			200
		);
	}

	/**
	 * POST /plugins/{id}/install
	 */
	public function install_plugin( WP_REST_Request $request ) {
		$manager   = new Plugin_Manager();
		$installer = new Plugin_Installer();

		$plugin = $manager->get( (int) $request['id'] );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$result = $installer->install( $plugin );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$manager->update( (int) $request['id'], array( 'status' => 'installed' ) );

		return new WP_REST_Response( array( 'installed' => true ), 200 );
	}

	/**
	 * POST /plugins/{id}/activate
	 */
	public function activate_plugin( WP_REST_Request $request ) {
		$manager   = new Plugin_Manager();
		$installer = new Plugin_Installer();

		$plugin = $manager->get( (int) $request['id'] );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$result = $installer->activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$manager->update( (int) $request['id'], array( 'status' => 'active' ) );

		return new WP_REST_Response( array( 'activated' => true ), 200 );
	}

	/**
	 * POST /plugins/{id}/deactivate
	 */
	public function deactivate_plugin( WP_REST_Request $request ) {
		$manager   = new Plugin_Manager();
		$installer = new Plugin_Installer();

		$plugin = $manager->get( (int) $request['id'] );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		$result = $installer->deactivate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$manager->update( (int) $request['id'], array( 'status' => 'installed' ) );

		return new WP_REST_Response( array( 'deactivated' => true ), 200 );
	}

	/**
	 * POST /plugins/{id}/replace — Replace installed plugin with updated zip.
	 * Deactivates if active, removes old files, reinstalls from zip, reactivates if was active.
	 */
	public function replace_plugin( WP_REST_Request $request ) {
		$manager   = new Plugin_Manager();
		$installer = new Plugin_Installer();

		$plugin = $manager->get( (int) $request['id'] );
		if ( is_wp_error( $plugin ) ) {
			return $plugin;
		}

		if ( ! $installer->is_installed( $plugin ) ) {
			return new WP_Error( 'aipg_not_installed', __( 'Plugin is not installed.', 'ai-plugin-generator' ), array( 'status' => 400 ) );
		}

		$was_active = $installer->is_active( $plugin );

		// Deactivate if active.
		if ( $was_active ) {
			$installer->deactivate_plugin( $plugin );
		}

		// Remove old plugin files.
		$installer->uninstall_plugin( $plugin );

		// Reinstall from zip.
		$result = $installer->install( $plugin );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Reactivate if it was active before.
		if ( $was_active ) {
			$activate_result = $installer->activate_plugin( $plugin );
			if ( is_wp_error( $activate_result ) ) {
				$manager->update( (int) $request['id'], array( 'status' => 'installed' ) );
				return new WP_REST_Response( array( 'replaced' => true, 'reactivated' => false ), 200 );
			}
			$manager->update( (int) $request['id'], array( 'status' => 'active' ) );
		} else {
			$manager->update( (int) $request['id'], array( 'status' => 'installed' ) );
		}

		return new WP_REST_Response( array( 'replaced' => true, 'reactivated' => $was_active ), 200 );
	}

	/**
	 * GET /settings
	 */
	public function get_settings() {
		$settings = get_option( 'aipg_settings', array() );

		// Mask API key for security.
		if ( ! empty( $settings['api_key'] ) ) {
			$key = $settings['api_key'];
			$settings['api_key_masked'] = substr( $key, 0, 4 ) . str_repeat( '*', max( 0, strlen( $key ) - 8 ) ) . substr( $key, -4 );
			$settings['has_api_key']    = true;
		} else {
			$settings['api_key_masked'] = '';
			$settings['has_api_key']    = false;
		}

		unset( $settings['api_key'] );

		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * PUT /settings
	 */
	public function update_settings( WP_REST_Request $request ) {
		$settings = get_option( 'aipg_settings', array() );

		$provider = $request->get_param( 'provider' );
		if ( null !== $provider ) {
			$settings['provider'] = sanitize_text_field( $provider );
		}

		$api_key = $request->get_param( 'api_key' );
		if ( null !== $api_key && ! empty( $api_key ) ) {
			$settings['api_key'] = sanitize_text_field( $api_key );
		}

		$model = $request->get_param( 'model' );
		if ( null !== $model ) {
			$settings['model'] = sanitize_text_field( $model );
		}

		update_option( 'aipg_settings', $settings );

		// Return masked settings.
		return $this->get_settings();
	}

	/**
	 * GET /providers
	 */
	public function get_providers() {
		return new WP_REST_Response( Code_Generator::get_available_providers(), 200 );
	}

	/**
	 * Argument schema for generate endpoint.
	 */
	private function get_generate_args() {
		return array(
			'name'         => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'slug'         => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
			),
			'requirements' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'version'      => array(
				'type'              => 'string',
				'default'           => '1.0.0',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'author'       => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'  => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}
}
