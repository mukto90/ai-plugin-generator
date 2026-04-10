# AI Plugin Generator — WordPress Plugin

## Overview

A WordPress plugin that lets admins generate other WordPress plugins using AI. Users describe what they want, the plugin forwards the request to the **PluginDaddy** service (hosted at `plugindaddy.com`), which talks to the real AI providers and returns generated PHP code. The plugin then packages the result as a downloadable/installable zip.

This repo also contains two sibling deliverables that are **not shipped** with the main plugin and are deployed separately:
- `website/` — the marketing landing page for plugindaddy.com
- `service/` — the WordPress plugin that runs on plugindaddy.com, built on WordPress + Easy Digital Downloads + EDD Recurring Payments. It authenticates requests, manages WP user accounts + credits, routes generation calls to real AI providers (OpenAI, DeepSeek, Claude) based on free/paid tier, and returns the response.

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
- **No BYOK** — users do not bring their own OpenAI/DeepSeek/etc. keys. The plugin only stores a PluginDaddy service API key.
- **Request key form**: a single email field. Submitting it asks PluginDaddy to email an API key to that address.
- **Verify form**: shown after the request is sent. Two fields — email + API key — used to save and verify the credentials against PluginDaddy. On success, the saved key is the credential used for all generation requests.
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
- `GET /settings` — get current settings (API key masked, email visible)
- `PUT /settings` — update settings (email + API key); verifies against PluginDaddy before saving
- `POST /settings/request-key` — ask PluginDaddy to email a new API key to the supplied address

### Storage
- **Database**: Custom table (`{prefix}aipg_plugins`) for metadata — name, slug, version, author, description, requirements, file path, status, timestamps. No generated code stored in DB — zip on filesystem is the source of truth.
- **Filesystem**: Zips stored in `wp-content/uploads/ai-plugin-generator/`

