# AI Plugin Generator — WordPress Plugin

## Overview

A WordPress plugin that lets admins generate other WordPress plugins using AI. Users describe what they want, an AI provider generates the PHP code, and the plugin packages it as a downloadable/installable zip.

## Requirements Summary

### Admin UI — Create/Edit Plugin
- Form with: plugin name, slug (auto-generated from name, editable), requirements/description of desired functionality
- Optional fields: author name, version, dependencies, description, and other plugin headers
- **Samples dropdown** at top-right of Plugin Name label — 10 sample plugins (Coming Soon Page, Simple Testimonials, FAQ Accordion, Custom Login Page, Reading Time Estimator, Social Share Buttons, Back to Top Button, Post Views Counter, Simple Notice Bar, Duplicate Post). Selecting one fills all form fields. Only shown in create mode, not edit mode.
- **Slug conflict check** — on slug change, checks against local DB, installed plugins directory, and wordpress.org plugin API. Shows green "available" or red "conflict" message. Blocks generation if conflict found. Only checked in create mode (slug is readonly in edit mode).
- "Generate Plugin" button sends requirements to an AI provider and displays generated code
- Split layout: form on the left, code preview on the right
- Code preview shows a **loading spinner animation** while AI generates
- **Multi-file support**: AI can return multiple files, displayed as separate tabs in the preview
- **Edit button** in preview header toggles between read-only code view and editable textarea. Allows admin to edit code before saving.
- Confirm button finalizes generation — creates/updates a zip on filesystem with metadata in DB
- **Same page for create and edit** — editing uses `?edit_id=X` param, no modal popup. Slug becomes readonly in edit mode.
- When editing and confirming, the existing DB record is **updated** (not duplicated)

### Admin UI — List Plugins (All Plugins)
- Wrapped in card UI with header ("All Plugins"), same styling as Create Plugin and Settings pages
- Table display of all generated plugins with columns: name, slug, version, status, created date, actions
- Actions per plugin: download zip, edit (links to create page with edit_id), install, activate/deactivate, delete
- **Replace** button (yellow, with "Replace" label) appears when an installed plugin has been regenerated (zip is newer than installed files) — handles deactivate → uninstall → reinstall → reactivate cycle
- **Delete** removes the plugin from everywhere: deactivates (if active), uninstalls from WP plugins directory, deletes DB record and zip file
- No search — not needed for a small self-generated plugin list
- Pagination for large lists
- Menu position: **3** (right after Dashboard)

### Settings
- AI provider selection dropdown (OpenAI, DeepSeek, Gemini)
- API key field (plain password input, no show/hide toggle icon)
- Model override field (optional, uses provider default if empty)
- Settings saved via REST API

### UI Style
- Clean, modern admin UI with AI-flavored aesthetic
- Unified `.aipg-btn` button system across all pages (consistent sizing, colors, hover/focus states)
- CSS custom properties for consistent theming
- Card-based layout with gradient headers on all pages

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
- `DELETE /plugins/{id}` — delete fully: deactivate + uninstall from WP + delete DB record + delete zip
- `POST /plugins/{id}/install` — install plugin on current site
- `POST /plugins/{id}/activate` — activate installed plugin
- `POST /plugins/{id}/deactivate` — deactivate installed plugin
- `POST /plugins/{id}/replace` — replace installed plugin with updated zip (deactivate → uninstall → reinstall → reactivate if was active)
- `GET /plugins/{id}/download` — get download URL for zip file
- `GET /check-slug?slug=xxx` — check slug against local DB, installed plugins, and wordpress.org API
- `GET /settings` — get current settings (API key masked)
- `PUT /settings` — update settings (provider, API key, model)
- `GET /providers` — list available AI providers

### Storage
- **Database**: Custom table (`{prefix}aipg_plugins`) for metadata — name, slug, version, author, description, requirements, file path, status, timestamps. No generated code stored in DB — zip on filesystem is the source of truth.
- **Filesystem**: Zips stored in `wp-content/uploads/ai-plugin-generator/`

