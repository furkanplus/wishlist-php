# Agent Instructions

## Translation Keys

**Always check for translation keys** when adding new UI text:

1. Add keys to `admin.php` `$translationKeys` array with description and default
2. Add translations to `lang/tr.php` (and other language files)
3. Add keys to `window.translations` in `index.php` for JavaScript usage
4. Use `__('key', 'default')` in PHP and `t.key` in JavaScript

Never hardcode user-facing strings.


[Deployment Command Options]
# DO NOT upload .env and other local/development files to FTP.
# config.php is NOT tracked by git (untracked) - keep local + FTP only.
# Deploy using ./deploy.sh (reads credentials from .env)

[Critical Rules]
1. CONFIGURATION PROTECTION: config.php is untracked by git. Deploy to FTP for production DB creds. Never commit to git.
2. SCHEMA ALTERATIONS: If the database schema changes, STOP immediately and alert the human developer.
3. MODULE/COMMAND EXECUTION: Don't verify if a command, module, or package is installed (e.g. using 'which' or checking versions). Just invoke/use it directly.
4. SYNC VERIFICATION: Always run the dry-run check command before committing a deployment sync.
5. AUTO-MIGRATION: config.php and install.php both auto-add missing columns (buyer_message, message_public, is_archived) non-destructively on first load.
6. DOCUMENTATION: Always update AGENTS.md with any code changes. install.php must reflect all schema/runtime changes.

[Architecture Map & Key Files]
* config.php - Main environment settings and DB credentials (local). Includes helper functions like getSetting, setSetting, ensureWishlistColumns (auto-adds missing DB columns).
* index.php - Main public page with wishlist grid view, metadata countdowns, purchase verification modals, and lang buttons.
* admin.php - Administrator console for adding/editing wishlist items, changing passwords, and managing translations.
* login.php - Secure login for admin dashboard. Handles initial creation of credentials on database.
* api/mark-bought.php - Backend API for verifying and marking purchase status.
* api/fetch-metadata.php - Metadata scraping API endpoint.
* api/update-order.php - Admin API to persist drag-and-drop sorting.
* lang/ - Folder for translation matrices. Currently contains tr.php.
* assets/css/style.css - The core style rules, featuring custom amber warning classes (.flash-warning, .empty-warning, .empty-fallback-badge).
* assets/js/app.js - Public scripts for purchase validation/verification workflows.
* assets/js/admin.js - Control panel scripts for scraping, drag-and-drop sorting, and live warning highlighting in translations.
* config.template.php - Template for install.php: full config with {{DB_HOST}} etc. placeholders for auto-generation.
* install.php - Web installer: creates DB schema, admin user, generates config.php with auto-migration.
* deploy.sh - Deployment script; reads FTP credentials from .env, syncs via lftp.

[Active Translation Logic Summary]
- The translation function __($key, $default) is duplicated locally across:
  - index.php
  - admin.php
  - login.php
  - api/mark-bought.php
- Any key that is empty, blank, or contains only whitespace is treated as undefined and falls back automatically to its English default string.
- If translations contain empty textareas on the admin panel, the UI renders amber warnings and displays a badge showing the default English translation fallback.

## Recent Changes (June 2026)

### Buyer Message Feature
Added optional message/note field to "Mark as Bought" flow:
- **index.php**: Added checkbox "Make message visible on public wishlist" + always-visible textarea
- **assets/js/app.js**: Checkbox toggles visibility hint text; includes `message_public` in POST payload
- **api/mark-bought.php**: Appends message to `buyer_proof` with prefix `[Public Message]:` or `[Private Message (Admin Only)]:`
- **admin.php**: Displays full `buyer_proof` with `white-space: pre-wrap` (already existed)
- **index.php (public)**: Parses and displays public messages in bought items
- **Translations added**: `message_public_checkbox`, `message_visibility_private`, `message_visibility_public` (EN/TR)

**No schema changes** - message stored in existing `buyer_proof` TEXT column.