### AI Integration (via PluginDaddy service)
- The main plugin does **not** talk to OpenAI/DeepSeek/Claude directly. All AI calls go to the PluginDaddy service.
- Service base URL is stored in a constant: `AIPG_SERVICE_URL` (default `https://plugindaddy.com`) so it can be changed for staging/testing.
- Generate endpoint on the service: `{AIPG_SERVICE_URL}/wp-json/plugindaddy/v1/plugin/generate`
- The main plugin sends **only the requirements** (plus plugin name/slug/metadata) along with the stored API key. All system prompts, provider selection, model choice, max_tokens, and WPCS guidance live on the **service side**, not here.
- **Timeouts**: 300s for generation, 30s for key verification.
- Multi-file output format (produced by the service, parsed by this plugin's JS): `=== filename.php ===` headings followed by ``` code blocks.
- Raw response from the service is returned to the frontend — JS parses the multi-file format.

### Service Client
- A single class `Service_Client` (under `A_Plugin_Generator\`) replaces the old provider architecture.
- Responsibilities:
  - Constructor loads `email` and `api_key` from `aipg_settings` option and reads `AIPG_SERVICE_URL`.
  - `generate( $requirements, $meta )` — POSTs to `/wp-json/plugindaddy/v1/plugin/generate`, returns raw response body or `WP_Error`.
  - `request_key( $email )` — POSTs to `/wp-json/plugindaddy/v1/keys/request` to trigger the "email me a key" flow.
  - `verify_key( $email, $api_key )` — POSTs to `/wp-json/plugindaddy/v1/keys/verify` used by the Settings save flow.
  - `parse_response()` — shared HTTP response parsing + error surfacing.
- Timeout properties: `$generate_timeout = 300`, `$verify_timeout = 30`.

### Zip Building
- `Zip_Builder` accepts an array of `{filename, code}` objects (multi-file) or a raw string (single file fallback)
- Files placed inside a directory matching the plugin slug: `slug/filename.php`
- `normalize_filename()` strips leading/trailing slashes, `./` and `..` segments, and any leading `{slug}/` prefix the AI may have added — prevents double-nested zips like `slug/slug/slug.php` that WP's installer rejects with "No valid plugins were found"

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
    Code_Generator.php             # Orchestrates generation via Service_Client
    Service_Client.php             # HTTP client to plugindaddy.com (generate/request_key/verify_key)
    Zip_Builder.php                # Packages generated code into zip (multi-file aware)
    Admin/                         # A_Plugin_Generator\Admin\
      Admin.php                    # Admin pages, menus (position 3), enqueues
  admin/                           # Non-class assets (views, CSS, JS)
    views/
      create-plugin.php            # Create/Edit plugin page template (shared, with samples dropdown)
      list-plugins.php             # List plugins page template (card UI, no search)
      settings.php                 # Settings page template (request-key + verify forms)
    css/
      admin-style.css              # Admin styles (unified buttons, cards, tabs, slug status, etc.)
    js/
      create-plugin.js             # jQuery: samples, slug check, generate, multi-file preview, code editing
      list-plugins.js              # jQuery: list, pagination, install/activate/deactivate/replace/delete
      settings.js                  # jQuery: request-key + verify flow
  website/                         # NOT shipped — landing page for plugindaddy.com (deployed separately)
    index.html
    assets/
      style.css
      script.js
  service/                         # NOT shipped — separate WP plugin hosted on plugindaddy.com
    plugindaddy-service.php        # Main plugin file, bootstrap, autoloader, EDD-required guard, defaults
    includes/
      Plugin.php                   # Bootstrap (rest_api_init, EDD_Integration, Admin)
      Installer.php                # Creates {prefix}plugindaddy_credits and _plugins tables; drops legacy
      Rest_Controller.php          # /plugindaddy/v1/plugin/generate, /keys/request, /keys/verify
      User_Manager.php             # Transient-to-user flow; WP user creation on verify; key hash in user meta
      Credit_Manager.php           # Free (rolling window) + paid (grants − usage) balance; tier selection
      Plugin_Log.php               # Writes a row to plugindaddy_plugins on successful generation
      Prompt_Builder.php           # System + user prompts (WPCS rules, multi-file format, self-review)
      AI_Router.php                # Picks provider + model per tier from settings
      EDD_Integration.php          # Variable-price "Credits" field; grants on purchase + renewal
      Admin.php                    # PluginDaddy menu, Settings page, Plugins Log page
      Plugins_List_Table.php       # WP_List_Table implementation for the plugins log
      Providers/
        AI_Provider.php            # Abstract base
        OpenAI.php
        DeepSeek.php
        Claude.php
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
- Deleting a plugin cleans up everything: WP plugin files, DB record, and zip
- The main plugin never calls OpenAI/DeepSeek/Claude directly — all AI calls go through `Service_Client` → PluginDaddy
- `AIPG_SERVICE_URL` is the single source of truth for the service base URL (define it in `ai-plugin-generator.php`)
- `aipg_settings` option shape: `{ email: string, api_key: string }` — no provider/model fields

## Service (plugindaddy.com) — separate plugin under `service/`

This is a standalone WordPress plugin deployed to plugindaddy.com. Not shipped with the main plugin. Versioned independently; DB schema version tracked via the `plugindaddy_service_db_version` option.

- **Stack**: WordPress + Easy Digital Downloads + EDD Recurring Payments
- **Required plugin**: EDD is declared via the `Requires Plugins` header and enforced by a `plugins_loaded` guard — the service refuses to boot and shows an admin error notice if EDD is not active
- **Purpose**: authenticate incoming requests, enforce per-user credit quotas (free + paid), build the full prompt, forward to the configured real AI provider, log successful generations

### REST API (`plugindaddy/v1`)
- `POST /plugin/generate` — body: `{ api_key, email, requirements, meta }`. Authenticates via `User_Manager::authenticate()`, asks `Credit_Manager::select_tier_to_charge()` for a free→paid preference, dispatches via `AI_Router`, logs a row in `plugindaddy_plugins` on success. Returns `{ code: "<raw AI text>" }`.
- `POST /keys/request` — body: `{ email }`. Generates a key, stores it in a **transient** keyed by email hash (10-minute TTL), `error_log`s it for testing, emails it via `wp_mail` with a `From:` header. No WP user is created at this stage.
- `POST /keys/verify` — body: `{ email, api_key }`. Looks up the transient; on match, creates a WP user (`subscriber` role) if one doesn't exist for that email, stores `sha256($key)` in the `_plugindaddy_api_key_hash` user meta, clears the transient. Returns `{ valid, user_id, free_available, paid_available }`.

### Accounts & API keys (`User_Manager`)
- The WP user is the canonical identifier; the `user_id` is used everywhere downstream (credits, logs)
- Re-requesting a key for an existing email **rotates** it: the transient overwrites, and on next verify the user-meta hash is overwritten too
- `authenticate( email, api_key )` = look up user by email → `hash_equals` against stored hash → return user_id or `WP_Error`
- Testing: `issue_and_email()` calls `error_log( '[PluginDaddy] Issued pending key to <email>: <key>' )` so keys can be grabbed from the PHP log without inbox access

### Credits (`Credit_Manager`)
- Two buckets per user, computed on the fly:
  - **Free** = `free_allowance − COUNT(plugins WHERE user_id=X AND tier='free' AND created_at > NOW() − INTERVAL <free_period>)`. Rolling window, not calendar-based.
  - **Paid** = `SUM(credits.amount WHERE user_id=X AND tier='paid') − COUNT(plugins WHERE user_id=X AND tier='paid')`
- `select_tier_to_charge()` → `'free'` if available, else `'paid'`, else `null` (→ 429)
- Credits never expire and have no `used` column — usage is implicit from the plugins log
- `grant_paid( user_id, amount, meta )` inserts a single ledger row (source: `edd_purchase` / `edd_renewal` / `manual`)
- Free allowance defaults come from constants in `plugindaddy-service.php`: `PLUGINDADDY_FREE_ALLOWANCE_DEFAULT` (int) and `PLUGINDADDY_FREE_PERIOD_DEFAULT` (`day|week|month|year`), both overridable via `wp-config.php` and via settings

### AI routing (`AI_Router`)
- Takes a tier string (`free`/`paid`) and picks provider + model from settings: `{tier}_provider` + `{tier}_model`
- Admin chooses each tier's provider independently (e.g. free → DeepSeek, paid → OpenAI or Claude)
- Returns `{ provider_slug, text }` so the caller can record which provider answered
- All system prompts (WPCS rules, no-Composer constraint, multi-file `=== filename ===` format, flat-filename rule, self-review instructions, clean-UI guidance) live in `Prompt_Builder` in the service, not the main plugin

### EDD integration
- A single EDD download is designated as the "credits product" via the settings page (`edd_product_id`)
- Each variable price on that download gets an extra **Credits** field rendered via `edd_download_price_table_head` + `edd_download_price_table_row`, stored as `_edd_variable_prices[price_id][plugindaddy_credits]` — EDD persists it alongside its own fields
- `edd_complete_purchase` walks the cart, matches the configured product_id, looks up the purchased `price_id`'s credit amount, calls `User_Manager::find_or_create_user( buyer_email )`, and `Credit_Manager::grant_paid()`
- `edd_subscription_post_renew` does the same with `source='edd_renewal'`
- **Purchases never issue or email API keys** — they only add credit ledger rows. Users still obtain their key via the /keys/request → /keys/verify flow

### Custom tables
- `{prefix}plugindaddy_credits` — grants ledger (paid only in practice; schema supports `tier`=`free` for future manual grants)
  - `id, user_id, tier, amount, source, edd_payment_id, edd_price_id, note, created_at`
- `{prefix}plugindaddy_plugins` — log of successfully generated plugins; each row doubles as a credit-consumption marker
  - `id, user_id, plugin_name, plugin_slug, description, tier, provider, created_at`
- `Installer::maybe_upgrade()` runs on `plugins_loaded` and `dbDelta`s both tables when the version option changes; also drops the legacy 1.x tables (`plugindaddy_keys`, `plugindaddy_requests`) and the `plugindaddy_keys` option

### Admin UI (`Admin.php`, `Plugins_List_Table.php`)
- Top-level **PluginDaddy** menu at position 58 with two submenus:
  - **Settings** — Provider credentials (OpenAI / DeepSeek / Claude API keys), Free tier (allowance + `day/week/month/year` selector + provider + model override), Paid tier (provider + model override), EDD product selector (dropdown of all `download` posts)
  - **Plugins Log** — WP_List_Table showing: User (display name + email, linked to user edit), Plugin (name + slug), Description (truncated 240 chars), Tier (color-coded Free/Paid), Provider, Created. Sortable by plugin_name / tier / created_at. Paginated 20/page.
- Settings stored in the `plugindaddy_service_settings` option; all fields sanitized with whitelist-based fallbacks

### Constants (service plugin file)
- `PLUGINDADDY_SERVICE_VERSION`, `PLUGINDADDY_SERVICE_FILE`, `PLUGINDADDY_SERVICE_PATH`, `PLUGINDADDY_SERVICE_URL`
- `PLUGINDADDY_FREE_ALLOWANCE_DEFAULT` (default `1`) — can be overridden in `wp-config.php`
- `PLUGINDADDY_FREE_PERIOD_DEFAULT` (default `'month'`) — can be overridden in `wp-config.php`

## Website (`website/`)

Static one-page marketing site for plugindaddy.com. Plain HTML/CSS/JS, no build step. Deployed separately from the WordPress plugins.
