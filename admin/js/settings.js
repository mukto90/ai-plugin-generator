(function ($) {
	'use strict';

	$(document).ready(function () {
		loadSettings();

		$('#aipg-request-key-form').on('submit', function (e) {
			e.preventDefault();
			requestKey();
		});

		$('#aipg-settings-form').on('submit', function (e) {
			e.preventDefault();
			saveSettings();
		});
	});

	function loadSettings() {
		$.ajax({
			url: aipgData.restUrl + 'settings',
			method: 'GET',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			success: function (settings) {
				if (settings.email) {
					$('#aipg-email').val(settings.email);
					$('#aipg-request-email').val(settings.email);
				}
				if (settings.has_api_key) {
					$('#aipg-api-key').attr('placeholder', settings.api_key_masked);
					$('#aipg-key-status').text('API key is configured.').removeClass('invalid').addClass('valid');
				}
			}
		});
	}

	function requestKey() {
		var email = $('#aipg-request-email').val();
		var $btn = $('#aipg-request-key-form').find('button[type="submit"]');
		$btn.prop('disabled', true);

		$.ajax({
			url: aipgData.restUrl + 'settings/request-key',
			method: 'POST',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			contentType: 'application/json',
			data: JSON.stringify({ email: email }),
			success: function () {
				showNotice('Check your inbox — your API key is on its way.', 'success');
				$('#aipg-email').val(email);
				$('#aipg-api-key').trigger('focus');
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	}

	function saveSettings() {
		var data = {
			email: $('#aipg-email').val(),
			api_key: $('#aipg-api-key').val()
		};

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
					$('#aipg-key-status').text('API key is configured.').removeClass('invalid').addClass('valid');
				}
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
				$('#aipg-key-status').text('Verification failed.').removeClass('valid').addClass('invalid');
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
