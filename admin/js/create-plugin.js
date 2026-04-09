(function ($) {
	'use strict';

	var parsedFiles = [];
	var pluginData = {};
	var editMode = false;
	var editId = 0;
	var codeEditable = false;

	var samples = [
		{
			name: 'Coming Soon Page',
			slug: 'coming-soon-page',
			description: 'A simple coming soon / maintenance mode page.',
			requirements: 'Create a coming soon / maintenance mode plugin. Features:\n' +
				'- Admin toggle to enable/disable maintenance mode\n' +
				'- A beautiful full-screen coming soon page with a countdown timer\n' +
				'- Admin users can still access the site normally\n' +
				'- Settings page to customize the launch date, headline, and message\n' +
				'- Responsive design'
		},
		{
			name: 'Simple Testimonials',
			slug: 'simple-testimonials',
			description: 'Display customer testimonials with a shortcode.',
			requirements: 'Create a testimonials plugin. Features:\n' +
				'- Custom post type "Testimonial" with fields: author name, role/company, quote, rating (1-5 stars), photo\n' +
				'- Shortcode [testimonials] to display testimonials in a responsive grid\n' +
				'- Shortcode attributes: count, columns, orderby\n' +
				'- Star rating display\n' +
				'- Clean, modern card-based design'
		},
		{
			name: 'FAQ Accordion',
			slug: 'faq-accordion',
			description: 'Create and display FAQs in an accordion layout.',
			requirements: 'Create an FAQ accordion plugin. Features:\n' +
				'- Custom post type "FAQ" with question (title) and answer (content)\n' +
				'- FAQ Groups/Categories taxonomy for organizing\n' +
				'- Shortcode [faq] with optional group attribute\n' +
				'- Smooth accordion animation (expand/collapse)\n' +
				'- Schema.org FAQPage structured data output\n' +
				'- Clean, accessible design with keyboard navigation'
		}
	];

	$(document).ready(function () {
		editId = parseInt($('#aipg-edit-id').val(), 10) || 0;
		editMode = editId > 0;

		if (editMode) {
			loadPluginForEdit(editId);
		}

		// Populate samples dropdown.
		if (!editMode) {
			initSamples();

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
		}

		// Generate / Regenerate.
		$('#aipg-create-form').on('submit', function (e) {
			e.preventDefault();
			generatePlugin();
		});

		$('#aipg-regenerate-btn').on('click', function () {
			generatePlugin();
		});

		// Confirm & Save.
		$('#aipg-confirm-btn').on('click', function () {
			confirmPlugin();
		});

		// Toggle code editing.
		$('#aipg-edit-code-btn').on('click', function () {
			toggleCodeEditing();
		});

		// Tab switching.
		$(document).on('click', '.aipg-file-tab', function () {
			var index = $(this).data('index');
			switchTab(index);
		});
	});

	function initSamples() {
		var $select = $('#aipg-samples');
		if (!$select.length) {
			return;
		}

		$.each(samples, function (i, sample) {
			$select.append('<option value="' + i + '">' + escHtml(sample.name) + '</option>');
		});

		$select.on('change', function () {
			var index = $(this).val();
			if (index === '') {
				return;
			}

			var sample = samples[index];
			$('#aipg-name').val(sample.name).trigger('input');
			$('#aipg-slug').val(sample.slug);
			$('#aipg-requirements').val(sample.requirements);
			$('#aipg-description').val(sample.description);
			$('#aipg-version').val('1.0.0');

			// Reset dropdown.
			$(this).val('');
		});
	}

	function loadPluginForEdit(id) {
		$.ajax({
			url: aipgData.restUrl + 'plugins/' + id,
			method: 'GET',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			success: function (plugin) {
				$('#aipg-name').val(plugin.name);
				$('#aipg-slug').val(plugin.slug);
				$('#aipg-version').val(plugin.version);
				$('#aipg-author').val(plugin.author);
				$('#aipg-description').val(plugin.description);
				$('#aipg-requirements').val(plugin.requirements);
			},
			error: function (xhr) {
				showNotice(getErrorMessage(xhr), 'error');
			}
		});
	}

	function generatePlugin() {
		var $btn = $('#aipg-generate-btn');
		var $icon = $btn.find('.aipg-btn-icon');

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

		// Show loading spinner in preview.
		$('#aipg-preview-placeholder').hide();
		$('#aipg-preview-content').hide();
		$('#aipg-confirm-actions').hide();
		$('#aipg-edit-code-btn').hide();
		$('#aipg-preview-loading').show();

		$.ajax({
			url: aipgData.restUrl + 'plugins',
			method: 'POST',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			contentType: 'application/json',
			data: JSON.stringify(data),
			success: function (response) {
				parsedFiles = parseMultiFileResponse(response.code, data.slug);
				pluginData = response.plugin_data;
				codeEditable = false;

				renderFileTabs(parsedFiles);

				$('#aipg-preview-loading').hide();
				$('#aipg-preview-content').show();
				$('#aipg-confirm-actions').show();
				$('#aipg-edit-code-btn').show().find('span:last').text('Edit');

				showNotice('Code generated successfully! Review and confirm.', 'success');
			},
			error: function (xhr) {
				$('#aipg-preview-loading').hide();
				$('#aipg-preview-placeholder').show();
				showNotice(getErrorMessage(xhr), 'error');
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

		// Collect current code from textareas if editable, or from parsedFiles.
		var files = collectFiles();

		if (editMode) {
			// Update existing plugin with regenerated zip.
			$.ajax({
				url: aipgData.restUrl + 'plugins/' + editId + '/confirm',
				method: 'POST',
				headers: { 'X-WP-Nonce': aipgData.nonce },
				contentType: 'application/json',
				data: JSON.stringify({
					files: files,
					plugin_data: pluginData
				}),
				success: function () {
					showNotice('Plugin updated successfully! <a href="' + adminUrl('admin.php?page=aipg-plugins') + '">View all plugins</a>', 'success');
				},
				error: function (xhr) {
					showNotice(getErrorMessage(xhr), 'error');
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		} else {
			$.ajax({
				url: aipgData.restUrl + 'plugins/0/confirm',
				method: 'POST',
				headers: { 'X-WP-Nonce': aipgData.nonce },
				contentType: 'application/json',
				data: JSON.stringify({
					files: files,
					plugin_data: pluginData
				}),
				success: function () {
					showNotice('Plugin saved successfully! <a href="' + adminUrl('admin.php?page=aipg-plugins') + '">View all plugins</a>', 'success');
					$('#aipg-confirm-actions').hide();
					$('#aipg-edit-code-btn').hide();
					$('#aipg-create-form')[0].reset();
					$('#aipg-preview-content').hide();
					$('#aipg-preview-placeholder').show();
					parsedFiles = [];
					pluginData = {};
				},
				error: function (xhr) {
					showNotice(getErrorMessage(xhr), 'error');
				},
				complete: function () {
					$btn.prop('disabled', false);
				}
			});
		}
	}

	function collectFiles() {
		var files = [];
		if (codeEditable) {
			// Read from textareas.
			$('.aipg-code-textarea').each(function () {
				files.push({
					filename: $(this).data('filename'),
					code: $(this).val()
				});
			});
		} else {
			files = parsedFiles;
		}
		return files;
	}

	/**
	 * Parse AI response into separate files.
	 * Looks for === filename.ext === patterns followed by ```lang code blocks.
	 * Falls back to a single file if no pattern found.
	 */
	function parseMultiFileResponse(raw, slug) {
		var files = [];
		// Pattern: === filename === followed by ```code```
		var regex = /===\s*(.+?)\s*===\s*```[\w]*\n([\s\S]*?)```/g;
		var match;

		while ((match = regex.exec(raw)) !== null) {
			files.push({
				filename: match[1].trim(),
				code: match[2].trim()
			});
		}

		if (files.length === 0) {
			// Try just extracting code blocks.
			var codeRegex = /```[\w]*\n([\s\S]*?)```/g;
			var codeMatch;
			var index = 0;

			while ((codeMatch = codeRegex.exec(raw)) !== null) {
				var filename = index === 0 ? slug + '.php' : 'file-' + (index + 1) + '.php';
				files.push({
					filename: filename,
					code: codeMatch[1].trim()
				});
				index++;
			}
		}

		if (files.length === 0) {
			// Raw code, no markdown blocks.
			files.push({
				filename: slug + '.php',
				code: raw.trim()
			});
		}

		return files;
	}

	function renderFileTabs(files) {
		var $tabs = $('#aipg-file-tabs');
		var $panels = $('#aipg-code-panels');
		$tabs.empty();
		$panels.empty();

		$.each(files, function (i, file) {
			// Tab.
			var activeClass = i === 0 ? ' active' : '';
			$tabs.append(
				'<button type="button" class="aipg-file-tab' + activeClass + '" data-index="' + i + '">' +
				'<span class="dashicons dashicons-media-code"></span> ' +
				escHtml(file.filename) +
				'</button>'
			);

			// Panel — code display (pre/code) and hidden textarea for editing.
			var displayStyle = i === 0 ? '' : ' style="display:none;"';
			$panels.append(
				'<div class="aipg-code-panel" data-index="' + i + '"' + displayStyle + '>' +
				'<div class="aipg-code-display"><pre><code>' + escHtml(file.code) + '</code></pre></div>' +
				'<textarea class="aipg-code-textarea" data-filename="' + escHtml(file.filename) + '" style="display:none;">' + escHtml(file.code) + '</textarea>' +
				'</div>'
			);
		});
	}

	function switchTab(index) {
		$('.aipg-file-tab').removeClass('active');
		$('.aipg-file-tab[data-index="' + index + '"]').addClass('active');
		$('.aipg-code-panel').hide();
		$('.aipg-code-panel[data-index="' + index + '"]').show();
	}

	function toggleCodeEditing() {
		codeEditable = !codeEditable;

		if (codeEditable) {
			$('.aipg-code-display').hide();
			$('.aipg-code-textarea').show();
			$('#aipg-edit-code-btn').addClass('active').find('span:last').text('Preview');
		} else {
			// Sync textarea content back to code display.
			$('.aipg-code-textarea').each(function () {
				var $panel = $(this).closest('.aipg-code-panel');
				$panel.find('.aipg-code-display pre code').text($(this).val());
			});
			$('.aipg-code-textarea').hide();
			$('.aipg-code-display').show();
			$('#aipg-edit-code-btn').removeClass('active').find('span:last').text('Edit');
		}
	}

	function adminUrl(path) {
		var base = aipgData.restUrl.split('/wp-json/')[0];
		return base + '/wp-admin/' + path;
	}

	function showNotice(message, type) {
		$('#aipg-notice').removeClass('aipg-notice-hidden success error info').addClass(type).show();
		$('#aipg-notice-text').html(message);
	}

	function hideNotice() {
		$('#aipg-notice').addClass('aipg-notice-hidden').hide();
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
