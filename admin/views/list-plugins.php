<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap aipg-wrap">
	<h1 class="aipg-page-title">
		<span class="dashicons dashicons-superhero-alt"></span>
		<?php esc_html_e( 'AI Generated Plugins', 'ai-plugin-generator' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aipg-create' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Create New', 'ai-plugin-generator' ); ?>
		</a>
	</h1>

	<div class="aipg-notice aipg-notice-hidden" id="aipg-notice">
		<p id="aipg-notice-text"></p>
	</div>

	<div class="aipg-card">
		<div class="aipg-card-body">
			<div class="aipg-list-toolbar">
				<div class="aipg-search-box">
					<input type="search" id="aipg-search" placeholder="<?php esc_attr_e( 'Search plugins...', 'ai-plugin-generator' ); ?>">
					<button type="button" class="button" id="aipg-search-btn"><?php esc_html_e( 'Search', 'ai-plugin-generator' ); ?></button>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped" id="aipg-plugins-table">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e( 'Plugin Name', 'ai-plugin-generator' ); ?></th>
						<th class="column-slug"><?php esc_html_e( 'Slug', 'ai-plugin-generator' ); ?></th>
						<th class="column-version"><?php esc_html_e( 'Version', 'ai-plugin-generator' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'ai-plugin-generator' ); ?></th>
						<th class="column-date"><?php esc_html_e( 'Created', 'ai-plugin-generator' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'ai-plugin-generator' ); ?></th>
					</tr>
				</thead>
				<tbody id="aipg-plugins-list">
					<tr class="aipg-loading-row">
						<td colspan="6">
							<span class="spinner is-active"></span>
							<?php esc_html_e( 'Loading plugins...', 'ai-plugin-generator' ); ?>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="aipg-pagination" id="aipg-pagination"></div>
		</div>
	</div>
</div>

<!-- Edit Modal -->
<div id="aipg-edit-modal" class="aipg-modal" style="display:none;">
	<div class="aipg-modal-overlay"></div>
	<div class="aipg-modal-content">
		<div class="aipg-modal-header">
			<h2><?php esc_html_e( 'Edit Plugin', 'ai-plugin-generator' ); ?></h2>
			<button type="button" class="aipg-modal-close">&times;</button>
		</div>
		<div class="aipg-modal-body">
			<form id="aipg-edit-form">
				<input type="hidden" id="aipg-edit-id" name="id">
				<div class="aipg-field">
					<label for="aipg-edit-name"><?php esc_html_e( 'Plugin Name', 'ai-plugin-generator' ); ?></label>
					<input type="text" id="aipg-edit-name" name="name" required>
				</div>
				<div class="aipg-field">
					<label for="aipg-edit-requirements"><?php esc_html_e( 'Requirements', 'ai-plugin-generator' ); ?></label>
					<textarea id="aipg-edit-requirements" name="requirements" rows="6" required></textarea>
				</div>
				<div class="aipg-field-group">
					<div class="aipg-field">
						<label for="aipg-edit-version"><?php esc_html_e( 'Version', 'ai-plugin-generator' ); ?></label>
						<input type="text" id="aipg-edit-version" name="version">
					</div>
					<div class="aipg-field">
						<label for="aipg-edit-author"><?php esc_html_e( 'Author', 'ai-plugin-generator' ); ?></label>
						<input type="text" id="aipg-edit-author" name="author">
					</div>
				</div>
				<div class="aipg-field">
					<label for="aipg-edit-description"><?php esc_html_e( 'Description', 'ai-plugin-generator' ); ?></label>
					<textarea id="aipg-edit-description" name="description" rows="3"></textarea>
				</div>
			</form>
		</div>
		<div class="aipg-modal-footer">
			<button type="button" class="button button-secondary aipg-modal-close-btn"><?php esc_html_e( 'Cancel', 'ai-plugin-generator' ); ?></button>
			<button type="button" class="button button-secondary" id="aipg-edit-save-btn"><?php esc_html_e( 'Save Only', 'ai-plugin-generator' ); ?></button>
			<button type="button" class="button button-primary" id="aipg-edit-regenerate-btn">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Save & Regenerate', 'ai-plugin-generator' ); ?>
			</button>
		</div>
	</div>
</div>
