<?php
// admin.php
if (isset($_GET['lang'])) {
    $requestedLang = strtolower($_GET['lang']);
    if ($requestedLang === 'en' || (preg_match('/^[a-z]{2,3}(_[a-z]{2,4})?$/i', $requestedLang) && file_exists(__DIR__ . '/lang/' . $requestedLang . '.php'))) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['lang'] = $requestedLang;
        setcookie('lang', $requestedLang, time() + (365 * 24 * 60 * 60), '/');
    }
    $cleanUri = strtok($_SERVER['REQUEST_URI'], '?');
    $queryParams = $_GET;
    unset($queryParams['lang']);
    if (!empty($queryParams)) {
        $cleanUri .= '?' . http_build_query($queryParams);
    }
    header('Location: ' . $cleanUri);
    exit;
}

if (!file_exists(__DIR__ . '/config.php')) {
    header("Location: install.php");
    exit;
}
require_once __DIR__ . '/config.php';

if (!function_exists('getAvailableLanguages')) {
    function getAvailableLanguages() {
        $langs = ['en'];
        $langDir = __DIR__ . '/lang';
        if (is_dir($langDir)) {
            $files = glob($langDir . '/*.php');
            if ($files) {
                foreach ($files as $file) {
                    $code = basename($file, '.php');
                    if ($code !== 'en' && preg_match('/^[a-z]{2,3}(_[a-z]{2,4})?$/i', $code)) {
                        $langs[] = strtolower($code);
                    }
                }
            }
        }
        return array_unique($langs);
    }
}

if (!function_exists('__')) {
    function __($key, $default = '') {
        static $translations = null;
        
        $lang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'en';
        if ($lang === 'en') {
            return $default;
        }
        
        if ($translations === null) {
            $translations = [];
            $file = __DIR__ . '/lang/' . $lang . '.php';
            if (file_exists($file)) {
                $translations = include $file;
            } else {
                // Backward compatibility / auto-migration from database
                global $pdo;
                if (isset($pdo)) {
                    try {
                        $stmt = $pdo->prepare('SELECT `translation_key`, `translation_value` FROM `translations` WHERE `lang` = ?');
                        $stmt->execute([$lang]);
                        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        if ($rows) {
                            $translations = $rows;
                            // Attempt to cache translations to static PHP file
                            $langDir = __DIR__ . '/lang';
                            if (!is_dir($langDir)) {
                                @mkdir($langDir, 0755, true);
                            }
                            @file_put_contents($file, '<?php' . PHP_EOL . 'return ' . var_export($translations, true) . ';');
                        }
                    } catch (PDOException $e) {
                        // translations table doesn't exist
                    }
                }
            }
        }
        
        if (isset($translations[$key]) && trim($translations[$key]) !== '') {
            return $translations[$key];
        }
        return $default;
    }
}

$currentLang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'en';


// Enforce admin login
requireAdmin();

