<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap aipg-wrap">
	<h1 class="aipg-page-title">
		<span class="dashicons dashicons-superhero-alt"></span>
		<?php esc_html_e( 'Create New Plugin', 'ai-plugin-generator' ); ?>
	</h1>

	<div class="aipg-notice aipg-notice-hidden" id="aipg-notice">
		<p id="aipg-notice-text"></p>
	</div>

	<div class="aipg-create-layout">
		<!-- Left: Form -->
		<div class="aipg-create-form-panel">
			<div class="aipg-card">
				<div class="aipg-card-header">
					<h2><?php esc_html_e( 'Plugin Details', 'ai-plugin-generator' ); ?></h2>
				</div>
				<div class="aipg-card-body">
					<form id="aipg-create-form">
						<div class="aipg-field">
							<label for="aipg-name"><?php esc_html_e( 'Plugin Name', 'ai-plugin-generator' ); ?> <span class="required">*</span></label>
							<input type="text" id="aipg-name" name="name" required placeholder="<?php esc_attr_e( 'My Awesome Plugin', 'ai-plugin-generator' ); ?>">
						</div>

						<div class="aipg-field">
							<label for="aipg-slug"><?php esc_html_e( 'Slug', 'ai-plugin-generator' ); ?> <span class="required">*</span></label>
							<input type="text" id="aipg-slug" name="slug" required placeholder="<?php esc_attr_e( 'my-awesome-plugin', 'ai-plugin-generator' ); ?>">
							<p class="description"><?php esc_html_e( 'Auto-generated from plugin name. You can edit it.', 'ai-plugin-generator' ); ?></p>
						</div>

						<div class="aipg-field">
							<label for="aipg-requirements"><?php esc_html_e( 'Requirements', 'ai-plugin-generator' ); ?> <span class="required">*</span></label>
							<textarea id="aipg-requirements" name="requirements" rows="6" required placeholder="<?php esc_attr_e( 'Describe what your plugin should do...', 'ai-plugin-generator' ); ?>"></textarea>
						</div>

						<div class="aipg-field-group">
							<div class="aipg-field">
								<label for="aipg-version"><?php esc_html_e( 'Version', 'ai-plugin-generator' ); ?></label>
								<input type="text" id="aipg-version" name="version" value="1.0.0" placeholder="1.0.0">
							</div>

							<div class="aipg-field">
								<label for="aipg-author"><?php esc_html_e( 'Author', 'ai-plugin-generator' ); ?></label>
								<input type="text" id="aipg-author" name="author" placeholder="<?php esc_attr_e( 'Your Name', 'ai-plugin-generator' ); ?>">
							</div>
						</div>

						<div class="aipg-field">
							<label for="aipg-description"><?php esc_html_e( 'Description', 'ai-plugin-generator' ); ?></label>
							<textarea id="aipg-description" name="description" rows="3" placeholder="<?php esc_attr_e( 'A short description of the plugin...', 'ai-plugin-generator' ); ?>"></textarea>
						</div>

						<div class="aipg-actions">
							<button type="submit" class="button button-primary button-hero aipg-generate-btn" id="aipg-generate-btn">
								<span class="dashicons dashicons-admin-generic aipg-spin-icon"></span>
								<?php esc_html_e( 'Generate Plugin', 'ai-plugin-generator' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- Right: Code Preview -->
		<div class="aipg-create-preview-panel">
			<div class="aipg-card">
				<div class="aipg-card-header">
					<h2><?php esc_html_e( 'Code Preview', 'ai-plugin-generator' ); ?></h2>
				</div>
				<div class="aipg-card-body">
					<div id="aipg-preview-placeholder" class="aipg-preview-placeholder">
						<span class="dashicons dashicons-editor-code"></span>
						<p><?php esc_html_e( 'Generated code will appear here...', 'ai-plugin-generator' ); ?></p>
					</div>
					<div id="aipg-preview-code" class="aipg-preview-code" style="display:none;">
						<pre><code id="aipg-code-output"></code></pre>
					</div>
					<div id="aipg-confirm-actions" class="aipg-actions" style="display:none;">
						<button type="button" class="button button-primary button-hero" id="aipg-confirm-btn">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Confirm & Save', 'ai-plugin-generator' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="aipg-regenerate-btn">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Regenerate', 'ai-plugin-generator' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
