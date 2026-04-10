<?php
/**
 * Admin menu, settings screen, and plugins log screen.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Admin {

	const OPTION    = 'plugindaddy_service_settings';
	const MENU_SLUG = 'plugindaddy-service';
	const LOG_SLUG  = 'plugindaddy-plugins';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'PluginDaddy', 'plugindaddy-service' ),
			__( 'PluginDaddy', 'plugindaddy-service' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-superhero-alt',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'plugindaddy-service' ),
			__( 'Settings', 'plugindaddy-service' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Plugins Log', 'plugindaddy-service' ),
			__( 'Plugins Log', 'plugindaddy-service' ),
			'manage_options',
			self::LOG_SLUG,
			array( $this, 'render_log_page' )
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
		$input  = is_array( $input ) ? $input : array();
		$clean  = array();
		$fields = array(
			'openai_api_key', 'deepseek_api_key', 'claude_api_key',
			'free_provider', 'free_model',
			'paid_provider', 'paid_model',
			'free_period',
		);
		foreach ( $fields as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$clean[ $k ] = sanitize_text_field( $input[ $k ] );
			}
		}

		$clean['free_allowance'] = isset( $input['free_allowance'] ) ? max( 0, (int) $input['free_allowance'] ) : 1;
		$clean['edd_product_id'] = isset( $input['edd_product_id'] ) ? max( 0, (int) $input['edd_product_id'] ) : 0;

		if ( ! in_array( $clean['free_period'] ?? 'month', array( 'day', 'week', 'month', 'year' ), true ) ) {
			$clean['free_period'] = 'month';
		}
		foreach ( array( 'free_provider', 'paid_provider' ) as $k ) {
			if ( ! in_array( $clean[ $k ] ?? '', array( 'openai', 'deepseek', 'claude' ), true ) ) {
				$clean[ $k ] = 'deepseek';
			}
		}

		return $clean;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$opts = get_option( self::OPTION, array() );
		$get  = function ( $k, $d = '' ) use ( $opts ) {
			return isset( $opts[ $k ] ) ? $opts[ $k ] : $d;
		};

		$providers = array(
			'openai'   => 'OpenAI',
			'deepseek' => 'DeepSeek',
			'claude'   => 'Claude (Anthropic)',
		);

		$periods = array(
			'day'   => __( 'Day', 'plugindaddy-service' ),
			'week'  => __( 'Week', 'plugindaddy-service' ),
			'month' => __( 'Month', 'plugindaddy-service' ),
			'year'  => __( 'Year', 'plugindaddy-service' ),
		);

		$downloads = get_posts(
			array(
				'post_type'      => 'download',
				'posts_per_page' => 200,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PluginDaddy — Settings', 'plugindaddy-service' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION ); ?>

				<h2><?php esc_html_e( 'Provider Credentials', 'plugindaddy-service' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="openai_api_key">OpenAI API Key</label></th>
						<td><input type="password" class="regular-text" id="openai_api_key" name="<?php echo esc_attr( self::OPTION ); ?>[openai_api_key]" value="<?php echo esc_attr( $get( 'openai_api_key' ) ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="deepseek_api_key">DeepSeek API Key</label></th>
						<td><input type="password" class="regular-text" id="deepseek_api_key" name="<?php echo esc_attr( self::OPTION ); ?>[deepseek_api_key]" value="<?php echo esc_attr( $get( 'deepseek_api_key' ) ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="claude_api_key">Claude API Key</label></th>
						<td><input type="password" class="regular-text" id="claude_api_key" name="<?php echo esc_attr( self::OPTION ); ?>[claude_api_key]" value="<?php echo esc_attr( $get( 'claude_api_key' ) ); ?>" autocomplete="off"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Free Tier', 'plugindaddy-service' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Allowance', 'plugindaddy-service' ); ?></th>
						<td>
							<input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( self::OPTION ); ?>[free_allowance]" value="<?php echo esc_attr( (int) $get( 'free_allowance', 1 ) ); ?>">
							<?php esc_html_e( 'credits per', 'plugindaddy-service' ); ?>
							<select name="<?php echo esc_attr( self::OPTION ); ?>[free_period]">
								<?php foreach ( $periods as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $get( 'free_period', 'month' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Rolling window. Each successful free generation counts toward the limit for this many units from now.', 'plugindaddy-service' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="free_provider"><?php esc_html_e( 'Provider', 'plugindaddy-service' ); ?></label></th>
						<td>
							<select id="free_provider" name="<?php echo esc_attr( self::OPTION ); ?>[free_provider]">
								<?php foreach ( $providers as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $get( 'free_provider' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="free_model"><?php esc_html_e( 'Model', 'plugindaddy-service' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="free_model" name="<?php echo esc_attr( self::OPTION ); ?>[free_model]" value="<?php echo esc_attr( $get( 'free_model' ) ); ?>" placeholder="<?php esc_attr_e( 'provider default', 'plugindaddy-service' ); ?>">
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Paid Tier', 'plugindaddy-service' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="paid_provider"><?php esc_html_e( 'Provider', 'plugindaddy-service' ); ?></label></th>
						<td>
							<select id="paid_provider" name="<?php echo esc_attr( self::OPTION ); ?>[paid_provider]">
								<?php foreach ( $providers as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $get( 'paid_provider' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="paid_model"><?php esc_html_e( 'Model', 'plugindaddy-service' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="paid_model" name="<?php echo esc_attr( self::OPTION ); ?>[paid_model]" value="<?php echo esc_attr( $get( 'paid_model' ) ); ?>" placeholder="<?php esc_attr_e( 'provider default', 'plugindaddy-service' ); ?>">
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'EDD Product', 'plugindaddy-service' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="edd_product_id"><?php esc_html_e( 'Credits Download', 'plugindaddy-service' ); ?></label></th>
						<td>
							<select id="edd_product_id" name="<?php echo esc_attr( self::OPTION ); ?>[edd_product_id]">
								<option value="0"><?php esc_html_e( '— Select a download —', 'plugindaddy-service' ); ?></option>
								<?php foreach ( $downloads as $d ) : ?>
									<option value="<?php echo esc_attr( (int) $d->ID ); ?>" <?php selected( (int) $get( 'edd_product_id', 0 ), (int) $d->ID ); ?>>
										<?php echo esc_html( $d->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select the EDD download that sells credits. Per-price credit amounts are configured on each variable price row in the download editor.', 'plugindaddy-service' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function render_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$table = new Plugins_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PluginDaddy — Plugins Log', 'plugindaddy-service' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::LOG_SLUG ); ?>" />
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}
}
