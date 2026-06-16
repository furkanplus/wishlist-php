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
# config.php IS pushed to GitHub but excluded from FTP sync.
exclude_options = --exclude=.env --exclude=.git/ --exclude=.gitignore --exclude=run-local.sh --exclude=schema.sql --exclude=README.md --exclude=install.php --exclude=.DS_Store --exclude=READFIRST.agent

[Critical Rules]
1. CONFIGURATION PROTECTION: Never upload config.php to the FTP server. It contains production database settings.
2. SCHEMA ALTERATIONS: If the database schema changes, STOP immediately and alert the human developer.
3. MODULE/COMMAND EXECUTION: Don't verify if a command, module, or package is installed (e.g. using 'which' or checking versions). Just invoke/use it directly.
4. SYNC VERIFICATION: Always run the dry-run check command before committing a deployment sync.

[Architecture Map & Key Files]
* config.php - Main environment settings and DB credentials (local). Includes helper functions like getSetting, setSetting.
* index.php - Main public page with wishlist grid view, metadata countdowns, purchase verification modals, and lang buttons.
* admin.php - Administrator console for adding/editing wishlist items, changing passwords, and managing translations.
* login.php - Secure login for admin dashboard. Handles initial creation of credentials on database.
* api/mark-bought.php - Backend API for verifying and marking purchase status.
* lang/ - Folder for translation matrices. Currently contains tr.php.
* assets/css/style.css - The core style rules, featuring custom amber warning classes (.flash-warning, .empty-warning, .empty-fallback-badge).
* assets/js/app.js - Public scripts for purchase validation/verification workflows.
* assets/js/admin.js - Control panel scripts for scraping, drag-and-drop sorting, and live warning highlighting in translations.

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
