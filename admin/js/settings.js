(function ($) {
	'use strict';

	$(document).ready(function () {
		loadProviders();
		loadSettings();

		$('#aipg-settings-form').on('submit', function (e) {
			e.preventDefault();
			saveSettings();
		});
	});

	function loadProviders() {
		$.ajax({
			url: aipgData.restUrl + 'providers',
			method: 'GET',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			success: function (providers) {
				var $select = $('#aipg-provider');
				$.each(providers, function (slug, name) {
					$select.append('<option value="' + slug + '">' + name + '</option>');
				});
			}
		});
	}

	function loadSettings() {
		$.ajax({
			url: aipgData.restUrl + 'settings',
			method: 'GET',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			success: function (settings) {
				if (settings.provider) {
					$('#aipg-provider').val(settings.provider);
				}
				if (settings.model) {
					$('#aipg-model').val(settings.model);
				}
				if (settings.has_api_key) {
					$('#aipg-api-key').attr('placeholder', settings.api_key_masked);
					$('#aipg-key-status').text('API key is configured').addClass('valid');
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
			headers: { 'X-WP-Nonce': aipgData.nonce },
			contentType: 'application/json',
			data: JSON.stringify(data),
			success: function (settings) {
				showNotice(aipgData.i18n.saved, 'success');
				if (settings.has_api_key) {
					$('#aipg-api-key').val('').attr('placeholder', settings.api_key_masked);
					$('#aipg-key-status').text('API key is configured').removeClass('invalid').addClass('valid');
				}
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	}

	function showNotice(message, type) {
		$('#aipg-notice').removeClass('aipg-notice-hidden success error info').addClass(type).show();
		$('#aipg-notice-text').html(message);
	}

	function getErrorMessage(xhr) {
		return (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : aipgData.i18n.error;
	}

})(jQuery);