### Buyer Message Database Revision (July 2026)
Moved buyer message from `buyer_proof` to dedicated database columns:
- **schema.sql**: Added `buyer_message` TEXT DEFAULT NULL, `message_public` TINYINT(1) DEFAULT 0 columns
- **config.php**: Added `ensureWishlistColumns()` auto-migration function
- **install.php**: Generates config.php with auto-migration; runs schema with new columns
- **api/mark-bought.php**: Stores message and visibility in separate columns (no longer concatenates to buyer_proof)
- **index.php (public)**: Displays private/public messages with distinct styling from DB columns
- **admin.php**: Shows both message types with colored badges (private=orange, public=purple)
- **Translations added**: `public_message_label`, `private_message_label`, `admin_buyer_public_message`, `admin_buyer_private_message` (EN/TR)

### Archiving Feature (July 2026)
Added ability to hide items from public view while keeping them visible to admin:
- **schema.sql**: Added `is_archived` TINYINT(1) DEFAULT 0 column to `wishlist_items` table
- **index.php**: Public view filters out archived items (`WHERE is_archived = 0`)
- **admin.php**: Archive/unarchive actions (`?archive=ID`, `?unarchive=ID`); archived items shown with orange "ARCHIVED" badge
- **admin.php**: Archive/unarchive buttons with confirmation dialogs in item actions
- **assets/css/style.css**: Added `.btn-warning` class for archive buttons
- **Translations added**: `admin_archive`, `admin_unarchive`, `admin_archived_badge`, `admin_archive_confirm`, `admin_unarchive_confirm` (EN/TR)
- **config.php / install.php**: Auto-add `is_archived` column if missing

### Config Management Update (July 2026)
- **config.php**: Removed from git tracking (untracked), deployed to FTP only for production
- **install.php**: Creates config.php dynamically on first install with auto-migration function
- **AGENTS.md**: Updated deployment rules to reflect config.php workflow

### Deploy Script Addition (July 2026)
- **deploy.sh**: New deployment script that reads FTP credentials from .env file and syncs via lftp
- **.env**: Contains FTP_HOST, FTP_USER, FTP_PASS, FTP_PORT, FTP_REMOTE_DIR for deploy.sh

### Admin Edit Buyer Message Feature (July 2026)
Added ability for admin to edit buyer message and its visibility (public/private) in edit modal:
- **admin.php**: Added translation keys (`admin_buyer_message_label`, `admin_buyer_message_placeholder`, `admin_message_public_label`, `admin_message_public_help`)
- **admin.php**: Updated edit modal to include buyer_message textarea and message_public checkbox
- **admin.php**: Updated edit_item POST handler to save buyer_message and message_public columns
- **admin.php**: Added data attributes to edit buttons (data-buyer-message, data-message-public)
- **assets/js/admin.js**: Updated edit modal handler to populate new fields from data attributes
- **lang/tr.php**: Added Turkish translations for new admin edit modal fields

### Security Hardening (July 2026)
Comprehensive security fixes across the application:
- **install.php**: Fixed SQL injection in database creation via allowlist validation (`/^[a-zA-Z0-9_$]+$/` for db_name)
- **config.php**: Added CSRF protection (`generateCsrfToken()`, `verifyCsrfToken()`), security headers (`setSecurityHeaders()` with CSP, HSTS, X-Frame-Options, etc.), session cookie hardening (SameSite=Strict, Secure, HttpOnly), password validation (min 12 chars), rate limiting helper (`checkRateLimit()`)
- **admin.php**: All state-changing endpoints now require CSRF tokens; converted GET actions (delete, toggle_bought, archive/unarchive) to POST with CSRF; removed detailed error messages
- **login.php**: Session fixation fix (`session_regenerate_id(true)`), login rate limiting (5 attempts/15min per IP via `rate_limits` table), password minimum 12 chars
- **api/fetch-metadata.php**: Enabled SSL verification (`CURLOPT_SSL_VERIFYPEER=true`, `CURLOPT_SSL_VERIFYHOST=2`)
- **api/mark-bought.php**, **index.php**, **admin.php**: Generic error messages (no internal details leaked)
- **assets/js/admin.js**: Fixed XSS in webhook test response - uses `textContent` instead of `innerHTML`
- **config.php**: Added `validateCssColor()` to prevent CSS injection via theme colors
- **schema.sql**: Added `rate_limits` table for login rate limiting

