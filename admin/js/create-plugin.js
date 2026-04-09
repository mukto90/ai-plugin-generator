(function ($) {
	'use strict';

	var parsedFiles = [];
	var pluginData = {};
	var editMode = false;
	var editId = 0;
	var codeEditable = false;
	var slugAvailable = true;
	var slugCheckXhr = null;

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
		},
		{
			name: 'Custom Login Page',
			slug: 'custom-login-page',
			description: 'Customize the WordPress login page appearance.',
			requirements: 'Create a custom login page plugin. Features:\n' +
				'- Settings page with options for: logo image URL, background color, form background color, button color\n' +
				'- Custom CSS that overrides the default WP login styles\n' +
				'- Custom footer text on login page\n' +
				'- Logo links to the site homepage instead of wordpress.org\n' +
				'- Responsive and clean design'
		},
		{
			name: 'Reading Time Estimator',
			slug: 'reading-time-estimator',
			description: 'Show estimated reading time on posts.',
			requirements: 'Create a reading time estimator plugin. Features:\n' +
				'- Calculate estimated reading time based on word count (average 200 words/min)\n' +
				'- Automatically display above or below post content\n' +
				'- Settings: words per minute, position (before/after content), post types, display text format\n' +
				'- Shortcode [reading_time] for manual placement\n' +
				'- Simple and lightweight'
		},
		{
			name: 'Social Share Buttons',
			slug: 'social-share-buttons',
			description: 'Add social sharing buttons to posts and pages.',
			requirements: 'Create a social share buttons plugin. Features:\n' +
				'- Automatically add share buttons after post content\n' +
				'- Support: Facebook, Twitter/X, LinkedIn, WhatsApp, Email\n' +
				'- Settings page to choose which platforms to show\n' +
				'- Settings to choose post types where buttons appear\n' +
				'- Clean, modern button design with SVG icons and hover effects'
		},
		{
			name: 'Back to Top Button',
			slug: 'back-to-top-button',
			description: 'A smooth scroll-to-top button that appears on scroll.',
			requirements: 'Create a back to top button plugin. Features:\n' +
				'- Floating button appears after scrolling down 300px\n' +
				'- Smooth scroll animation to top\n' +
				'- Settings: button color, position (left/right), icon, scroll offset threshold\n' +
				'- Fade in/out animation\n' +
				'- Mobile-friendly sizing'
		},
		{
			name: 'Post Views Counter',
			slug: 'post-views-counter',
			description: 'Track and display how many times each post has been viewed.',
			requirements: 'Create a post views counter plugin. Features:\n' +
				'- Track views for posts and pages using post meta\n' +
				'- Exclude logged-in admins from view count\n' +
				'- Display view count automatically before or after post content\n' +
				'- Shortcode [post_views] to display count anywhere\n' +
				'- Admin column in post list showing view counts\n' +
				'- Dashboard widget showing top 10 most viewed posts'
		},
		{
			name: 'Simple Notice Bar',
			slug: 'simple-notice-bar',
			description: 'Display a customizable announcement bar at the top of your site.',
			requirements: 'Create a notice bar / announcement bar plugin. Features:\n' +
				'- Fixed or sticky bar at the top of the site\n' +
				'- Settings: message text, background color, text color, font size, link URL, link text\n' +
				'- Dismissible with a close button (remembers via cookie)\n' +
				'- Option to show only on homepage or all pages\n' +
				'- Smooth slide-down animation on page load'
		},
		{
			name: 'Duplicate Post',
			slug: 'duplicate-post',
			description: 'One-click duplicate any post or page.',
			requirements: 'Create a duplicate post plugin. Features:\n' +
				'- Add "Duplicate" link to post/page row actions in admin\n' +
				'- Duplicates all content, title (prefixed with "Copy of"), featured image, categories, tags, and custom fields\n' +
				'- New post is created as draft\n' +
				'- Redirects to the edit screen of the new post after duplicating\n' +
				'- Works with all public post types\n' +
				'- Admin bar "Duplicate" link when viewing a single post'
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
				checkSlug(slug);
			});

			$('#aipg-slug').on('change', function () {
				checkSlug($(this).val());
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

	function checkSlug(slug) {
		if (!slug || slug.length < 2) {
			$('#aipg-slug-status').remove();
			slugAvailable = true;
			return;
		}

		// Abort previous request.
		if (slugCheckXhr) {
			slugCheckXhr.abort();
		}

		slugCheckXhr = $.ajax({
			url: aipgData.restUrl + 'check-slug',
			method: 'GET',
			headers: { 'X-WP-Nonce': aipgData.nonce },
			data: { slug: slug },
			success: function (response) {
				$('#aipg-slug-status').remove();

				if (response.available) {
					slugAvailable = true;
					$('#aipg-slug').after(
						'<p id="aipg-slug-status" class="description aipg-slug-ok">' +
						'Slug is available.</p>'
					);
				} else {
					slugAvailable = false;
					var sources = response.conflicts.join(', ');
					$('#aipg-slug').after(
						'<p id="aipg-slug-status" class="description aipg-slug-conflict">' +
						'Slug conflict: already exists in ' + escHtml(sources) +
						'. Please choose a different slug.</p>'
					);
				}
			}
		});
	}

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

		if (!editMode && !slugAvailable) {
			showNotice('Slug has a conflict. Please choose a different slug before generating.', 'error');
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
