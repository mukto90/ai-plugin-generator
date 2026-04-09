(function ($) {
	'use strict';

	$(document).ready(function () {
		loadProviders();
		loadSettings();

		// Toggle API key visibility.
		$('#aipg-toggle-key').on('click', function () {
			var $input = $('#aipg-api-key');
			var $icon = $(this).find('.dashicons');

			if ($input.attr('type') === 'password') {
				$input.attr('type', 'text');
				$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
			} else {
				$input.attr('type', 'password');
				$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
			}
		});

		// Save settings.
		$('#aipg-settings-form').on('submit', function (e) {
			e.preventDefault();
			saveSettings();
		});
	});

	function loadProviders() {
		$.ajax({
			url: aipgData.restUrl + 'providers',
			method: 'GET',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			success: function (providers) {
				var $select = $('#aipg-provider');
				$.each(providers, function (slug, name) {
					$select.append(
						'<option value="' + slug + '">' + name + '</option>'
					);
				});
			}
		});
	}

	function loadSettings() {
		$.ajax({
			url: aipgData.restUrl + 'settings',
			method: 'GET',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			success: function (settings) {
				if (settings.provider) {
					$('#aipg-provider').val(settings.provider);
				}
				if (settings.model) {
					$('#aipg-model').val(settings.model);
				}
				if (settings.has_api_key) {
					$('#aipg-api-key').attr('placeholder', settings.api_key_masked);
					$('#aipg-key-status')
						.text('API key is configured')
						.addClass('valid');
				}
			}
		});
	}

	function saveSettings() {
		var data = {
			provider: $('#aipg-provider').val(),
			model: $('#aipg-model').val()
		};

		var apiKey = $('#aipg-api-key').val();
		if (apiKey) {
			data.api_key = apiKey;
		}

		var $btn = $('#aipg-settings-form').find('button[type="submit"]');
		$btn.prop('disabled', true);

		$.ajax({
			url: aipgData.restUrl + 'settings',
			method: 'PUT',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify(data),
			success: function (settings) {
				showNotice(aipgData.i18n.saved, 'success');

				// Update UI.
				if (settings.has_api_key) {
					$('#aipg-api-key').val('').attr('placeholder', settings.api_key_masked);
					$('#aipg-key-status')
						.text('API key is configured')
						.removeClass('invalid')
						.addClass('valid');
				}
			},
			error: function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.message)
					? xhr.responseJSON.message
					: aipgData.i18n.error;
				showNotice(msg, 'error');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	}

	function showNotice(message, type) {
		$('#aipg-notice')
			.removeClass('aipg-notice-hidden success error info')
			.addClass(type)
			.show();
		$('#aipg-notice-text').html(message);
	}

})(jQuery);