$translationKeys = [
    'index_title' => [
        'desc' => 'Public page title',
        'default' => 'My Wishlist'
    ],
    'index_subtitle' => [
        'desc' => 'Public page welcome message/subtitle',
        'default' => "Welcome! Here are some items I've been wishing for. If you purchase something, please mark it as bought to avoid duplicates."
    ],
    'admin_dashboard_link' => [
        'desc' => 'Link to admin dashboard',
        'default' => '🔒 Admin Dashboard'
    ],
    'notes_from_owner' => [
        'desc' => 'Header for owner notes',
        'default' => '📝 Notes from Owner'
    ],
    'shipping_address' => [
        'desc' => 'Header for shipping address',
        'default' => '📍 Shipping Address'
    ],
    'available_for' => [
        'desc' => 'Countdown timer label (contains %s)',
        'default' => '⏱️ Available for: %s'
    ],
    'items_list' => [
        'desc' => 'Header for the items grid',
        'default' => '🎁 Items List'
    ],
    'empty_wishlist' => [
        'desc' => 'Message displayed when wishlist has no items',
        'default' => 'No items have been added to the wishlist yet.'
    ],
    'bought_badge' => [
        'desc' => 'Badge text for purchased items',
        'default' => 'BOUGHT'
    ],
    'no_image_loaded' => [
        'desc' => 'Fallback image text if load fails',
        'default' => 'No Image Loaded'
    ],
    'no_image_provided' => [
        'desc' => 'Fallback image text if no URL provided',
        'default' => 'No Image'
    ],
    'view_product' => [
        'desc' => 'Button to open product URL',
        'default' => '🌐 View Product'
    ],
    'mark_bought' => [
        'desc' => 'Button to mark item as purchased',
        'default' => "🎁 I've Bought This"
    ],
    'purchased_by' => [
        'desc' => 'Confirmation text for bought items (contains %s)',
        'default' => '✔ Purchased by %s'
    ],
    'anonymous_friend' => [
        'desc' => 'Fallback display name for anonymous buyers',
        'default' => 'Anonymous Friend'
    ],
    'modal_title' => [
        'desc' => 'Title of verification modal',
        'default' => '🎁 Mark as Bought'
    ],
    'modal_instructions' => [
        'desc' => 'Instructions on verification modal (contains %s for item name)',
        'default' => 'You are marking %s as purchased. To protect against accidental duplicates, you must provide verification.'
    ],
    'buyer_name_label' => [
        'desc' => 'Label for buyer name input field',
        'default' => 'Your Name / Nickname (Optional)'
    ],
    'buyer_name_placeholder' => [
        'desc' => 'Placeholder for buyer name input field',
        'default' => 'e.g. Secret Santa, Aunt Mary'
    ],
    'buyer_proof_label' => [
        'desc' => 'Label for verification proof input field',
        'default' => 'Tracking Link OR Order ID (Required)'
    ],
    'buyer_proof_placeholder' => [
        'desc' => 'Placeholder for verification proof input field',
        'default' => 'e.g. UPS link or Amazon Order ID'
    ],
    'buyer_proof_desc' => [
        'desc' => 'Help text below verification input field',
        'default' => 'This proof will only be visible to the wishlist owner to verify the purchase.'
    ],
    'confirm_purchase' => [
        'desc' => 'Button label to submit purchase',
        'default' => 'Confirm Purchase'
    ],
    'modal_success_title' => [
        'desc' => 'Success title after purchase confirmation',
        'default' => 'Purchase Verified!'
    ],
    'modal_success_desc' => [
        'desc' => 'Success text after purchase confirmation',
        'default' => 'The item has been marked as bought and moved to the bottom of the list.'
    ],
    'err_item_id_required' => [
        'desc' => 'API Error: Item ID is missing',
        'default' => 'Item ID is required.'
    ],
    'err_proof_required' => [
        'desc' => 'API Error: Buyer proof is missing',
        'default' => 'You must provide a Tracking Link or Order ID as proof.'
    ],
    'err_item_not_found' => [
        'desc' => 'API Error: Item does not exist',
        'default' => 'Wishlist item not found.'
    ],
    'err_already_bought' => [
        'desc' => 'API Error: Item is already marked as bought',
        'default' => 'This item has already been marked as bought.'
    ],
    'success_marked_bought' => [
        'desc' => 'API Success: Item successfully marked as bought',
        'default' => 'Thank you! Item successfully marked as bought.'
    ],
    'err_updating_failed' => [
        'desc' => 'API Error: DB transaction failed',
        'default' => 'An error occurred while updating the item.'
    ],
    'login_title_create' => [
        'desc' => 'Document title for admin signup',
        'default' => 'Create Admin Account'
    ],
    'login_title_login' => [
        'desc' => 'Document title for admin login',
        'default' => 'Admin Login'
    ],
    'login_subtitle_create' => [
        'desc' => 'Subtitle on admin signup page',
        'default' => "No administrator account found. Let's set up your access."
    ],
    'login_subtitle_login' => [
        'desc' => 'Subtitle on admin login page',
        'default' => 'Access the dashboard to manage your wishlist items.'
    ],
    'login_card_create' => [
        'desc' => 'Card header for admin signup',
        'default' => '🛡️ Create Admin Account'
    ],
    'login_card_login' => [
        'desc' => 'Card header for admin login',
        'default' => '🔒 Secure Admin Login'
    ],
    'username_label' => [
        'desc' => 'Label for admin username field',
        'default' => 'Username'
    ],
    'password_label' => [
        'desc' => 'Label for admin password field',
        'default' => 'Password'
    ],
    'login_btn_create' => [
        'desc' => 'Button text to submit admin signup',
        'default' => 'Create & Log In'
    ],
    'login_btn_login' => [
        'desc' => 'Button text to submit admin login',
        'default' => 'Sign In'
    ],
    'back_to_public' => [
        'desc' => 'Link to go back to public wishlist',
        'default' => '← Back to public wishlist'
    ],
    'js_verification_required' => [
        'desc' => 'JS alert: Verification proof is empty',
        'default' => 'Verification proof (Tracking Link or Order ID) is required.'
    ],
    'js_verifying' => [
        'desc' => 'JS loader text when verifying purchase',
        'default' => 'Verifying...'
    ],
    'js_network_error' => [
        'desc' => 'JS error: Network failure',
        'default' => 'Unable to contact the server. Please check your network connection.'
    ],
    'admin_panel_title' => [
        'desc' => 'Admin dashboard header title',
        'default' => 'Admin Control Panel'
    ],
    'admin_panel_subtitle' => [
        'desc' => 'Admin dashboard header subtitle',
        'default' => 'Manage items, sort manually, view purchase verifications, and edit configurations.'
    ],
    'admin_logged_in_as' => [
        'desc' => 'Logged in user display (contains %s)',
        'default' => 'Logged in as: <strong>%s</strong>'
    ],
    'admin_view_public' => [
        'desc' => 'Link button to public page',
        'default' => '👁️ View Public List'
    ],
    'admin_sign_out' => [
        'desc' => 'Sign out button link',
        'default' => '🚪 Sign Out'
    ],
    'admin_tab_items' => [
        'desc' => 'Tab button label: Wishlist Items',
        'default' => '📋 Wishlist Items'
    ],
    'admin_tab_settings' => [
        'desc' => 'Tab button label: Settings & Security',
        'default' => '⚙️ Settings & Security'
    ],
    'admin_tab_translations' => [
        'desc' => 'Tab button label: Translation Management',
        'default' => '🌐 Translations'
    ],
    'admin_add_item_title' => [
        'desc' => 'Card header for adding new item',
        'default' => '➕ Add Wishlist Item'
    ],
    'admin_paste_link' => [
        'desc' => 'Form label for scraping input',
        'default' => 'Paste Product Link'
    ],
    'admin_fetch_details' => [
        'desc' => 'Button label for scraper trigger',
        'default' => 'Fetch Details'
    ],
    'admin_analyzing' => [
        'desc' => 'Loader status text during scraping',
        'default' => 'Analyzing page and grabbing image...'
    ],
    'admin_product_title' => [
        'desc' => 'Label for product title input',
        'default' => 'Product Title'
    ],
    'admin_product_link' => [
        'desc' => 'Label for product link URL input',
        'default' => 'Product Link URL'
    ],
    'admin_image_url' => [
        'desc' => 'Label for image URL input',
        'default' => 'Image URL'
    ],
    'admin_image_preview' => [
        'desc' => 'Header for image preview panel',
        'default' => 'Image Preview:'
    ],
    'admin_notes_label' => [
        'desc' => 'Label for product notes text area',
        'default' => 'Notes (Size, color, preferences)'
    ],
    'admin_add_button' => [
        'desc' => 'Button to submit add item form',
        'default' => 'Add to Wishlist'
    ],
    'admin_current_items' => [
        'desc' => 'Card header for current wishlist grid',
        'default' => '📋 Current Wishlist Items'
    ],
    'admin_drag_drop_info' => [
        'desc' => 'Instructional text for drag-and-drop',
        'default' => '💡 Drag and drop cards to reorder. Mobile users can use the arrow buttons (↑/↓) to sort items.'
    ],
    'admin_empty_wishlist' => [
        'desc' => 'Placeholder message when list has no items',
        'default' => 'Your wishlist is currently empty. Use the form on the left to add items!'
    ],
    'admin_purchased_by' => [
        'desc' => 'Verification status: buyer name header',
        'default' => 'Purchased by:'
    ],
    'admin_verification_proof' => [
        'desc' => 'Verification status: proof header',
        'default' => 'Verification Proof:'
    ],
    'admin_open_link' => [
        'desc' => 'Card action: Open link button',
        'default' => '🌐 Open Link'
    ],
    'admin_edit' => [
        'desc' => 'Card action: Edit button',
        'default' => '✏️ Edit'
    ],
    'admin_unmark' => [
        'desc' => 'Card action: Unmark bought status',
        'default' => '↩️ Unmark'
    ],
    'admin_mark_bought_btn' => [
        'desc' => 'Card action: Mark as bought manually',
        'default' => '✅ Mark Bought'
    ],
    'admin_delete_confirm' => [
        'desc' => 'JavaScript confirmation question before delete',
        'default' => 'Remove this item from your wishlist?'
    ],
    'admin_general_settings' => [
        'desc' => 'Card header for general settings form',
        'default' => '⚙️ General Settings'
    ],
    'admin_announcement_notes' => [
        'desc' => 'Form label for announcement notes',
        'default' => 'General Announcement/Notes'
    ],
    'admin_shipping_address_area' => [
        'desc' => 'Form label for shipping address text area',
        'default' => 'Shipping Address Area'
    ],
    'admin_shipping_visible_label' => [
        'desc' => 'Form label for visibility checkbox',
        'default' => 'Make shipping address visible to visitors'
    ],
    'admin_visibility_deadline' => [
        'desc' => 'Form label for visibility expiration date',
        'default' => 'Visibility Deadline (Optional)'
    ],
    'admin_visibility_deadline_desc' => [
        'desc' => 'Help text below visibility deadline',
        'default' => 'The shipping address will automatically hide after this time. Leave empty for permanent visibility.'
    ],
    'admin_save_config' => [
        'desc' => 'Button to save settings configuration',
        'default' => 'Save Config Settings'
    ],
    'admin_change_password' => [
        'desc' => 'Card header for change password form',
        'default' => '🔒 Change Password'
    ],
    'admin_current_password' => [
        'desc' => 'Label for current password field',
        'default' => 'Current Password'
    ],
    'admin_new_password' => [
        'desc' => 'Label for new password field',
        'default' => 'New Password'
    ],
    'admin_confirm_password' => [
        'desc' => 'Label for confirm password field',
        'default' => 'Confirm New Password'
    ],
    'admin_update_password' => [
        'desc' => 'Button to submit password update',
        'default' => 'Update Password'
    ],
    'admin_translation_management' => [
        'desc' => 'Card header for translation dashboard',
        'default' => '🌐 Translation Management'
    ],
    'admin_edit_translations_desc' => [
        'desc' => 'Instructions on the translation panel',
        'default' => '💡 Edit the translations displayed to users. Filter translation keys or values in real-time below.'
    ],
    'admin_search_translations' => [
        'desc' => 'Placeholder inside translation search bar',
        'default' => 'Search translation keys or values...'
    ],
    'admin_select_language' => [
        'desc' => 'Form label/selection placeholder for language dropdown',
        'default' => 'Select Language to Edit:'
    ],
    'admin_add_new_language' => [
        'desc' => 'Form label for adding a language',
        'default' => '➕ Add New Language'
    ],
    'admin_language_code' => [
        'desc' => 'Placeholder for language code input (e.g. de, fr)',
        'default' => 'Language Code (e.g. de, fr, es)'
    ],
    'admin_btn_add_language' => [
        'desc' => 'Button to submit language addition',
        'default' => 'Add'
    ],
    'admin_btn_delete_language' => [
        'desc' => 'Button to delete current language file',
        'default' => '🗑️ Delete Language'
    ],
    'admin_delete_language_confirm' => [
        'desc' => 'JavaScript confirmation dialog when deleting a language (contains %s)',
        'default' => 'Are you sure you want to delete the language \'%s\'? This will delete the lang file and cannot be undone.'
    ],
    'admin_table_key' => [
        'desc' => 'Header label: Translation Key column',
        'default' => 'Translation Key'
    ],
    'admin_table_english' => [
        'desc' => 'Header label: English (Default) column',
        'default' => 'English (Default)'
    ],
    'admin_table_translation' => [
        'desc' => 'Header label: Target Translation column',
        'default' => 'Translation'
    ],
    'admin_save_translations' => [
        'desc' => 'Button to submit translations form',
        'default' => 'Save Translations'
    ],
    'admin_modal_edit_title' => [
        'desc' => 'Title of the edit item modal dialog',
        'default' => '✏️ Edit Wishlist Item'
    ],
    'admin_save_changes' => [
        'desc' => 'Button to submit edit item changes',
        'default' => 'Save Changes'
    ],
    'admin_theme_settings' => [
        'desc' => 'Card header for theme settings form',
        'default' => '🎨 Theme & Appearance'
    ],
    'admin_theme_reset_desc' => [
        'desc' => 'Theme section help message',
        'default' => 'Choose from pre-configured color schemes or adjust individual colors below. Changes are previewed in real-time.'
    ],
    'admin_theme_presets' => [
        'desc' => 'Preset selector label',
        'default' => 'Select a Color Preset:'
    ],
    'admin_theme_primary' => [
        'desc' => 'Label for primary color option',
        'default' => 'Primary Color (Brand/Glow)'
    ],
    'admin_theme_accent' => [
        'desc' => 'Label for accent color option',
        'default' => 'Accent Color (Secondary/Radial)'
    ],
    'admin_theme_background' => [
        'desc' => 'Label for background color option',
        'default' => 'Main Background Color'
    ],
    'admin_theme_card' => [
        'desc' => 'Label for card background option',
        'default' => 'Card Background Color'
    ],
    'admin_theme_text_primary' => [
        'desc' => 'Label for primary text color option',
        'default' => 'Primary Text Color'
    ],
    'admin_theme_text_secondary' => [
        'desc' => 'Label for secondary text color option',
        'default' => 'Secondary Text Color'
    ],
    'admin_save_theme' => [
        'desc' => 'Button to save theme settings',
        'default' => 'Save Theme Settings'
    ],
    'add_message_checkbox' => [
        'desc' => 'Label for the checkbox to add a message/note to the owner',
        'default' => 'Add a message/note'
    ],
    'buyer_message_label' => [
        'desc' => 'Label for the message textarea field',
        'default' => 'Message (Visible only to owner)'
    ],
    'buyer_message_placeholder' => [
        'desc' => 'Placeholder for the message textarea field',
        'default' => 'e.g. Hope you like it! Happy holidays!'
    ],
    'message_public_checkbox' => [
        'desc' => 'Checkbox label to make buyer message public',
        'default' => 'Make message visible on public wishlist'
    ],
    'message_visibility_private' => [
        'desc' => 'Hint text when message is private (admin only)',
        'default' => 'This message will only be visible to the wishlist owner (admin).'
    ],
    'message_visibility_public' => [
        'desc' => 'Hint text when message is public',
        'default' => 'This message will be visible to everyone on the public wishlist.'
    ]
];

