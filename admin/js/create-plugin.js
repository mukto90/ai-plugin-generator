(function ($) {
	'use strict';

	var generatedCode = '';
	var pluginData = {};

	$(document).ready(function () {
		// Auto-generate slug from name.
		$('#aipg-name').on('input', function () {
			var name = $(this).val();
			var slug = name
				.toLowerCase()
				.replace(/[^a-z0-9\s-]/g, '')
				.replace(/\s+/g, '-')
				.replace(/-+/g, '-')
				.replace(/^-|-$/g, '');
			$('#aipg-slug').val(slug);
		});

		// Generate plugin.
		$('#aipg-create-form').on('submit', function (e) {
			e.preventDefault();
			generatePlugin();
		});

		// Regenerate.
		$('#aipg-regenerate-btn').on('click', function () {
			generatePlugin();
		});

		// Confirm & Save.
		$('#aipg-confirm-btn').on('click', function () {
			confirmPlugin();
		});
	});

	function generatePlugin() {
		var $btn = $('#aipg-generate-btn');
		var $icon = $btn.find('.aipg-spin-icon');

		var data = {
			name: $('#aipg-name').val(),
			slug: $('#aipg-slug').val(),
			requirements: $('#aipg-requirements').val(),
			version: $('#aipg-version').val() || '1.0.0',
			author: $('#aipg-author').val(),
			description: $('#aipg-description').val()
		};

		if (!data.name || !data.slug || !data.requirements) {
			showNotice('Please fill in all required fields.', 'error');
			return;
		}

		$btn.prop('disabled', true);
		$icon.addClass('spinning');
		hideNotice();
		showNotice(aipgData.i18n.generating, 'info');

		$.ajax({
			url: aipgData.restUrl + 'plugins',
			method: 'POST',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify(data),
			success: function (response) {
				generatedCode = response.code;
				pluginData = response.plugin_data;

				$('#aipg-code-output').text(response.code);
				$('#aipg-preview-placeholder').hide();
				$('#aipg-preview-code').show();
				$('#aipg-confirm-actions').show();

				showNotice('Code generated successfully! Review and confirm.', 'success');
			},
			error: function (xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.message
					? xhr.responseJSON.message
					: aipgData.i18n.error;
				showNotice(msg, 'error');
			},
			complete: function () {
				$btn.prop('disabled', false);
				$icon.removeClass('spinning');
			}
		});
	}

	function confirmPlugin() {
		var $btn = $('#aipg-confirm-btn');

		$btn.prop('disabled', true);
		showNotice(aipgData.i18n.saving, 'info');

		$.ajax({
			url: aipgData.restUrl + 'plugins/0/confirm',
			method: 'POST',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify({
				code: generatedCode,
				plugin_data: pluginData
			}),
			success: function () {
				showNotice('Plugin saved successfully! <a href="' + adminUrl('admin.php?page=aipg-plugins') + '">View all plugins</a>', 'success');
				$('#aipg-confirm-actions').hide();

				// Reset form.
				$('#aipg-create-form')[0].reset();
				$('#aipg-preview-code').hide();
				$('#aipg-preview-placeholder').show();
				generatedCode = '';
				pluginData = {};
			},
			error: function (xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.message
					? xhr.responseJSON.message
					: aipgData.i18n.error;
				showNotice(msg, 'error');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	}

	function adminUrl(path) {
		// Build admin URL from REST URL.
		var base = aipgData.restUrl.split('/wp-json/')[0];
		return base + '/wp-admin/' + path;
	}

	function showNotice(message, type) {
		var $notice = $('#aipg-notice');
		$notice
			.removeClass('aipg-notice-hidden success error info')
			.addClass(type)
			.show();
		$('#aipg-notice-text').html(message);
	}

	function hideNotice() {
		$('#aipg-notice').addClass('aipg-notice-hidden').hide();
	}

})(jQuery);