### Security Hardening & Bug Fixes (July 2026 - Batch 2)
Further security hardening and logic patching across the application:
- **config.php**: Added `isSafeUrl()` and `isSafeIp()` helpers for robust SSRF protection. Hardened webhook URL setting to be HTTPS-only and resolved IP-verified. JSON-escaped payload parameters before substitution in custom webhook body templates to prevent JSON syntax corruption. Set `Cache-Control` headers in `setSecurityHeaders()` to block local caching of sensitive info. Disabled cURL redirects (`CURLOPT_FOLLOWLOCATION => false`) on webhooks.
- **api/mark-bought.php**: Converted purchase logic to be atomic (atomic UPDATE with `is_bought = 0` condition + `rowCount()` checking) to prevent TOCTOU race conditions. Appended `source => buyer` metadata inside webhook payload.
- **api/fetch-metadata.php**: Added host-level and redirect-level SSRF checks using `isSafeUrl()`. Replaced cURL followlocation with a manual redirect-checking loop that evaluates each redirected location.
- **api/update-order.php**: Implemented database-level exclusive row locking (`FOR UPDATE`) on the transaction to prevent sorting race conditions.
- **admin.php**: Validated `estimated_price` (between 0 and 999999.99), `webhook_url` (HTTPS-only, SSRF-safe), and theme colors on POST action handles. Triggers `sendWebhook()` with `source => admin` discriminator when admin manually marks an item as bought. Correctly clears `buyer_message` and `message_public` columns when unmarking purchases.

### SAST Findings Fixes (July 2026)
Applied fixes from SAST security audit findings:
- **index.php**: Fixed 2 XSS vectors — wrapped `sprintf()` format string with `h()` escaping for `available_for` and `modal_instructions` translations (`sprintf(h(__('key')), ...)` pattern). Previously, admin-controlled translation values could inject arbitrary HTML/JS into `sprintf()` output.
- **admin.php**: Fixed XSS in `admin_logged_in_as` — separated translation text from HTML markup (`<strong>` moved to template, `%s` removed from translation).
- **api/mark-bought.php**: Added rate limiting via `checkRateLimit("mark-bought:{ip}:{item_id}", 10, 60)` to prevent abuse/enumeration. Added `event_id` (random hex) to webhook payload for idempotency.
- **admin.php**: Added `event_id` to admin-triggered webhook payload. Added `err_rate_limited` translation key.
- **api/update-order.php**: Added `sort($sanitizedIds)` before `FOR UPDATE` query for explicit lock-order guarantee.
- **logout.php**: Changed to POST-only with CSRF token verification; updated admin.php logout link to POST form.
- **install.php**: Added `install.lock` file creation on successful install and checks for it on startup, preventing installer re-enable if `config.php` is lost.
- **lang/tr.php**: Added Turkish translation for `err_rate_limited`.

### Webhook Bug Fixes (July 2026)
Fixed webhooks not actually sending with the configured HTTP method:
- **config.php**: `$method` was read from settings but never applied to cURL (`CURLOPT_POST => true` was hardcoded). Now uses `CURLOPT_POST` for POST and `CURLOPT_CUSTOMREQUEST` for PUT/PATCH. Also removed dead duplicate `$debugInfo` block. Added IPv6 (AAAA) DNS fallback in `isSafeUrl()` when `gethostbynamel()` returns empty.
- **assets/js/admin.js**: Fixed debug display — was reading wrong property names (`url`/`method`/`body` vs PHP's `request_url`/`request_method`/`request_body`). Added header display and `white-space: pre-wrap` for readable output.
- **admin.php**: `toggle_bought` webhook call now checks result and sets `flash_warning` on failure.
- **api/mark-bought.php**: Webhook failures logged via `error_log()` instead of silently discarded.

### Config Template Extraction (July 2026)
Generated config.php now stays in sync with the full helper set:
- **config.template.php**: New committed template with `{{DB_HOST}}` etc. placeholders, containing ALL functions (CSRF, security headers, rate limiting, currency, webhooks, SSRF-safe URL validation, theme helpers). Replaces the old inline string that was missing ~20 functions.
- **install.php**: Reads `config.template.php`, replaces placeholders via `str_replace` + `var_export()`, writes `config.php`. No more escaping hell or drift.
- **config.php**: Fixed PDO error message to be generic (was leaking `$e->getMessage()`).
- **AGENTS.md**: Updated architecture map with config.template.php.