function runSecurityChecks($pdo) {
    $warnings = [];
    
    // 1. Check install.php
    $installFile = __DIR__ . '/install.php';
    if (file_exists($installFile)) {
        if (!@unlink($installFile)) {
            $renameTarget = __DIR__ . '/install.php.bak_' . time();
            if (!@rename($installFile, $renameTarget)) {
                $warnings[] = "<strong>⚠️ Security Risk:</strong> The installer file <code>install.php</code> is still present and could not be automatically deleted. Please delete it manually from your server files immediately.";
            } else {
                $warnings[] = "<strong>⚠️ Notice:</strong> The installer file was automatically renamed to <code>" . basename($renameTarget) . "</code> because it could not be deleted. We recommend deleting it completely.";
            }
        }
    }
    
    // 1b. Check schema.sql
    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        if (!@unlink($schemaFile)) {
            $warnings[] = "<strong>⚠️ Security Notice:</strong> The database schema file <code>schema.sql</code> is still present and could not be automatically deleted. Please delete it manually from your server files.";
        }
    }
    
    // 2. Check config.php permissions
    $configFile = __DIR__ . '/config.php';
    if (file_exists($configFile) && DIRECTORY_SEPARATOR === '/') {
        $perms = fileperms($configFile) & 0777;
        if (($perms & 0027) !== 0 || ($perms & 0111) !== 0) {
            if (!@chmod($configFile, 0600)) {
                if (!@chmod($configFile, 0640)) {
                    $warnings[] = "<strong>⚠️ Weak Permissions:</strong> Your <code>config.php</code> file is readable or accessible by other users on this server (permissions: " . sprintf('%03o', $perms) . "). Please change its file permissions to <code>600</code> or <code>640</code> in your cPanel File Manager.";
                }
            }
        }
    }
    
    // 3. Check HTTPS
    $isHttps = false;
    if (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) === 'on' || $_SERVER['HTTPS'] === '1')) {
        $isHttps = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        $isHttps = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        $isHttps = true;
    }
    
    if (!$isHttps) {
        $warnings[] = "<strong>⚠️ Insecure Connection:</strong> You are accessing the admin panel over an unencrypted connection (HTTP). Please enable and enforce HTTPS (SSL) on your domain to prevent credential theft.";
    }
    
    // 4. Check for default username/password
    try {
        $stmt = $pdo->prepare("SELECT `password_hash` FROM `users` WHERE `username` = ?");
        $stmt->execute(['wish_admin']);
        $user = $stmt->fetch();
        if ($user && password_verify('132734', $user['password_hash'])) {
            $warnings[] = "<strong>⚠️ Default Credentials:</strong> You are still using the default credentials (<code>wish_admin</code> / <code>132734</code>). Please use the password change form below to secure your administrator account.";
        }
    } catch (Exception $e) {
        // Ignore DB query errors during warning scanner
    }
    
    return $warnings;
}

