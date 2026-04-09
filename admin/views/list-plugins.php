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
		<div class="aipg-card-header">
			<h2><?php esc_html_e( 'All Plugins', 'ai-plugin-generator' ); ?></h2>
		</div>
		<div class="aipg-card-body aipg-list-body">
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
