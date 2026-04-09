# AI Plugin Generator — WordPress Plugin

## Overview

A WordPress plugin that lets admins generate other WordPress plugins using AI. Users describe what they want, an AI provider generates the PHP code, and the plugin packages it as a downloadable/installable zip.

## Requirements Summary

### Admin UI — Create/Edit Plugin
- Form with: plugin name, slug (auto-generated from name, editable), requirements/description of desired functionality
- Optional fields: author name, version, dependencies, description, and other plugin headers
- "Samples" dropdown at top-right of form — selecting one fills the form with sample plugin data (3 samples: Coming Soon Page, Simple Testimonials, FAQ Accordion)
- "Generate Plugin" button sends requirements to an AI provider and displays generated code
- Split layout: form on the left, code preview on the right
- Code preview shows a loading spinner animation while AI generates
- Multi-file support: AI can return multiple files, displayed as tabs in the preview
- Edit button in preview header toggles code editing (textarea) before saving
- Confirm button finalizes generation — creates/updates a zip on filesystem with metadata in DB
- Same page used for editing existing plugins (via `?edit_id=X` param) — no modal popup

### Admin UI — List Plugins
- Table display of all generated plugins with columns: name, slug, version, status, created date, actions
- Actions per plugin: download zip, edit (links to create page with edit_id), install, activate/deactivate, delete
- **Replace** button (yellow) appears when an installed plugin has been regenerated — handles deactivate → uninstall → reinstall → reactivate cycle
- Search box for filtering plugins

### Settings
- AI provider selection (OpenAI, DeepSeek, Gemini)
- API key field (password input, no toggle icon)
- Model override field
- Settings saved via REST API

### UI Style
- Clean, modern admin UI with AI-flavored aesthetic
- Unified `.aipg-btn` button system across all pages
- CSS custom properties for consistent theming

## Technical Decisions

### PHP
- **Minimum PHP**: 7.4
- **Minimum WordPress**: 5.8
- **Coding Standards**: WordPress Coding Standards (WPCS) for PHP, CSS, and JS
- **Prefix**: `aipg_` for functions/hooks, `AIPG_` for constants
- **Namespace**: `A_Plugin_Generator\`
- **Autoloading**: Composer PSR-4 (`A_Plugin_Generator\` → `includes/`)
- **File naming**: PSR-4 convention — filenames match class names (e.g., `Plugin_Manager.php`)

### Architecture
- **API-first**: All backend operations exposed via WP REST API (`/wp-json/aipg/v1/`)
- **No admin-ajax**: Do NOT use `admin-ajax.php` — all async communication goes through REST endpoints
- **jQuery**: Use jQuery (bundled with WP) for all frontend JS — no vanilla JS, no frontend build tools
- **No build step**: Plain CSS and jQuery, enqueued via `wp_enqueue_*`

### REST API Endpoints
- `POST /plugins` — generate a new plugin (sends requirements to AI, returns raw AI response)
- `POST /plugins/{id}/confirm` — confirm and package plugin as zip (id=0 creates new, id>0 updates existing)
- `GET /plugins` — list all generated plugins (includes `installed`, `active`, `needs_replace` flags)
- `GET /plugins/{id}` — get single plugin details
- `PUT /plugins/{id}` — update plugin metadata, optionally regenerate with `regenerate=true`
- `DELETE /plugins/{id}` — delete a generated plugin (removes DB record + zip file)
- `POST /plugins/{id}/install` — install plugin on current site
- `POST /plugins/{id}/activate` — activate installed plugin
- `POST /plugins/{id}/deactivate` — deactivate installed plugin
- `POST /plugins/{id}/replace` — replace installed plugin with updated zip (deactivate → uninstall → reinstall → reactivate if was active)
- `GET /plugins/{id}/download` — get download URL for zip file
- `GET /settings` — get current settings (API key masked)
- `PUT /settings` — update settings (provider, API key, model)
- `GET /providers` — list available AI providers

### Storage
- **Database**: Custom table (`{prefix}aipg_plugins`) for metadata — name, slug, version, author, description, requirements, file path, status, timestamps (no generated code — zip on filesystem is the source of truth)
- **Filesystem**: Zips stored in `wp-content/uploads/ai-plugin-generator/`

### AI Integration
- Single-shot generation: AI receives the requirements and generates the complete plugin code
- Support multiple providers via a common interface/adapter pattern
- Providers: OpenAI (default model: gpt-4o), DeepSeek (default: deepseek-chat, max_tokens: 8192), Gemini (default: gemini-2.0-flash)
- Multi-file output format: `=== filename.php ===` headings followed by ``` code blocks
- Raw AI response returned to frontend — JS parses multi-file format
- System prompt instructs AI to:
  - Follow WPCS strictly for PHP, CSS, and JS
  - NOT use Composer or external dependencies
  - Use clean, modern UI/UX for frontend output
  - Use `=== filename ===` headings for multi-file responses