$securityWarnings = runSecurityChecks($pdo);

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle Post Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF check could go here, for simplicity we trust admin session
    
    if ($_POST['action'] === 'save_translations') {
        $langCode = strtolower(trim($_POST['lang_code'] ?? ''));
        if (!preg_match('/^[a-z]{2,3}(_[a-z]{2,4})?$/i', $langCode)) {
            $_SESSION['flash_error'] = "Invalid language code format.";
            header("Location: admin.php#tab-translations");
            exit;
        }
        $submitted = $_POST['translations'] ?? [];
        $trData = [];
        foreach ($submitted as $k => $v) {
            if (array_key_exists($k, $translationKeys)) {
                $trData[$k] = trim($v);
            }
        }
        try {
            $langDir = __DIR__ . '/lang';
            if (!is_dir($langDir)) {
                mkdir($langDir, 0755, true);
            }
            $fileContent = "<?php\n// Dynamically generated translation file\nreturn " . var_export($trData, true) . ";\n";
            if (file_put_contents($langDir . '/' . $langCode . '.php', $fileContent) !== false) {
                $_SESSION['flash_success'] = "Translations for '" . strtoupper($langCode) . "' updated successfully.";
            } else {
                $_SESSION['flash_error'] = "Failed to write translation file. Please check folder permissions.";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to save translations: " . $e->getMessage();
        }
        header("Location: admin.php?edit_lang=" . urlencode($langCode) . "#tab-translations");
        exit;
    }
    
    if ($_POST['action'] === 'create_language') {
        $newLangCode = strtolower(trim($_POST['new_lang_code'] ?? ''));
        if (!preg_match('/^[a-z]{2,3}(_[a-z]{2,4})?$/i', $newLangCode)) {
            $_SESSION['flash_error'] = "Invalid language code format (use 2-3 letters, e.g. 'de', 'es').";
        } elseif ($newLangCode === 'en') {
            $_SESSION['flash_error'] = "English is the default language and is defined in the source code.";
        } else {
            try {
                $langDir = __DIR__ . '/lang';
                if (!is_dir($langDir)) {
                    mkdir($langDir, 0755, true);
                }
                $newFile = $langDir . '/' . $newLangCode . '.php';
                if (file_exists($newFile)) {
                    $_SESSION['flash_error'] = "Language '" . strtoupper($newLangCode) . "' already exists.";
                } else {
                    $fileContent = "<?php\n// Dynamically generated translation file\nreturn [];\n";
                    if (file_put_contents($newFile, $fileContent) !== false) {
                        $_SESSION['flash_success'] = "Language '" . strtoupper($newLangCode) . "' created successfully.";
                        header("Location: admin.php?edit_lang=" . urlencode($newLangCode) . "#tab-translations");
                        exit;
                    } else {
                        $_SESSION['flash_error'] = "Failed to create language file. Check permissions.";
                    }
                }
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Error creating language: " . $e->getMessage();
            }
        }
        header("Location: admin.php#tab-translations");
        exit;
    }
    
    if ($_POST['action'] === 'delete_language') {
        $delLang = strtolower(trim($_POST['lang_code'] ?? ''));
        if ($delLang === 'en' || !preg_match('/^[a-z]{2,3}(_[a-z]{2,4})?$/i', $delLang)) {
            $_SESSION['flash_error'] = "Invalid language code to delete.";
        } else {
            $langFile = __DIR__ . '/lang/' . $delLang . '.php';
            if (file_exists($langFile)) {
                if (@unlink($langFile)) {
                    $_SESSION['flash_success'] = "Language '" . strtoupper($delLang) . "' deleted successfully.";
                    header("Location: admin.php?edit_lang=tr#tab-translations");
                    exit;
                } else {
                    $_SESSION['flash_error'] = "Failed to delete language file. Check permissions.";
                }
            } else {
                $_SESSION['flash_error'] = "Language file does not exist.";
            }
        }
        header("Location: admin.php#tab-translations");
        exit;
    }
    
    if ($_POST['action'] === 'save_settings') {
        setSetting('shipping_address', $_POST['shipping_address'] ?? '');
        setSetting('shipping_address_visible', isset($_POST['shipping_address_visible']) ? '1' : '0');
        setSetting('shipping_address_expires_at', $_POST['shipping_address_expires_at'] ?? '');
        setSetting('general_notes', $_POST['general_notes'] ?? '');
        
        $_SESSION['flash_success'] = "Settings saved successfully.";
        header("Location: admin.php");
        exit;
    }
    
    if ($_POST['action'] === 'save_theme') {
        setSetting('theme_primary', $_POST['theme_primary'] ?? '#6366f1');
        setSetting('theme_accent', $_POST['theme_accent'] ?? '#a855f7');
        setSetting('theme_background', $_POST['theme_background'] ?? '#09090b');
        setSetting('theme_card', $_POST['theme_card'] ?? '#141419');
        setSetting('theme_text_primary', $_POST['theme_text_primary'] ?? '#f4f4f5');
        setSetting('theme_text_secondary', $_POST['theme_text_secondary'] ?? '#a1a1aa');
        
        $_SESSION['flash_success'] = "Theme settings saved successfully.";
        header("Location: admin.php");
        exit;
    }
    
    if ($_POST['action'] === 'add_item') {
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($title) || empty($url)) {
            $_SESSION['flash_error'] = "Product title and link URL are required.";
        } else {
            // Find max sort order
            $maxOrder = (int)$pdo->query("SELECT MAX(`sort_order`) FROM `wishlist_items`")->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT INTO `wishlist_items` (`title`, `url`, `image_url`, `notes`, `sort_order`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $title, 
                $url, 
                !empty($image_url) ? $image_url : null, 
                !empty($notes) ? $notes : null, 
                $maxOrder + 1
            ]);
            $_SESSION['flash_success'] = "Item added to your wishlist successfully.";
        }
        header("Location: admin.php");
        exit;
    }
    
    if ($_POST['action'] === 'edit_item') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($id <= 0 || empty($title) || empty($url)) {
            $_SESSION['flash_error'] = "Invalid item configuration. Title and URL are required.";
        } else {
            $stmt = $pdo->prepare("UPDATE `wishlist_items` SET `title` = ?, `url` = ?, `image_url` = ?, `notes` = ? WHERE `id` = ?");
            $stmt->execute([
                $title, 
                $url, 
                !empty($image_url) ? $image_url : null, 
                !empty($notes) ? $notes : null, 
                $id
            ]);
            $_SESSION['flash_success'] = "Wishlist item updated successfully.";
        }
        header("Location: admin.php");
        exit;
    }

    if ($_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['flash_error'] = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['flash_error'] = "New passwords do not match.";
        } elseif (strlen($newPassword) < 6) {
            $_SESSION['flash_error'] = "New password must be at least 6 characters long.";
        } else {
            $username = $_SESSION['admin_user'];
            try {
                $stmt = $pdo->prepare("SELECT `password_hash` FROM `users` WHERE `username` = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($currentPassword, $user['password_hash'])) {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $updateStmt = $pdo->prepare("UPDATE `users` SET `password_hash` = ? WHERE `username` = ?");
                    $updateStmt->execute([$newHash, $username]);
                    $_SESSION['flash_success'] = "Password changed successfully.";
                } else {
                    $_SESSION['flash_error'] = "Incorrect current password.";
                }
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
            }
        }
        header("Location: admin.php");
        exit;
    }
}

