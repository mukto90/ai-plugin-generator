# AI Plugin Generator — WordPress Plugin

## Overview

A WordPress plugin that lets admins generate other WordPress plugins using AI. Users describe what they want, an AI provider generates the PHP code, and the plugin packages it as a downloadable/installable zip.

## Requirements Summary

### Admin UI — Create Plugin
- Form with: plugin name, slug (auto-generated from name, editable), requirements/description of desired functionality
- Optional fields: author name, version, dependencies, description, and other plugin headers
- "Generate Plugin" button sends requirements to an AI provider and displays generated code
- Split layout: form on the left, code preview on the right
- Confirm button finalizes generation — creates a zip stored on filesystem with metadata in DB

### Admin UI — List Plugins
- Use `WP_List_Table` to display all generated plugins
- Actions per plugin: download zip, edit & regenerate, install on current site, activate/deactivate, delete

### Settings
- AI provider selection (OpenAI, DeepSeek, Gemini, etc.)
- API key/credential management per provider

### UI Style
- Clean, modern admin UI with an AI-flavored aesthetic

## Technical Decisions

### PHP
- **Minimum PHP**: 7.4
- **Minimum WordPress**: 5.8
- **Coding Standards**: WordPress Coding Standards (WPCS)
- **Prefix**: `aipg_` for functions/hooks, `AIPG_` for constants
- **Namespace**: `A_Plugin_Generator\`
- **File naming**: Class files use `class-{name}.php` (WP convention)

### Architecture
- **API-first**: All backend operations exposed via WP REST API (`/wp-json/aipg/v1/`)
- **No admin-ajax**: Do NOT use `admin-ajax.php` — all async communication goes through REST endpoints
- **jQuery**: Use jQuery (bundled with WP) for all frontend JS — no vanilla JS, no frontend build tools
- **No build step**: Plain CSS and jQuery, enqueued via `wp_enqueue_*`

### REST API Endpoints (planned)
- `POST /plugins` — generate a new plugin (sends requirements to AI, returns generated code)
- `POST /plugins/{id}/confirm` — confirm and package plugin as zip
- `GET /plugins` — list all generated plugins
- `GET /plugins/{id}` — get single plugin details
- `PUT /plugins/{id}` — update requirements and regenerate
- `DELETE /plugins/{id}` — delete a generated plugin
- `POST /plugins/{id}/install` — install plugin on current site
- `POST /plugins/{id}/activate` — activate installed plugin
- `POST /plugins/{id}/deactivate` — deactivate installed plugin
- `GET /plugins/{id}/download` — download zip file
- `GET /settings` — get current settings
- `PUT /settings` — update settings (provider, API key)

### Storage
- **Database**: Custom table (`{prefix}aipg_plugins`) for metadata — name, slug, version, author, requirements, file path, status, timestamps (no generated code — zip on filesystem is the source of truth)
- **Filesystem**: Zips stored in `wp-content/uploads/ai-plugin-generator/`

### AI Integration
- Single-shot generation: AI receives the requirements and generates the complete plugin code
- Support multiple providers via a common interface/adapter pattern
- Providers: OpenAI, DeepSeek, Gemini (extensible)

## Project Structure (planned)

```
ai-plugin-generator/
  ai-plugin-generator.php          # Main plugin file, bootstrap
  uninstall.php                    # Cleanup on uninstall
  includes/
    class-plugin.php               # Core plugin class (singleton, hooks, init)
    class-activator.php            # Activation logic (DB tables, dirs)
    class-deactivator.php          # Deactivation logic
    class-rest-controller.php      # REST API registration and routing
    class-plugin-manager.php       # CRUD for generated plugins (DB + filesystem)
    class-plugin-installer.php     # Install/activate/deactivate generated plugins
    class-code-generator.php       # Orchestrates AI code generation
    class-zip-builder.php          # Packages generated code into zip
  includes/providers/
    interface-ai-provider.php      # Common AI provider interface
    class-openai-provider.php      # OpenAI implementation
    class-deepseek-provider.php    # DeepSeek implementation
    class-gemini-provider.php      # Gemini implementation
  admin/
    class-admin.php                # Admin pages, menus, enqueues
    views/
      create-plugin.php            # Create plugin page template
      list-plugins.php             # List plugins page template
      settings.php                 # Settings page template
    css/
      admin-style.css              # Admin styles
    js/
      create-plugin.js             # jQuery for create page (form, preview, confirm)
      list-plugins.js              # jQuery for list page actions
      settings.js                  # jQuery for settings page
```

## Key Conventions

- All REST endpoints require `manage_options` capability (admin only)
- Use `wp_remote_post` / `wp_remote_get` for external AI API calls
- Nonces are not needed for REST (WP REST handles auth via cookies + nonce automatically when using `wp_enqueue_script` with `wp_localize_script`)
- Pass REST base URL and nonce to JS via `wp_localize_script`
- Sanitize all inputs, escape all outputs
- Use `$wpdb` for custom table queries, prepared statements always
- Error handling: return `WP_Error` from REST callbacks, surface errors in UI via jQuery