### AI Integration
- Single-shot generation: AI receives the requirements and generates the complete plugin code
- Providers share common logic via abstract base class `AI_Provider`
- Providers: OpenAI (default model: gpt-4o, max_tokens: 16000), DeepSeek (default: deepseek-chat, max_tokens: 8192), Gemini (default: gemini-2.0-flash, maxOutputTokens: 16000)
- **Timeouts**: 300s for generation, 30s for key validation
- Multi-file output format: `=== filename.php ===` headings followed by ``` code blocks
- Raw AI response returned to frontend — JS parses multi-file format
- System prompt instructs AI to:
  - Follow WPCS strictly for PHP, CSS, and JS
  - NOT use Composer or external dependencies — generated plugins must be fully self-contained
  - Use clean, modern UI/UX for frontend output
  - Use `=== filename ===` headings for multi-file responses
  - **Self-review code** before responding: check for syntax errors, undefined functions, missing brackets, incorrect hook usage, and anything that could cause a fatal error or site crash

### Provider Architecture
- `AI_Provider` (abstract class) contains all shared logic:
  - Constructor loads `api_key` and `model` from `aipg_settings` option
  - `get_system_prompt()`, `build_prompt()`, `extract_code()` — shared prompt/response handling
  - `check_api_key()` — shared validation with provider name in error message
  - `parse_response()` — shared HTTP response parsing + error handling
  - Timeout properties: `$generate_timeout = 300`, `$validate_timeout = 30`
- Each provider (`OpenAI`, `DeepSeek`, `Gemini`) only implements:
  - `get_name()`, `get_slug()`, `get_default_model()` — identity
  - `generate()` — provider-specific API call format + response extraction
  - `validate_api_key()` — provider-specific validation call
- Provider classes are named without `_Provider` suffix since they're already under the `Providers` namespace (e.g., `Providers\DeepSeek`, not `Providers\DeepSeek_Provider`)

### Zip Building
- `Zip_Builder` accepts an array of `{filename, code}` objects (multi-file) or a raw string (single file fallback)
- Files placed inside a directory matching the plugin slug: `slug/filename.php`

### Plugin Install/Replace/Delete Flow
- `Plugin_Installer::install()` — uses `Plugin_Upgrader` to install from zip
- `Plugin_Installer::needs_replace()` — compares zip mtime vs installed plugin file mtime
- `Plugin_Installer::uninstall_plugin()` — removes plugin directory from `wp-content/plugins/` via `WP_Filesystem`
- Replace flow: deactivate (if active) → remove old files → install from updated zip → reactivate (if was active)
- Delete flow: deactivate (if active) → uninstall from WP → delete DB record → delete zip file

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
    Plugin_Installer.php           # Install/activate/deactivate/replace/uninstall
    Code_Generator.php             # Orchestrates AI code generation, provider factory
    Zip_Builder.php                # Packages generated code into zip (multi-file aware)
    Admin/                         # A_Plugin_Generator\Admin\
      Admin.php                    # Admin pages, menus (position 3), enqueues
    Providers/                     # A_Plugin_Generator\Providers\
      AI_Provider.php              # Abstract base class with shared logic
      OpenAI.php                   # OpenAI implementation
      DeepSeek.php                 # DeepSeek implementation
      Gemini.php                   # Gemini implementation
  admin/                           # Non-class assets (views, CSS, JS)
    views/
      create-plugin.php            # Create/Edit plugin page template (shared, with samples dropdown)
      list-plugins.php             # List plugins page template (card UI, no search)
      settings.php                 # Settings page template (no API key toggle)
    css/
      admin-style.css              # Admin styles (unified buttons, cards, tabs, slug status, etc.)
    js/
      create-plugin.js             # jQuery: samples, slug check, generate, multi-file preview, code editing
      list-plugins.js              # jQuery: list, pagination, install/activate/deactivate/replace/delete
      settings.js                  # jQuery: load/save provider settings
```

## Key Conventions

- All REST endpoints require `manage_options` capability (admin only)
- Use `wp_remote_post` / `wp_remote_get` for external AI API calls
- Nonces are not needed for REST (WP REST handles auth via cookies + nonce automatically when using `wp_enqueue_script` with `wp_localize_script`)
- Pass REST base URL and nonce to JS via `wp_localize_script` as `aipgData`
- Sanitize all inputs, escape all outputs
- Use `$wpdb` for custom table queries, prepared statements always
- Error handling: return `WP_Error` from REST callbacks, surface errors in UI via jQuery
- Buttons: always use `.aipg-btn` class; `.aipg-btn-sm` for row actions; `.aipg-btn-danger` for destructive; `.aipg-btn-replace` (yellow) for replace
- Edit mode: create page with `?edit_id=X` loads existing plugin data, slug becomes readonly
- AI-generated plugins must NOT use Composer — they are self-contained
- Provider classes use short names under the `Providers` namespace (e.g., `DeepSeek`, not `DeepSeek_Provider`)
- Deleting a plugin cleans up everything: WP plugin files, DB record, and zip
