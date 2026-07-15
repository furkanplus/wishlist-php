<?php
// config.template.php
// Template for install.php — placeholders replaced with user input

// Secure session cookie settings (must be before session_start())
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
} else {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
}

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

// CSRF Protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

// Security Headers
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline'; " .
           "style-src 'self' 'unsafe-inline'; " .
           "img-src 'self' data: https:; " .
           "font-src 'self' data:; " .
           "connect-src 'self'; " .
           "frame-ancestors 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self';";
    header("Content-Security-Policy: $csp");
}

define('DB_HOST', '{{DB_HOST}}');
define('DB_PORT', '{{DB_PORT}}');
define('DB_NAME', '{{DB_NAME}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASS', '{{DB_PASS}}');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die('Database Connection Error. Please check credentials in config.php.');
}

// Auto-add missing columns to wishlist_items (non-destructive)
function ensureWishlistColumns($pdo) {
    $columns = [
        'buyer_message' => 'TEXT DEFAULT NULL',
        'message_public' => 'TINYINT(1) DEFAULT 0',
        'is_archived' => 'TINYINT(1) DEFAULT 0',
    ];
    foreach ($columns as $col => $def) {
        try {
            $pdo->query("SELECT `$col` FROM `wishlist_items` LIMIT 1");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $pdo->exec("ALTER TABLE `wishlist_items` ADD COLUMN `$col` $def");
            }
        }
    }
}

// Auto-create rate_limits table if missing
function ensureRateLimitsTable($pdo) {
    try {
        $pdo->query("SELECT 1 FROM `rate_limits` LIMIT 1");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'doesn\'t exist') !== false || strpos($e->getMessage(), 'Table') !== false) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `rate_limits` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(100) NOT NULL,
                `created_at` INT NOT NULL,
                INDEX `idx_key_created` (`key`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }
}
ensureWishlistColumns($pdo);
ensureRateLimitsTable($pdo);

// Rate limiting
function checkRateLimit($key, $maxAttempts = 5, $windowSeconds = 300) {
    global $pdo;
    $now = time();
    $windowStart = $now - $windowSeconds;
    $stmt = $pdo->prepare("DELETE FROM `rate_limits` WHERE `created_at` < ?");
    $stmt->execute([$windowStart]);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `rate_limits` WHERE `key` = ? AND `created_at` >= ?");
    $stmt->execute([$key, $windowStart]);
    $count = (int)$stmt->fetchColumn();
    if ($count >= $maxAttempts) {
        return false;
    }
    $stmt = $pdo->prepare("INSERT INTO `rate_limits` (`key`, `created_at`) VALUES (?, ?)");
    $stmt->execute([$key, $now]);
    return true;
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

function getCurrencySymbol($currency = null) {
    $currency = $currency ?? getSetting('currency', 'USD');
    $symbols = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'TRY' => '₺',
        'CAD' => 'C$', 'AUD' => 'A$', 'JPY' => '¥', 'CNY' => '¥',
        'INR' => '₹', 'KRW' => '₩', 'BRL' => 'R$', 'MXN' => 'MX$',
        'CHF' => 'CHF', 'SEK' => 'kr', 'NOK' => 'kr', 'DKK' => 'kr',
        'PLN' => 'zł', 'CZK' => 'Kč', 'HUF' => 'Ft', 'RUB' => '₽',
        'ZAR' => 'R', 'SGD' => 'S$', 'HKD' => 'HK$', 'NZD' => 'NZ$',
        'TWD' => 'NT$', 'THB' => '฿', 'MYR' => 'RM', 'PHP' => '₱',
        'IDR' => 'Rp', 'VND' => '₫', 'ILS' => '₪', 'AED' => 'د.إ',
        'SAR' => '﷼',
    ];
    return $symbols[$currency] ?? $currency;
}

function getCurrencyList() {
    return [
        'USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)', 'GBP' => 'British Pound (£)',
        'TRY' => 'Turkish Lira (₺)', 'CAD' => 'Canadian Dollar (C$)', 'AUD' => 'Australian Dollar (A$)',
        'JPY' => 'Japanese Yen (¥)', 'CNY' => 'Chinese Yuan (¥)', 'INR' => 'Indian Rupee (₹)',
        'KRW' => 'South Korean Won (₩)', 'BRL' => 'Brazilian Real (R$)', 'MXN' => 'Mexican Peso (MX$)',
        'CHF' => 'Swiss Franc (CHF)', 'SEK' => 'Swedish Krona (kr)', 'NOK' => 'Norwegian Krone (kr)',
        'DKK' => 'Danish Krone (kr)', 'PLN' => 'Polish Złoty (zł)', 'CZK' => 'Czech Koruna (Kč)',
        'HUF' => 'Hungarian Forint (Ft)', 'RUB' => 'Russian Ruble (₽)', 'ZAR' => 'South African Rand (R)',
        'SGD' => 'Singapore Dollar (S$)', 'HKD' => 'Hong Kong Dollar (HK$)', 'NZD' => 'New Zealand Dollar (NZ$)',
        'TWD' => 'New Taiwan Dollar (NT$)', 'THB' => 'Thai Baht (฿)', 'MYR' => 'Malaysian Ringgit (RM)',
        'PHP' => 'Philippine Peso (₱)', 'IDR' => 'Indonesian Rupiah (Rp)', 'VND' => 'Vietnamese Dong (₫)',
        'ILS' => 'Israeli Shekel (₪)', 'AED' => 'UAE Dirham (د.إ)', 'SAR' => 'Saudi Riyal (﷼)',
    ];
}