### Zip Building
- `Zip_Builder` accepts an array of `{filename, code}` objects (multi-file) or a raw string (single file fallback)
- Files placed inside a directory matching the plugin slug: `slug/filename.php`

### Plugin Install/Replace Flow
- `Plugin_Installer::install()` — uses `Plugin_Upgrader` to install from zip
- `Plugin_Installer::needs_replace()` — compares zip mtime vs installed plugin file mtime
- `Plugin_Installer::uninstall_plugin()` — removes plugin directory from `wp-content/plugins/`
- Replace flow: deactivate (if active) → remove old files → install from updated zip → reactivate (if was active)

## Project Structure

```
ai-plugin-generator/
  ai-plugin-generator.php          # Main plugin file, bootstrap
  composer.json                    # Composer PSR-4 autoload config
  .gitignore                       # Excludes vendor/
  uninstall.php                    # Cleanup on uninstall
  vendor/                          # Composer autoloader (generated)
  includes/                        # PSR-4 root: A_Plugin_Generator\
    Plugin.php                     # Core plugin class (singleton, hooks, init)
    Activator.php                  # Activation logic (DB tables, upload dir)
    Deactivator.php                # Deactivation placeholder
    Rest_Controller.php            # REST API registration and all endpoints
    Plugin_Manager.php             # CRUD for generated plugins (DB + filesystem)
    Plugin_Installer.php           # Install/activate/deactivate/replace generated plugins
    Code_Generator.php             # Orchestrates AI code generation, provider factory
    Zip_Builder.php                # Packages generated code into zip (multi-file aware)
    Admin/                         # A_Plugin_Generator\Admin\
      Admin.php                    # Admin pages, menus, enqueues
    Providers/                     # A_Plugin_Generator\Providers\
      AI_Provider.php              # Common AI provider interface
      OpenAI_Provider.php          # OpenAI implementation
      DeepSeek_Provider.php        # DeepSeek implementation
      Gemini_Provider.php          # Gemini implementation
  admin/                           # Non-class assets (views, CSS, JS)
    views/
      create-plugin.php            # Create/Edit plugin page template (shared)
      list-plugins.php             # List plugins page template
      settings.php                 # Settings page template
    css/
      admin-style.css              # Admin styles (unified button system, cards, tabs, etc.)
    js/
      create-plugin.js             # jQuery for create/edit page (samples, multi-file preview, code editing)
      list-plugins.js              # jQuery for list page (CRUD actions, replace, pagination)
      settings.js                  # jQuery for settings page
```

## Key Conventions

- All REST endpoints require `manage_options` capability (admin only)
- Use `wp_remote_post` / `wp_remote_get` for external AI API calls
- Nonces are not needed for REST (WP REST handles auth via cookies + nonce automatically when using `wp_enqueue_script` with `wp_localize_script`)
- Pass REST base URL and nonce to JS via `wp_localize_script` as `aipgData`
- Sanitize all inputs, escape all outputs
- Use `$wpdb` for custom table queries, prepared statements always
- Error handling: return `WP_Error` from REST callbacks, surface errors in UI via jQuery
- Buttons: always use `.aipg-btn` class for consistent styling; `.aipg-btn-sm` for row actions; `.aipg-btn-danger` for destructive actions; `.aipg-btn-replace` for replace actions
- Edit mode: create page with `?edit_id=X` loads existing plugin data, slug becomes readonly
- AI-generated plugins must NOT use Composer — they are self-contained
