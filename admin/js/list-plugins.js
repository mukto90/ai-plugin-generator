(function ($) {
	'use strict';

	var currentPage = 1;
	var perPage = 20;
	var searchTerm = '';

	$(document).ready(function () {
		loadPlugins();

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
			headers: { 'X-WP-Nonce': aipgData.nonce },
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

			actions += '<a class="button aipg-btn aipg-btn-sm aipg-action-download" data-id="' + plugin.id + '" title="Download">';
			actions += '<span class="dashicons dashicons-download"></span></a>';

			actions += '<a class="button aipg-btn aipg-btn-sm" href="' + adminUrl('admin.php?page=aipg-create&edit_id=' + plugin.id) + '" title="Edit">';
			actions += '<span class="dashicons dashicons-edit"></span></a>';

			if (plugin.needs_replace) {
				actions += '<a class="button aipg-btn aipg-btn-sm aipg-btn-replace aipg-action-replace" data-id="' + plugin.id + '" title="Replace with updated version">';
				actions += '<span class="dashicons dashicons-update"></span> Replace</a>';
			}

			if (!plugin.installed) {
				actions += '<a class="button aipg-btn aipg-btn-sm aipg-action-install" data-id="' + plugin.id + '" title="Install">';
				actions += '<span class="dashicons dashicons-upload"></span></a>';
			} else if (plugin.active) {
				actions += '<a class="button aipg-btn aipg-btn-sm aipg-action-deactivate" data-id="' + plugin.id + '" title="Deactivate">';
				actions += '<span class="dashicons dashicons-no"></span></a>';
			} else {
				actions += '<a class="button aipg-btn aipg-btn-sm aipg-action-activate" data-id="' + plugin.id + '" title="Activate">';
				actions += '<span class="dashicons dashicons-yes"></span></a>';
			}

			actions += '<a class="button aipg-btn aipg-btn-sm aipg-btn-danger aipg-action-delete" data-id="' + plugin.id + '" title="Delete">';
			actions += '<span class="dashicons dashicons-trash"></span></a>';
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

		bindActions();
	}

	function bindActions() {
		$('.aipg-action-download').off('click').on('click', function (e) {
			e.preventDefault();
			downloadPlugin($(this).data('id'));
		});

		$('.aipg-action-install').off('click').on('click', function (e) {
			e.preventDefault();
			installPlugin($(this).data('id'), $(this));
		});

		$('.aipg-action-replace').off('click').on('click', function (e) {
			e.preventDefault();
			replacePlugin($(this).data('id'), $(this));
		});

		$('.aipg-action-activate').off('click').on('click', function (e) {
			e.preventDefault();
			activatePlugin($(this).data('id'), $(this));
		});

		$('.aipg-action-deactivate').off('click').on('click', function (e) {
			e.preventDefault();
			deactivatePlugin($(this).data('id'), $(this));
		});

		$('.aipg-action-delete').off('click').on('click', function (e) {
			e.preventDefault();
			deletePlugin($(this).data('id'));
		});
	}

	function downloadPlugin(id) {
		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id + '/download',
			method: 'GET',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			success: function (response) {
				window.location.href = response.download_url;
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
			}
		});
	}

	function replacePlugin(id, $btn) {
		if (!confirm('Replace the installed plugin with the updated version? The plugin will be deactivated, replaced, and reactivated.')) {
			return;
		}

		$btn.addClass('disabled');
		showNotice('Replacing plugin...', 'info');

		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id + '/replace',
			method: 'POST',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			success: function (response) {
				var msg = 'Plugin replaced successfully!';
				if (response.reactivated) {
					msg += ' Plugin was reactivated.';
				}
				showNotice(msg, 'success');
				loadPlugins();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
				$btn.removeClass('disabled');
			}
		});
	}

	function installPlugin(id, $btn) {
		$btn.addClass('disabled');
		showNotice(aipgData.i18n.installing, 'info');

		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id + '/install',
			method: 'POST',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			success: function () {
				showNotice('Plugin installed successfully!', 'success');
				loadPlugins();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
				$btn.removeClass('disabled');
			}
		});
	}

	function activatePlugin(id, $btn) {
		$btn.addClass('disabled');

		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id + '/activate',
			method: 'POST',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			success: function () {
				showNotice('Plugin activated!', 'success');
				loadPlugins();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
				$btn.removeClass('disabled');
			}
		});
	}

	function deactivatePlugin(id, $btn) {
		$btn.addClass('disabled');

		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id + '/deactivate',
			method: 'POST',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			success: function () {
				showNotice('Plugin deactivated.', 'success');
				loadPlugins();
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
				$btn.removeClass('disabled');
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
			headers: { 'X-WP-Nonce': aipgData.nonce },
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
			var cls = i === currentPage ? 'button aipg-btn current' : 'button aipg-btn';
			$pagination.append('<button class="' + cls + '" data-page="' + i + '">' + i + '</button>');
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
		$('#aipg-notice').removeClass('aipg-notice-hidden success error info').addClass(type).show();
		$('#aipg-notice-text').html(message);
	}

	function getErrorMessage(xhr) {
		return (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : aipgData.i18n.error;
	}

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

})(jQuery);
