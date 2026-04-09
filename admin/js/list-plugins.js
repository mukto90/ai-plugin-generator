(function ($) {
	'use strict';

	var currentPage = 1;
	var perPage = 20;
	var searchTerm = '';

	$(document).ready(function () {
		loadPlugins();

		// Search.
		$('#aipg-search-btn').on('click', function () {
			searchTerm = $('#aipg-search').val();
			currentPage = 1;
			loadPlugins();
		});

		$('#aipg-search').on('keypress', function (e) {
			if (e.which === 13) {
				e.preventDefault();
				$('#aipg-search-btn').click();
			}
		});

		// Modal close.
		$(document).on('click', '.aipg-modal-close, .aipg-modal-close-btn, .aipg-modal-overlay', function () {
			$('#aipg-edit-modal').hide();
		});

		// Save only.
		$('#aipg-edit-save-btn').on('click', function () {
			savePlugin(false);
		});

		// Save & Regenerate.
		$('#aipg-edit-regenerate-btn').on('click', function () {
			savePlugin(true);
		});
	});

	function loadPlugins() {
		var $tbody = $('#aipg-plugins-list');
		$tbody.html(
			'<tr class="aipg-loading-row"><td colspan="6">' +
			'<span class="spinner is-active"></span> Loading plugins...</td></tr>'
		);

		$.ajax({
			url: aipgData.restUrl + 'plugins',
			method: 'GET',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			data: {
				per_page: perPage,
				offset: (currentPage - 1) * perPage,
				search: searchTerm
			},
			success: function (response) {
				renderPlugins(response.items);
				renderPagination(response.total);
			},
			error: function () {
				$tbody.html(
					'<tr class="aipg-empty-row"><td colspan="6">Failed to load plugins.</td></tr>'
				);
			}
		});
	}

	function renderPlugins(items) {
		var $tbody = $('#aipg-plugins-list');
		$tbody.empty();

		if (!items || items.length === 0) {
			$tbody.html(
				'<tr class="aipg-empty-row"><td colspan="6">' +
				'No plugins found. <a href="' + adminUrl('admin.php?page=aipg-create') + '">Create one!</a>' +
				'</td></tr>'
			);
			return;
		}

		$.each(items, function (i, plugin) {
			var statusClass = plugin.active ? 'active' : (plugin.installed ? 'installed' : plugin.status);
			var statusLabel = plugin.active ? 'Active' : (plugin.installed ? 'Installed' : plugin.status);

			var actions = '<div class="aipg-row-actions">';
			actions += '<button class="button aipg-action-download" data-id="' + plugin.id + '" title="Download">';
			actions += '<span class="dashicons dashicons-download"></span></button>';
			actions += '<button class="button aipg-action-edit" data-id="' + plugin.id + '" title="Edit">';
			actions += '<span class="dashicons dashicons-edit"></span></button>';

			if (!plugin.installed) {
				actions += '<button class="button aipg-action-install" data-id="' + plugin.id + '" title="Install">';
				actions += '<span class="dashicons dashicons-upload"></span></button>';
			} else if (plugin.active) {
				actions += '<button class="button aipg-action-deactivate" data-id="' + plugin.id + '" title="Deactivate">';
				actions += '<span class="dashicons dashicons-no"></span></button>';
			} else {
				actions += '<button class="button aipg-action-activate" data-id="' + plugin.id + '" title="Activate">';
				actions += '<span class="dashicons dashicons-yes"></span></button>';
			}

			actions += '<button class="button button-danger aipg-action-delete" data-id="' + plugin.id + '" title="Delete">';
			actions += '<span class="dashicons dashicons-trash"></span></button>';
			actions += '</div>';

			var date = new Date(plugin.created_at).toLocaleDateString();

			$tbody.append(
				'<tr>' +
				'<td class="column-name"><strong>' + escHtml(plugin.name) + '</strong></td>' +
				'<td class="column-slug"><code>' + escHtml(plugin.slug) + '</code></td>' +
				'<td class="column-version">' + escHtml(plugin.version) + '</td>' +
				'<td class="column-status"><span class="aipg-status-badge ' + statusClass + '">' + statusLabel + '</span></td>' +
				'<td class="column-date">' + date + '</td>' +
				'<td class="column-actions">' + actions + '</td>' +
				'</tr>'
			);
		});

		// Bind action buttons.
		bindActions();
	}

	function bindActions() {
		$('.aipg-action-download').off('click').on('click', function () {
			downloadPlugin($(this).data('id'));
		});

		$('.aipg-action-edit').off('click').on('click', function () {
			editPlugin($(this).data('id'));
		});

		$('.aipg-action-install').off('click').on('click', function () {
			installPlugin($(this).data('id'), $(this));
		});

		$('.aipg-action-activate').off('click').on('click', function () {
			activatePlugin($(this).data('id'), $(this));
		});

		$('.aipg-action-deactivate').off('click').on('click', function () {
			deactivatePlugin($(this).data('id'), $(this));
		});

		$('.aipg-action-delete').off('click').on('click', function () {
			deletePlugin($(this).data('id'));
		});
	}

	function downloadPlugin(id) {
		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id + '/download',
			method: 'GET',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			success: function (response) {
				window.location.href = response.download_url;
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
			}
		});
	}

	function editPlugin(id) {
		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id,
			method: 'GET',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			success: function (plugin) {
				$('#aipg-edit-id').val(plugin.id);
				$('#aipg-edit-name').val(plugin.name);
				$('#aipg-edit-requirements').val(plugin.requirements);
				$('#aipg-edit-version').val(plugin.version);
				$('#aipg-edit-author').val(plugin.author);
				$('#aipg-edit-description').val(plugin.description);
				$('#aipg-edit-modal').show();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
			}
		});
	}

	function savePlugin(regenerate) {
		var id = $('#aipg-edit-id').val();
		var data = {
			name: $('#aipg-edit-name').val(),
			requirements: $('#aipg-edit-requirements').val(),
			version: $('#aipg-edit-version').val(),
			author: $('#aipg-edit-author').val(),
			description: $('#aipg-edit-description').val(),
			regenerate: regenerate
		};

		var $btn = regenerate ? $('#aipg-edit-regenerate-btn') : $('#aipg-edit-save-btn');
		$btn.prop('disabled', true).text(regenerate ? 'Regenerating...' : 'Saving...');

		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id,
			method: 'PUT',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			contentType: 'application/json',
			data: JSON.stringify(data),
			success: function () {
				$('#aipg-edit-modal').hide();
				showNotice(regenerate ? 'Plugin regenerated successfully!' : 'Plugin updated.', 'success');
				loadPlugins();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
			},
			complete: function () {
				$btn.prop('disabled', false).text(regenerate ? 'Save & Regenerate' : 'Save Only');
			}
		});
	}

	function installPlugin(id, $btn) {
		$btn.prop('disabled', true);
		showNotice(aipgData.i18n.installing, 'info');

		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id + '/install',
			method: 'POST',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			success: function () {
				showNotice('Plugin installed successfully!', 'success');
				loadPlugins();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
				$btn.prop('disabled', false);
			}
		});
	}

	function activatePlugin(id, $btn) {
		$btn.prop('disabled', true);

		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id + '/activate',
			method: 'POST',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			success: function () {
				showNotice('Plugin activated!', 'success');
				loadPlugins();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
				$btn.prop('disabled', false);
			}
		});
	}

	function deactivatePlugin(id, $btn) {
		$btn.prop('disabled', true);

		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id + '/deactivate',
			method: 'POST',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			success: function () {
				showNotice('Plugin deactivated.', 'success');
				loadPlugins();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
				$btn.prop('disabled', false);
			}
		});
	}

	function deletePlugin(id) {
		if (!confirm(aipgData.i18n.confirm_delete)) {
			return;
		}

		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id,
			method: 'DELETE',
			headers: {
				'X-WP-Nonce': aipgData.nonce
			},
			success: function () {
				showNotice('Plugin deleted.', 'success');
				loadPlugins();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
			}
		});
	}

	function renderPagination(total) {
		var $pagination = $('#aipg-pagination');
		$pagination.empty();

		var totalPages = Math.ceil(total / perPage);
		if (totalPages <= 1) {
			return;
		}

		for (var i = 1; i <= totalPages; i++) {
			var cls = i === currentPage ? 'button current' : 'button';
			$pagination.append(
				'<button class="' + cls + '" data-page="' + i + '">' + i + '</button>'
			);
		}

		$pagination.find('.button').on('click', function () {
			currentPage = $(this).data('page');
			loadPlugins();
		});
	}

	function adminUrl(path) {
		var base = aipgData.restUrl.split('/wp-json/')[0];
		return base + '/wp-admin/' + path;
	}

	function showNotice(message, type) {
		$('#aipg-notice')
			.removeClass('aipg-notice-hidden success error info')
			.addClass(type)
			.show();
		$('#aipg-notice-text').html(message);
	}

	function getErrorMessage(xhr) {
		return (xhr.responseJSON && xhr.responseJSON.message)
			? xhr.responseJSON.message
			: aipgData.i18n.error;
	}

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

})(jQuery);