// Handle GET Actions (Delete, Toggle Bought)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM `wishlist_items` WHERE `id` = ?");
    $stmt->execute([$id]);
    $_SESSION['flash_success'] = "Wishlist item removed.";
    header("Location: admin.php");
    exit;
}

if (isset($_GET['toggle_bought'])) {
    $id = (int)$_GET['toggle_bought'];
    $stmt = $pdo->prepare("SELECT `is_bought` FROM `wishlist_items` WHERE `id` = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if ($item) {
        $newBought = (int)$item['is_bought'] === 1 ? 0 : 1;
        if ($newBought === 0) {
            // Reset buyer details
            $updateStmt = $pdo->prepare("UPDATE `wishlist_items` SET `is_bought` = 0, `buyer_name` = NULL, `buyer_proof` = NULL, `bought_at` = NULL WHERE `id` = ?");
            $updateStmt->execute([$id]);
        } else {
            // Set bought by admin
            $updateStmt = $pdo->prepare("UPDATE `wishlist_items` SET `is_bought` = 1, `buyer_name` = 'Admin', `buyer_proof` = 'Manually marked by admin', `bought_at` = NOW() WHERE `id` = ?");
            $updateStmt->execute([$id]);
        }
        $_SESSION['flash_success'] = "Item purchase status updated.";
    }
    header("Location: admin.php");
    exit;
}

// Fetch settings
$shippingAddress = getSetting('shipping_address', '');
$shippingAddressVisible = getSetting('shipping_address_visible', '1') === '1';
$shippingAddressExpiresAt = getSetting('shipping_address_expires_at', '');
$generalNotes = getSetting('general_notes', '');

// Fetch all wishlist items
$items = $pdo->query("SELECT * FROM `wishlist_items` ORDER BY `sort_order` ASC")->fetchAll();
// Determine edit language
$editLang = strtolower($_GET['edit_lang'] ?? 'tr');
if (!preg_match('/^[a-z]{2,3}(_[a-z]{2,4})?$/i', $editLang)) {
    $editLang = 'tr';
}

