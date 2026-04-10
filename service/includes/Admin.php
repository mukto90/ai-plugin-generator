<?php
/**
 * Admin settings screen for configuring AI provider credentials.
 *
 * The field names match what Providers\AI_Provider reads from
 * the plugindaddy_service_settings option: {slug}_api_key and {slug}_model.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Admin {

	const OPTION = 'plugindaddy_service_settings';
	const SLUG   = 'plugindaddy-service';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'PluginDaddy Service', 'plugindaddy-service' ),
			__( 'PluginDaddy', 'plugindaddy-service' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' ),
			'dashicons-superhero-alt',
			58
		);
	}

	public function register_settings() {
		register_setting(
			self::OPTION,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();
		$keys  = array(
			'openai_api_key', 'openai_model',
			'deepseek_api_key', 'deepseek_model',
			'claude_api_key', 'claude_model',
		);
		foreach ( $keys as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$clean[ $k ] = sanitize_text_field( $input[ $k ] );
			}
		}
		return $clean;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$opts = get_option( self::OPTION, array() );
		$get  = function ( $key ) use ( $opts ) {
			return isset( $opts[ $key ] ) ? $opts[ $key ] : '';
		};
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PluginDaddy Service — AI Providers', 'plugindaddy-service' ); ?></h1>
			<p><?php esc_html_e( 'Configure the real AI provider credentials used to fulfill incoming generation requests. Trial keys are routed to DeepSeek, Pro keys to OpenAI, and Studio keys to Claude.', 'plugindaddy-service' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION ); ?>

				<h2><?php esc_html_e( 'OpenAI', 'plugindaddy-service' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="openai_api_key"><?php esc_html_e( 'API Key', 'plugindaddy-service' ); ?></label></th>
						<td><input type="password" class="regular-text" id="openai_api_key" name="<?php echo esc_attr( self::OPTION ); ?>[openai_api_key]" value="<?php echo esc_attr( $get( 'openai_api_key' ) ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="openai_model"><?php esc_html_e( 'Model', 'plugindaddy-service' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="openai_model" name="<?php echo esc_attr( self::OPTION ); ?>[openai_model]" value="<?php echo esc_attr( $get( 'openai_model' ) ); ?>" placeholder="gpt-4o">
							<p class="description"><?php esc_html_e( 'Leave empty to use the default (gpt-4o).', 'plugindaddy-service' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'DeepSeek', 'plugindaddy-service' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="deepseek_api_key"><?php esc_html_e( 'API Key', 'plugindaddy-service' ); ?></label></th>
						<td><input type="password" class="regular-text" id="deepseek_api_key" name="<?php echo esc_attr( self::OPTION ); ?>[deepseek_api_key]" value="<?php echo esc_attr( $get( 'deepseek_api_key' ) ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="deepseek_model"><?php esc_html_e( 'Model', 'plugindaddy-service' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="deepseek_model" name="<?php echo esc_attr( self::OPTION ); ?>[deepseek_model]" value="<?php echo esc_attr( $get( 'deepseek_model' ) ); ?>" placeholder="deepseek-chat">
							<p class="description"><?php esc_html_e( 'Leave empty to use the default (deepseek-chat).', 'plugindaddy-service' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Claude (Anthropic)', 'plugindaddy-service' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="claude_api_key"><?php esc_html_e( 'API Key', 'plugindaddy-service' ); ?></label></th>
						<td><input type="password" class="regular-text" id="claude_api_key" name="<?php echo esc_attr( self::OPTION ); ?>[claude_api_key]" value="<?php echo esc_attr( $get( 'claude_api_key' ) ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="claude_model"><?php esc_html_e( 'Model', 'plugindaddy-service' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="claude_model" name="<?php echo esc_attr( self::OPTION ); ?>[claude_model]" value="<?php echo esc_attr( $get( 'claude_model' ) ); ?>" placeholder="claude-sonnet-4-6">
							<p class="description"><?php esc_html_e( 'Leave empty to use the default (claude-sonnet-4-6).', 'plugindaddy-service' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