function hexToRgba($hex, $opacity = 1) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } elseif (strlen($hex) == 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    } else {
        return '';
    }
    return "rgba($r, $g, $b, $opacity)";
}

function validateCssColor($color) {
    $color = trim($color);
    if ($color === '') return false;
    if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color)) {
        return $color;
    }
    if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*[\d.]+)?\s*\)$/', $color, $m)) {
        $r = min(255, max(0, (int)$m[1]));
        $g = min(255, max(0, (int)$m[2]));
        $b = min(255, max(0, (int)$m[3]));
        return stripos($color, 'rgba') === 0 ? "rgba($r, $g, $b, 1)" : "rgb($r, $g, $b)";
    }
    if (preg_match('/^hsla?\(\s*(\d{1,3})\s*,\s*(\d{1,3})%\s*,\s*(\d{1,3})%\s*(?:,\s*[\d.]+)?\s*\)$/', $color, $m)) {
        $h = min(360, max(0, (int)$m[1]));
        $s = min(100, max(0, (int)$m[2]));
        $l = min(100, max(0, (int)$m[3]));
        return stripos($color, 'hsla') === 0 ? "hsla($h, $s%, $l%, 1)" : "hsl($h, $s%, $l%)";
    }
    $namedColors = [
        'transparent', 'currentcolor', 'inherit', 'initial', 'unset',
        'black', 'white', 'red', 'green', 'blue', 'yellow', 'cyan', 'magenta',
        'silver', 'gray', 'grey', 'maroon', 'olive', 'purple', 'teal', 'navy',
        'orange', 'pink', 'brown', 'violet', 'indigo', 'lime', 'aqua', 'fuchsia'
    ];
    return in_array(strtolower($color), $namedColors) ? $color : false;
}

function getCustomStyles() {
    $themePrimary = getSetting('theme_primary', '#6366f1');
    $themeAccent = getSetting('theme_accent', '#a855f7');
    $themeBackground = getSetting('theme_background', '#09090b');
    $themeCard = getSetting('theme_card', '#141419');
    $themeTextPrimary = getSetting('theme_text_primary', '#f4f4f5');
    $themeTextSecondary = getSetting('theme_text_secondary', '#a1a1aa');

    $defaults = [
        'theme_primary' => '#6366f1', 'theme_accent' => '#a855f7',
        'theme_background' => '#09090b', 'theme_card' => '#141419',
        'theme_text_primary' => '#f4f4f5', 'theme_text_secondary' => '#a1a1aa',
    ];

    $themePrimary = validateCssColor($themePrimary) ?: $defaults['theme_primary'];
    $themeAccent = validateCssColor($themeAccent) ?: $defaults['theme_accent'];
    $themeBackground = validateCssColor($themeBackground) ?: $defaults['theme_background'];
    $themeCard = validateCssColor($themeCard) ?: $defaults['theme_card'];
    $themeTextPrimary = validateCssColor($themeTextPrimary) ?: $defaults['theme_text_primary'];
    $themeTextSecondary = validateCssColor($themeTextSecondary) ?: $defaults['theme_text_secondary'];

    $hexToRgbOnly = function($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } elseif (strlen($hex) == 6) {
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

function isSafeUrl($url, $allowHttp = true) {
    if (empty($url)) return false;
    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) return false;
    $scheme = strtolower($parts['scheme']);
    $allowedSchemes = $allowHttp ? ['http', 'https'] : ['https'];
    if (!in_array($scheme, $allowedSchemes, true)) return false;
    $host = rtrim($parts['host'], '.');
    if (filter_var($host, FILTER_VALIDATE_IP)) return isSafeIp($host);
    $ips = gethostbynamel($host);
    if ($ips === false || count($ips) === 0) return false;
    foreach ($ips as $ip) {
        if (!isSafeIp($ip)) return false;
    }
    return true;
}

function isSafeIp($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ipParts = explode('.', $ip);
        if (count($ipParts) !== 4) return false;
        $first = (int)$ipParts[0];
        $second = (int)$ipParts[1];
        if ($first === 127) return false;
        if ($first === 10) return false;
        if ($first === 172 && ($second >= 16 && $second <= 31)) return false;
        if ($first === 192 && $second === 168) return false;
        if ($first === 169 && $second === 254) return false;
        if ($first === 0) return false;
        return true;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        if ($ip === '::1' || $ip === '0000:0000:0000:0000:0000:0000:0000:0001') return false;
        if (stripos($ip, 'fe80:') === 0) return false;
        if (stripos($ip, 'fc00:') === 0 || stripos($ip, 'fd00:') === 0) return false;
        return true;
    }
    return false;
}

