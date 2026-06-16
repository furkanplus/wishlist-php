<?php
// config.php
// Dynamically generated for local testing

if (function_exists('session_status')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} elseif (function_exists('session_start') && function_exists('session_id')) {
    if (session_id() === '') {
        session_start();
    }
}

if (!isset($_SESSION)) {
    $_SESSION = [];
}

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'wishlist_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die('Database Connection Error: ' . $e->getMessage() . '<br><br>Please check credentials in config.php.');
}

// Helpers
function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}

function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT `value` FROM `settings` WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?');
    return $stmt->execute([$key, $value, $value]);
}

function isShippingAddressVisible() {
    $visible = getSetting('shipping_address_visible', '1') === '1';
    if (!$visible) return false;
    
    $expires_at = getSetting('shipping_address_expires_at', '');
    if (!empty($expires_at)) {
        $expiry_time = strtotime($expires_at);
        if ($expiry_time !== false && time() > $expiry_time) {
            return false;
        }
    }
    return true;
}

function hexToRgba($hex, $opacity = 1) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else if (strlen($hex) == 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    } else {
        return '';
    }
    return "rgba($r, $g, $b, $opacity)";
}

function getCustomStyles() {
    $themePrimary = getSetting('theme_primary', '#6366f1');
    $themeAccent = getSetting('theme_accent', '#a855f7');
    $themeBackground = getSetting('theme_background', '#09090b');
    $themeCard = getSetting('theme_card', '#141419');
    $themeTextPrimary = getSetting('theme_text_primary', '#f4f4f5');
    $themeTextSecondary = getSetting('theme_text_secondary', '#a1a1aa');

    $hexToRgbOnly = function($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else if (strlen($hex) == 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            return '20, 20, 25';
        }
        return "$r, $g, $b";
    };

    $rgbPrimary = $hexToRgbOnly($themePrimary);
    $rgbAccent = $hexToRgbOnly($themeAccent);
    $rgbCard = $hexToRgbOnly($themeCard);

    return "
    <style>
    :root {
        --primary: {$themePrimary};
        --primary-glow: rgba({$rgbPrimary}, 0.15);
        --border-color-focus: rgba({$rgbPrimary}, 0.5);
        --accent: {$themeAccent};
        --bg-main: {$themeBackground};
        --bg-card: rgba({$rgbCard}, 0.6);
        --bg-card-hover: rgba({$rgbCard}, 0.8);
        --text-primary: {$themeTextPrimary};
        --text-secondary: {$themeTextSecondary};
    }
    body {
        background-image: 
            radial-gradient(at 0% 0%, rgba({$rgbPrimary}, 0.08) 0px, transparent 50%),
            radial-gradient(at 100% 100%, rgba({$rgbAccent}, 0.08) 0px, transparent 50%);
    }
    </style>
    ";
}