$editTranslations = [];
$editTrFile = __DIR__ . '/lang/' . $editLang . '.php';
if (file_exists($editTrFile)) {
    $editTranslations = include $editTrFile;
} else if ($editLang === 'tr') {
    // Backward compatibility: fetch from DB and save it to file
    try {
        $stmt = $pdo->prepare("SELECT `translation_key`, `translation_value` FROM `translations` WHERE `lang` = 'tr'");
        $stmt->execute();
        $editTranslations = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($editTranslations) {
            $langDir = __DIR__ . '/lang';
            if (!is_dir($langDir)) {
                @mkdir($langDir, 0755, true);
            }
            @file_put_contents($editTrFile, "<?php\nreturn " . var_export($editTranslations, true) . ";\n");
        }
    } catch (PDOException $e) {
        // translations table might not exist yet
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php echo getCustomStyles(); ?>
    <style>
        .admin-nav {
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }
        .order-btn-group {
            display: flex;
            gap: 2px;
            position: absolute;
            top: 10px;
            left: 45px;
            z-index: 10;
        }
        .btn-order-nav {
            padding: 2px 6px;
            font-size: 0.75rem;
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-order-nav:hover {
            background: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Dashboard Header -->
        <header>
            <div class="logo-container">
                <span class="logo-icon">🛠️</span>
                <h1><?= h(__('admin_panel_title', 'Admin Control Panel')) ?></h1>
            </div>
            <p class="subtitle"><?= h(__('admin_panel_subtitle', 'Manage items, sort manually, view purchase verifications, and edit configurations.')) ?></p>
        </header>

        <!-- Navigation -->
        <div class="nav-bar admin-nav">
            <span class="text-sm text-muted"><?= sprintf(__('admin_logged_in_as', 'Logged in as: <strong>%s</strong>'), h($_SESSION['admin_user'])) ?></span>
            <div class="flex gap-2" style="align-items: center;">
                <form method="GET" action="" style="display: inline-block; margin: 0;">
                    <div class="lang-select-wrapper">
                        <select name="lang" class="lang-dropdown" onchange="this.form.submit()">
                            <?php foreach (getAvailableLanguages() as $langCode): ?>
                                <option value="<?= h($langCode) ?>" <?= $currentLang === $langCode ? 'selected' : '' ?>>
                                    <?= h(strtoupper($langCode)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <a href="index.php" class="btn btn-secondary btn-sm"><?= h(__('admin_view_public', '👁️ View Public List')) ?></a>
                <a href="logout.php" class="btn btn-danger btn-sm"><?= h(__('admin_sign_out', '🚪 Sign Out')) ?></a>
            </div>
        </div>

        <!-- Security Warnings -->
        <?php if (!empty($securityWarnings)): ?>
            <?php foreach ($securityWarnings as $warning): ?>
                <div class="flash-message flash-danger" style="margin-bottom: 1rem; border-left: 4px solid #ef4444; text-align: left;">
                    <span><?= $warning ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Flash messages -->
        <?php if ($flashSuccess): ?>
            <div class="flash-message flash-success">
                <span><?= h($flashSuccess) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="flash-message flash-danger">
                <span><?= h($flashError) ?></span>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" data-tab="items"><?= h(__('admin_tab_items', '📋 Wishlist Items')) ?></button>
            <button class="tab-btn" data-tab="settings"><?= h(__('admin_tab_settings', '⚙️ Settings & Security')) ?></button>
            <button class="tab-btn" data-tab="translations"><?= h(__('admin_tab_translations', '🌐 Translations')) ?></button>
        </div>

        <!-- Tab Content: Items -->
        <div class="tab-content active" id="tab-items">
            <div class="admin-grid">
                <!-- LEFT COLUMN: Forms -->
                <div class="flex flex-col gap-3">
                    <!-- Add New Item Section -->
                    <div class="add-item-box">
                        <h3 class="mb-4"><?= h(__('admin_add_item_title', '➕ Add Wishlist Item')) ?></h3>
                        
                        <div class="form-group">
                            <label for="scrape-url"><?= h(__('admin_paste_link', 'Paste Product Link')) ?></label>
                            <div class="url-input-container">
                                <input type="url" id="scrape-url" placeholder="https://example.com/product-page" autocomplete="off">
                                <button type="button" id="btn-scrape" class="btn btn-primary"><?= h(__('admin_fetch_details', 'Fetch Details')) ?></button>
                            </div>
                            <div id="scrape-loading" class="scraping-indicator">
                                <div class="spinner"></div>
                                <span><?= h(__('admin_analyzing', 'Analyzing page and grabbing image...')) ?></span>
                            </div>
                        </div>

                        <!-- Add form (reveals details once scraped or manually entered) -->
                        <form action="admin.php" method="POST" id="form-add-item">
                            <input type="hidden" name="action" value="add_item">
                            
                            <div class="form-group">
                                <label for="add-title"><?= h(__('admin_product_title', 'Product Title')) ?></label>
                                <input type="text" id="add-title" name="title" placeholder="Enter title" required>
                            </div>

                            <div class="form-group">
                                <label for="add-url"><?= h(__('admin_product_link', 'Product Link URL')) ?></label>
                                <input type="url" id="add-url" name="url" placeholder="https://..." required>
                            </div>

                            <div class="form-group">
                                <label for="add-image"><?= h(__('admin_image_url', 'Image URL')) ?></label>
                                <input type="url" id="add-image" name="image_url" placeholder="https://... (or leave empty)">
                                <div class="preview-pane mt-2" id="add-image-preview-container">
                                    <span class="text-xs text-muted block mb-1"><?= h(__('admin_image_preview', 'Image Preview:')) ?></span>
                                    <img src="" alt="" class="preview-image" id="add-image-preview">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="add-notes"><?= h(__('admin_notes_label', 'Notes (Size, color, preferences)')) ?></label>
                                <textarea id="add-notes" name="notes" rows="2" placeholder="e.g. Size M, color black"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block"><?= h(__('admin_add_button', 'Add to Wishlist')) ?></button>
                        </form>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Interactive Sortable Wishlist Grid -->
                <div class="wishlist-container">
                    <h3 class="mb-4"><?= h(__('admin_current_items', '📋 Current Wishlist Items')) ?></h3>
                    <p class="text-xs text-muted mb-4">💡 <?= h(__('admin_drag_drop_info', 'Drag and drop cards to reorder. Mobile users can use the arrow buttons (↑/↓) to sort items.')) ?></p>
                    
                    <div class="wishlist-grid" id="sortable-list">
                        <?php if (empty($items)): ?>
                            <div class="info-card text-center" style="grid-column: 1/-1;">
                                <p class="text-muted"><?= h(__('admin_empty_wishlist', 'Your wishlist is currently empty. Use the form on the left to add items!')) ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <div class="item-card sortable-item <?= $item['is_bought'] ? 'bought' : '' ?>" data-id="<?= $item['id'] ?>" draggable="true">
                                    <span class="drag-handle">☰</span>
                                    <div class="order-btn-group">
                                        <button class="btn-order-nav btn-move-up" title="Move Up">▲</button>
                                        <button class="btn-order-nav btn-move-down" title="Move Down">▼</button>
                                    </div>

                                    <?php if ($item['is_bought']): ?>
                                        <span class="bought-badge"><?= h(__('bought_badge', 'BOUGHT')) ?></span>
                                    <?php endif; ?>

                                    <div class="item-image-wrapper">
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?= h($item['image_url']) ?>" alt="<?= h($item['title']) ?>" class="item-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="item-placeholder" style="display:none;">
                                                <span>🎁</span>
                                                <span><?= h(__('no_image_loaded', 'No Image Loaded')) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="item-placeholder">
                                                <span>🎁</span>
                                                <span><?= h(__('no_image_provided', 'No Image')) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="item-content">
                                        <h4 class="item-title" title="<?= h($item['title']) ?>"><?= h($item['title']) ?></h4>
                                        
                                        <?php if ($item['notes']): ?>
                                            <p class="item-notes" title="<?= h($item['notes']) ?>"><?= h($item['notes']) ?></p>
                                        <?php else: ?>
                                            <p class="item-notes text-muted"><?= h(__('admin_no_notes', 'No specific notes.')) ?></p>
                                        <?php endif; ?>

                                        <!-- Admin Proof Area -->
                                        <?php if ($item['is_bought']): ?>
                                            <div style="background: rgba(16, 185, 129, 0.08); padding: 8px; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 12px; word-break: break-all;">
                                                <span class="text-xs text-muted block"><?= h(__('admin_purchased_by', 'Purchased by:')) ?></span>
                                                <strong class="text-sm block" style="color: var(--success);"><?= h($item['buyer_name']) ?></strong>
                                                <span class="text-xs text-muted block mt-1"><?= h(__('admin_verification_proof', 'Verification Proof:')) ?></span>
                                                <code class="text-xs" style="color: var(--text-primary); white-space: pre-wrap; display: block;"><?= h($item['buyer_proof']) ?></code>
                                            </div>
                                        <?php endif; ?>

                                        <div class="item-actions">
                                            <a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm"><?= h(__('admin_open_link', '🌐 Open Link')) ?></a>
                                            <button class="btn btn-secondary btn-sm btn-edit" 
                                                    data-id="<?= $item['id'] ?>"
                                                    data-title="<?= h($item['title']) ?>"
                                                    data-url="<?= h($item['url']) ?>"
                                                    data-image="<?= h($item['image_url']) ?>"
                                                    data-notes="<?= h($item['notes']) ?>">
                                                <?= h(__('admin_edit', '✏️ Edit')) ?>
                                            </button>
                                            <div class="flex gap-2">
                                                <a href="admin.php?toggle_bought=<?= $item['id'] ?>" class="btn btn-secondary btn-sm" style="flex-grow: 1;">
                                                    <?= $item['is_bought'] ? h(__('admin_unmark', '↩️ Unmark')) : h(__('admin_mark_bought_btn', '✅ Mark Bought')) ?>
                                                </a>
                                                <a href="admin.php?delete=<?= $item['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm(<?= h(json_encode(__('admin_delete_confirm', 'Remove this item from your wishlist?'))) ?>);" title="Delete">🗑️</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Settings & Security -->
        <div class="tab-content" id="tab-settings">
            <div class="admin-grid">
                <!-- Settings Box -->
                <div class="add-item-box">
                    <h3 class="mb-4"><?= h(__('admin_general_settings', '⚙️ General Settings')) ?></h3>
                    <form action="admin.php" method="POST">
                        <input type="hidden" name="action" value="save_settings">

                        <div class="form-group">
                            <label for="general-notes"><?= h(__('admin_announcement_notes', 'General Announcement/Notes')) ?></label>
                            <textarea id="general-notes" name="general_notes" rows="3" placeholder="Notes for everyone visiting..."><?= h($generalNotes) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="shipping-address"><?= h(__('admin_shipping_address_area', 'Shipping Address Area')) ?></label>
                            <textarea id="shipping-address" name="shipping_address" rows="3" placeholder="Enter your full shipping address details..."><?= h($shippingAddress) ?></textarea>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="shipping-visible" name="shipping_address_visible" value="1" <?= $shippingAddressVisible ? 'checked' : '' ?>>
                            <label for="shipping-visible"><?= h(__('admin_shipping_visible_label', 'Make shipping address visible to visitors')) ?></label>
                        </div>

                        <div class="form-group">
                            <label for="shipping-deadline"><?= h(__('admin_visibility_deadline', 'Visibility Deadline (Optional)')) ?></label>
                            <input type="datetime-local" id="shipping-deadline" name="shipping_address_expires_at" value="<?= h($shippingAddressExpiresAt) ?>">
                            <span class="text-xs text-muted"><?= h(__('admin_visibility_deadline_desc', 'The shipping address will automatically hide after this time. Leave empty for permanent visibility.')) ?></span>
                        </div>

                        <button type="submit" class="btn btn-secondary btn-block"><?= h(__('admin_save_config', 'Save Config Settings')) ?></button>
                    </form>
                </div>

                <!-- Theme Settings Box -->
                <div class="add-item-box">
                    <h3 class="mb-4"><?= h(__('admin_theme_settings', '🎨 Theme & Appearance')) ?></h3>
                    <p class="text-xs text-muted mb-4"><?= h(__('admin_theme_reset_desc', 'Choose from pre-configured color schemes or adjust individual colors below. Changes are previewed in real-time.')) ?></p>
                    
                    <div class="form-group mb-4">
                        <label><?= h(__('admin_theme_presets', 'Select a Color Preset:')) ?></label>
                        <div class="flex gap-2" style="flex-wrap: wrap; margin-top: 0.5rem;">
                            <button type="button" class="btn btn-secondary btn-sm preset-btn" data-preset="default">Cyberpunk</button>
                            <button type="button" class="btn btn-secondary btn-sm preset-btn" data-preset="emerald">Emerald</button>
                            <button type="button" class="btn btn-secondary btn-sm preset-btn" data-preset="sunset">Sunset</button>
                            <button type="button" class="btn btn-secondary btn-sm preset-btn" data-preset="ocean">Ocean</button>
                            <button type="button" class="btn btn-secondary btn-sm preset-btn" data-preset="sakura">Sakura</button>
                            <button type="button" class="btn btn-secondary btn-sm preset-btn" data-preset="dracula">Dracula</button>
                        </div>
                    </div>

                    <form action="admin.php" method="POST">
                        <input type="hidden" name="action" value="save_theme">

                        <div class="form-group">
                            <label for="theme-primary"><?= h(__('admin_theme_primary', 'Primary Color')) ?></label>
                            <input type="color" id="theme-primary" name="theme_primary" value="<?= h(getSetting('theme_primary', '#6366f1')) ?>" class="theme-color-picker" data-var="--primary" style="height: 45px; padding: 4px; cursor: pointer;">
                        </div>

                        <div class="form-group">
                            <label for="theme-accent"><?= h(__('admin_theme_accent', 'Accent Color')) ?></label>
                            <input type="color" id="theme-accent" name="theme_accent" value="<?= h(getSetting('theme_accent', '#a855f7')) ?>" class="theme-color-picker" data-var="--accent" style="height: 45px; padding: 4px; cursor: pointer;">
                        </div>

                        <div class="form-group">
                            <label for="theme-background"><?= h(__('admin_theme_background', 'Background Color')) ?></label>
                            <input type="color" id="theme-background" name="theme_background" value="<?= h(getSetting('theme_background', '#09090b')) ?>" class="theme-color-picker" data-var="--bg-main" style="height: 45px; padding: 4px; cursor: pointer;">
                        </div>

                        <div class="form-group">
                            <label for="theme-card"><?= h(__('admin_theme_card', 'Card Background Color')) ?></label>
                            <input type="color" id="theme-card" name="theme_card" value="<?= h(getSetting('theme_card', '#141419')) ?>" class="theme-color-picker" data-var="--bg-card" style="height: 45px; padding: 4px; cursor: pointer;">
                        </div>

                        <div class="form-group">
                            <label for="theme-text-primary"><?= h(__('admin_theme_text_primary', 'Primary Text Color')) ?></label>
                            <input type="color" id="theme-text-primary" name="theme_text_primary" value="<?= h(getSetting('theme_text_primary', '#f4f4f5')) ?>" class="theme-color-picker" data-var="--text-primary" style="height: 45px; padding: 4px; cursor: pointer;">
                        </div>

                        <div class="form-group">
                            <label for="theme-text-secondary"><?= h(__('admin_theme_text_secondary', 'Secondary Text Color')) ?></label>
                            <input type="color" id="theme-text-secondary" name="theme_text_secondary" value="<?= h(getSetting('theme_text_secondary', '#a1a1aa')) ?>" class="theme-color-picker" data-var="--text-secondary" style="height: 45px; padding: 4px; cursor: pointer;">
                        </div>

                        <button type="submit" class="btn btn-secondary btn-block mt-4"><?= h(__('admin_save_theme', 'Save Theme Settings')) ?></button>
                    </form>
                </div>

                <!-- Change Password Box -->
                <div class="add-item-box">
                    <h3 class="mb-4"><?= h(__('admin_change_password', '🔒 Change Password')) ?></h3>
                    <form action="admin.php" method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current-password"><?= h(__('admin_current_password', 'Current Password')) ?></label>
                            <input type="password" id="current-password" name="current_password" placeholder="••••••••" required>
                        </div>

                        <div class="form-group">
                            <label for="new-password"><?= h(__('admin_new_password', 'New Password')) ?></label>
                            <input type="password" id="new-password" name="new_password" placeholder="Minimum 6 characters" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm-password"><?= h(__('admin_confirm_password', 'Confirm New Password')) ?></label>
                            <input type="password" id="confirm-password" name="confirm_password" placeholder="Repeat new password" required>
                        </div>

                        <button type="submit" class="btn btn-secondary btn-block"><?= h(__('admin_update_password', 'Update Password')) ?></button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab Content: Translations -->
        <div class="tab-content" id="tab-translations">
            <div class="add-item-box">
                <h3 class="mb-4">🌐 <?= h(__('admin_translation_management', 'Translation Management')) ?></h3>
                <p class="text-xs text-muted mb-4">💡 <?= h(__('admin_edit_translations_desc', 'Edit the translations displayed to users. Filter translation keys or values in real-time below.')) ?></p>

                <!-- Language Selector & Add/Delete Language Bar -->
                <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
                    
                    <!-- Select Language to Edit -->
                    <div style="flex: 1; min-width: 250px;">
                        <label class="text-xs text-muted block mb-2" style="font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?= h(__('admin_select_language', 'Select Language to Edit:')) ?></label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <div class="lang-select-wrapper">
                                <select id="select-edit-lang" class="lang-dropdown" onchange="window.location.href='admin.php?edit_lang=' + this.value + '#tab-translations'">
                                    <?php foreach (getAvailableLanguages() as $langCode): ?>
                                        <option value="<?= h($langCode) ?>" <?= $editLang === $langCode ? 'selected' : '' ?>><?= h(strtoupper($langCode)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($editLang !== 'tr' && $editLang !== 'en'): ?>
                                <form action="admin.php" method="POST" onsubmit="return confirm(<?= h(json_encode(sprintf(__('admin_delete_language_confirm', "Are you sure you want to delete the language '%s'? This will delete the lang file and cannot be undone."), strtoupper($editLang)))) ?>);" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_language">
                                    <input type="hidden" name="lang_code" value="<?= h($editLang) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><?= h(__('admin_btn_delete_language', '🗑️ Delete Language')) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Add New Language Form -->
                    <form action="admin.php" method="POST" style="margin: 0; flex: 1; min-width: 280px; display: flex; flex-direction: column;">
                        <input type="hidden" name="action" value="create_language">
                        <label for="new-lang-code" class="text-xs text-muted block mb-2" style="font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?= h(__('admin_add_new_language', '➕ Add New Language')) ?></label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="new-lang-code" name="new_lang_code" placeholder="<?= h(__('admin_language_code', 'Language Code (e.g. de, fr, es)')) ?>" pattern="^[a-zA-Z]{2,3}(_[a-zA-Z]{2,4})?$" required style="flex-grow: 1; padding: 6px 12px; font-size: 0.85rem;">
                            <button type="submit" class="btn btn-primary" style="padding: 6px 16px; font-size: 0.85rem; font-weight: 600;"><?= h(__('admin_btn_add_language', 'Add')) ?></button>
                        </div>
                    </form>
                </div>
                
                <!-- Empty Translations Warning Banner -->
                <?php
                $emptyCount = 0;
                foreach ($translationKeys as $key => $meta) {
                    if (trim($editTranslations[$key] ?? '') === '') {
                        $emptyCount++;
                    }
                }
                ?>
                <div id="empty-translations-warning" class="flash-message flash-warning" style="display: <?= $emptyCount > 0 ? 'flex' : 'none' ?>; margin-bottom: 1.5rem;">
                    <div>
                        <strong>⚠️ Notice:</strong> <span id="empty-translations-count"><?= $emptyCount ?></span> translation box(es) are empty. They will default to their English versions on the public page.
                    </div>
                </div>

                <!-- Search bar for filtering -->
                <div class="translation-search-container mb-4" style="position: relative;">
                    <span class="translation-search-icon">🔍</span>
                    <input type="text" id="translation-search" placeholder="<?= h(__('admin_search_translations', 'Search translation keys or values...')) ?>" autocomplete="off">
                </div>

                <form action="admin.php" method="POST">
                    <input type="hidden" name="action" value="save_translations">
                    <input type="hidden" name="lang_code" value="<?= h($editLang) ?>">
                    
                    <div class="table-container">
                        <table class="translation-table">
                            <thead>
                                <tr>
                                    <th><?= h(__('admin_table_key', 'Translation Key')) ?></th>
                                    <th><?= h(__('admin_table_english', 'English (Default)')) ?></th>
                                    <th><?= h(__('admin_table_translation', 'Translation')) ?> (<?= h(strtoupper($editLang)) ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($translationKeys as $key => $meta): 
                                    $currentVal = $editTranslations[$key] ?? '';
                                ?>
                                    <tr class="translation-row" data-key="<?= h($key) ?>">
                                        <td>
                                            <span class="translation-row-key"><?= h($key) ?></span>
                                            <span class="translation-row-desc"><?= h($meta['desc']) ?></span>
                                        </td>
                                        <td style="white-space: pre-wrap;"><?= h($meta['default']) ?></td>
                                        <td>
                                            <div style="display: flex; flex-direction: column;">
                                                <textarea name="translations[<?= h($key) ?>]" class="translation-textarea <?= trim($currentVal) === '' ? 'empty-warning' : '' ?>" placeholder="Enter <?= h(strtoupper($editLang)) ?> translation..."><?= h($currentVal) ?></textarea>
                                                <span class="empty-fallback-badge" style="display: <?= trim($currentVal) === '' ? 'inline-block' : 'none' ?>;">
                                                    ⚠️ Empty: Falls back to "<?= h($meta['default']) ?>"
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block mt-4"><?= h(__('admin_save_translations', 'Save Translations')) ?></button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal-backdrop" id="edit-modal">
        <div class="modal-window">
            <div class="modal-header">
                <h3 class="modal-title"><?= h(__('admin_modal_edit_title', '✏️ Edit Wishlist Item')) ?></h3>
                <button class="modal-close" id="modal-close">&times;</button>
            </div>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="id" id="edit-id">

                <div class="form-group">
                    <label for="edit-title"><?= h(__('admin_product_title', 'Product Title')) ?></label>
                    <input type="text" id="edit-title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="edit-url"><?= h(__('admin_product_link', 'Product Link URL')) ?></label>
                    <input type="url" id="edit-url" name="url" required>
                </div>

                <div class="form-group">
                    <label for="edit-image"><?= h(__('admin_image_url', 'Image URL')) ?></label>
                    <input type="url" id="edit-image" name="image_url">
                    <div class="preview-pane mt-2" id="edit-image-preview-container" style="display: block;">
                        <span class="text-xs text-muted block mb-1"><?= h(__('admin_image_preview', 'Image Preview:')) ?></span>
                        <img src="" alt="" class="preview-image" id="edit-image-preview">
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit-notes"><?= h(__('admin_notes_label', 'Notes (Size, color, preferences)')) ?></label>
                    <textarea id="edit-notes" name="notes" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block"><?= h(__('admin_save_changes', 'Save Changes')) ?></button>
            </form>
        </div>
    </div>

    <script src="assets/js/admin.js?v=<?= filemtime(__DIR__ . '/assets/js/admin.js') ?>"></script>
</body>
</html>