function sendWebhook($itemData, $buyerData, $debug = false) {
    $webhookUrl = getSetting('webhook_url', '');
    $webhookEnabled = getSetting('webhook_enabled', '0') === '1';
    if (!$webhookEnabled || empty($webhookUrl)) {
        return ['success' => false, 'message' => 'Webhook not configured or disabled'];
    }
    if (!isSafeUrl($webhookUrl, false)) {
        return ['success' => false, 'message' => 'Unsafe or invalid webhook URL provided.'];
    }
    $method = getSetting('webhook_method', 'POST');
    $contentType = getSetting('webhook_content_type', 'application/json');
    $timeout = (int)getSetting('webhook_timeout', 10);
    $verifySsl = getSetting('webhook_verify_ssl', '1') === '1';
    $secret = getSetting('webhook_secret', '');
    $queryParams = getSetting('webhook_query_params', '');

    if (!empty($queryParams)) {
        $separator = (parse_url($webhookUrl, PHP_URL_QUERY) === null) ? '?' : '&';
        $webhookUrl .= $separator . $queryParams;
    }

    $payload = array_merge($itemData, $buyerData);

    $bodyTemplate = getSetting('webhook_body_template', '');
    if (!empty($bodyTemplate)) {
        $body = $bodyTemplate;
        foreach ($payload as $key => $value) {
            $escapedValue = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (strlen($escapedValue) >= 2 && $escapedValue[0] === '"' && $escapedValue[strlen($escapedValue) - 1] === '"') {
                $escapedValue = substr($escapedValue, 1, -1);
            }
            $body = str_replace('{{' . $key . '}}', $escapedValue, $body);
        }
        if (stripos($contentType, 'application/json') !== false) {
            json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Webhook template produced invalid JSON: ' . json_last_error_msg(), 'http_code' => 0, 'response' => ''];
            }
        }
    } else {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false || json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'JSON encoding failed: ' . json_last_error_msg(), 'http_code' => 0, 'response' => ''];
        }
    }

    if ($contentType === 'application/x-www-form-urlencoded') {
        $body = http_build_query($payload);
    }

    $contentTypeHeader = 'Content-Type: application/json; charset=utf-8';
    if ($contentType === 'application/x-www-form-urlencoded') {
        $contentTypeHeader = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
    }
    $contentLength = strlen($body);

    $headers = [
        $contentTypeHeader,
        'Content-Length: ' . $contentLength,
        'User-Agent: Wishlist-PHP-Webhook/1.0 (+https://github.com/furkanplus/wishlist-php)',
        'Accept: application/json, */*',
        'X-Webhook-Event: item_bought'
    ];

    if (!empty($secret)) {
        $signature = hash_hmac('sha256', $body, $secret);
        $headers[] = 'X-Webhook-Signature: ' . $signature;
    }

    $debugInfo = null;
    if ($debug) {
        $debugInfo = [
            'request_url' => $webhookUrl,
            'request_method' => $method,
            'request_headers' => $headers,
            'request_body' => $body,
            'body_template_used' => !empty($bodyTemplate)
        ];
    }

    $ch = curl_init();
    $curlOpts = [
        CURLOPT_URL => $webhookUrl,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HEADER => false,
        CURLOPT_FAILONERROR => false,
    ];
    if (strtoupper($method) === 'POST') {
        $curlOpts[CURLOPT_POST] = true;
    } else {
        $curlOpts[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
    }
    curl_setopt_array($ch, $curlOpts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($error) {
        $result = ['success' => false, 'message' => 'cURL error (' . $errno . '): ' . $error, 'http_code' => 0, 'response' => ''];
        if ($debug) $result['debug'] = $debugInfo;
        return $result;
    }

    $trimmedResponse = strlen($response) > 1000 ? substr($response, 0, 1000) . '... [truncated]' : $response;

    if ($httpCode >= 200 && $httpCode < 300) {
        $result = ['success' => true, 'message' => 'Webhook delivered successfully', 'http_code' => $httpCode, 'response' => $trimmedResponse];
        if ($debug) $result['debug'] = $debugInfo;
        return $result;
    }

    $result = [
        'success' => false,
        'message' => "Webhook failed with HTTP $httpCode",
        'http_code' => $httpCode,
        'response' => $trimmedResponse
    ];
    if ($debug) $result['debug'] = $debugInfo;
    return $result;
}
