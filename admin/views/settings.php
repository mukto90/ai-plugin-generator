<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap aipg-wrap">
	<h1 class="aipg-page-title">
		<span class="dashicons dashicons-superhero-alt"></span>
		<?php esc_html_e( 'AI Plugin Generator Settings', 'ai-plugin-generator' ); ?>
	</h1>

	<div class="aipg-notice aipg-notice-hidden" id="aipg-notice">
		<p id="aipg-notice-text"></p>
	</div>

	<div class="aipg-settings-layout">
		<div class="aipg-card">
			<div class="aipg-card-header">
				<h2><?php esc_html_e( 'AI Provider Configuration', 'ai-plugin-generator' ); ?></h2>
			</div>
			<div class="aipg-card-body">
				<form id="aipg-settings-form">
					<div class="aipg-field">
						<label for="aipg-provider"><?php esc_html_e( 'AI Provider', 'ai-plugin-generator' ); ?></label>
						<select id="aipg-provider" name="provider">
							<option value=""><?php esc_html_e( '— Select Provider —', 'ai-plugin-generator' ); ?></option>
						</select>
					</div>

					<div class="aipg-field">
						<label for="aipg-api-key"><?php esc_html_e( 'API Key', 'ai-plugin-generator' ); ?></label>
						<input type="password" id="aipg-api-key" name="api_key" placeholder="<?php esc_attr_e( 'Enter your API key...', 'ai-plugin-generator' ); ?>">
						<p class="description" id="aipg-key-status"></p>
					</div>

					<div class="aipg-field">
						<label for="aipg-model"><?php esc_html_e( 'Model', 'ai-plugin-generator' ); ?></label>
						<input type="text" id="aipg-model" name="model" placeholder="<?php esc_attr_e( 'e.g., gpt-4o, deepseek-chat, gemini-2.0-flash', 'ai-plugin-generator' ); ?>">
						<p class="description"><?php esc_html_e( 'Leave empty to use the provider default.', 'ai-plugin-generator' ); ?></p>
					</div>

					<div class="aipg-actions">
						<button type="submit" class="button button-primary aipg-btn">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Save Settings', 'ai-plugin-generator' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
