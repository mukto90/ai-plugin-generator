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
				<h2><?php esc_html_e( 'PluginDaddy API Key', 'ai-plugin-generator' ); ?></h2>
				<p class="aipg-card-sub">
					<?php esc_html_e( 'This plugin uses the PluginDaddy service to generate code. Request a free API key below — it will be emailed to you.', 'ai-plugin-generator' ); ?>
				</p>
			</div>
			<div class="aipg-card-body">

				<form id="aipg-request-key-form" class="aipg-subform">
					<h3><?php esc_html_e( 'Step 1 — Request a key', 'ai-plugin-generator' ); ?></h3>
					<div class="aipg-field">
						<label for="aipg-request-email"><?php esc_html_e( 'Email address', 'ai-plugin-generator' ); ?></label>
						<input type="email" id="aipg-request-email" name="email" placeholder="you@example.com" required>
						<p class="description"><?php esc_html_e( 'We will send an API key to this address.', 'ai-plugin-generator' ); ?></p>
					</div>
					<div class="aipg-actions">
						<button type="submit" class="button button-primary aipg-btn">
							<span class="dashicons dashicons-email-alt"></span>
							<?php esc_html_e( 'Email me a key', 'ai-plugin-generator' ); ?>
						</button>
					</div>
				</form>

				<hr class="aipg-hr">

				<form id="aipg-settings-form" class="aipg-subform">
					<h3><?php esc_html_e( 'Step 2 — Save your key', 'ai-plugin-generator' ); ?></h3>
					<div class="aipg-field">
						<label for="aipg-email"><?php esc_html_e( 'Email', 'ai-plugin-generator' ); ?></label>
						<input type="email" id="aipg-email" name="email" placeholder="you@example.com" required>
					</div>

					<div class="aipg-field">
						<label for="aipg-api-key"><?php esc_html_e( 'API Key', 'ai-plugin-generator' ); ?></label>
						<input type="password" id="aipg-api-key" name="api_key" placeholder="<?php esc_attr_e( 'Paste the key from your email...', 'ai-plugin-generator' ); ?>">
						<p class="description" id="aipg-key-status"></p>
					</div>

					<div class="aipg-actions">
						<button type="submit" class="button button-primary aipg-btn">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Verify & Save', 'ai-plugin-generator' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
