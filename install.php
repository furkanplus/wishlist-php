<?php
// install.php

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

$configFile = __DIR__ . '/config.php';
$schemaFile = __DIR__ . '/schema.sql';

$isInstalled = false;

// Check if already installed
if (file_exists($configFile)) {
    // Attempt connection to see if it is functional
    try {
        @include_once $configFile;
        if (defined('DB_HOST') && isset($pdo)) {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('users', $tables) && in_array('settings', $tables)) {
                // Already installed and working
                $isInstalled = true;
            }
        }
    } catch (Exception $e) {
        // config.php exists but database is not working, let the user re-install/fix
        $isInstalled = false;
    }
}

if ($isInstalled) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Already Installed - Wishlist App</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <div class="container text-center" style="margin-top: 15vh;">
            <div class="info-card" style="max-width: 500px; margin: 0 auto;">
                <span style="font-size: 3rem; display: block; margin-bottom: 1rem;">🔒</span>
                <h3 style="color: var(--success); font-family: var(--font-heading); margin-bottom: 1rem;">Already Configured</h3>
                <p class="text-sm text-secondary mb-4">
                    The Wishlist application is already installed and connected to the database.
                </p>
                <p class="text-xs text-muted mb-4">
                    For security reasons, the installer has been locked. If you wish to run it again, please delete or rename <code>config.php</code> in your server files.
                </p>
                <div class="flex gap-2 justify-between">
                    <a href="index.php" class="btn btn-primary btn-block">👁️ View Wishlist</a>
                    <a href="login.php" class="btn btn-secondary btn-block">🔒 Admin Login</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$error = '';
$success = false;
$generatedConfigCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
    $db_port = trim($_POST['db_port'] ?? '3306');
    $db_name = trim($_POST['db_name'] ?? 'wishlist_db');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    
    $admin_user = trim($_POST['admin_user'] ?? '');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';
    
    if (empty($db_user) || empty($db_name) || empty($admin_user) || empty($admin_pass)) {
        $error = 'Please fill out all required fields.';
    } elseif ($admin_pass !== $admin_pass_confirm) {
        $error = 'Admin passwords do not match.';
    } else {
        try {
            // 1. Try to connect to MySQL without selecting database (in case it needs to be created)
            $dsn_no_db = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
            $pdo_init = new PDO($dsn_no_db, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            // Create database
            $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo_init = null; // close connection
            
            // 2. Connect to the new database
            $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // 3. Load and execute schema
            if (!file_exists($schemaFile)) {
                throw new Exception("Schema file (schema.sql) is missing from the directory.");
            }
            $schemaSql = file_get_contents($schemaFile);
            $pdo->exec($schemaSql);
            
            // 4. Register Admin User
            $hashedPass = password_hash($admin_pass, PASSWORD_BCRYPT);
            // Check if admin user already exists, if not insert
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
            $stmt->execute([$admin_user]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`) VALUES (?, ?)");
                $stmt->execute([$admin_user, $hashedPass]);
            }
            
            // 5. Generate config.php Code
            $configTemplate = "<?php
// config.php
// Dynamically generated by Web Installer

if (function_exists('session_status')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} elseif (function_exists('session_start') && function_exists('session_id')) {
    if (session_id() === '') {
        session_start();
    }
}

if (!isset(\$_SESSION)) {
    \$_SESSION = [];
}

define('DB_HOST', " . var_export($db_host, true) . ");
define('DB_PORT', " . var_export($db_port, true) . ");
define('DB_NAME', " . var_export($db_name, true) . ");
define('DB_USER', " . var_export($db_user, true) . ");
define('DB_PASS', " . var_export($db_pass, true) . ");

try {
    \$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException \$e) {
    die('Database Connection Error: ' . \$e->getMessage() . '<br><br>Please check credentials in config.php.');
}

// Helpers
function isAdmin() {
    return isset(\$_SESSION['admin_logged_in']) && \$_SESSION['admin_logged_in'] === true;
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}

function h(\$string) {
    return htmlspecialchars(\$string ?? '', ENT_QUOTES, 'UTF-8');
}

function getSetting(\$key, \$default = '') {
    global \$pdo;
    try {
        \$stmt = \$pdo->prepare('SELECT `value` FROM `settings` WHERE `key` = ?');
        \$stmt->execute([\$key]);
        \$row = \$stmt->fetch();
        return \$row ? \$row['value'] : \$default;
    } catch (PDOException \$e) {
        return \$default;
    }
}

function setSetting(\$key, \$value) {
    global \$pdo;
    \$stmt = \$pdo->prepare('INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?');
    return \$stmt->execute([\$key, \$value, \$value]);
}

function isShippingAddressVisible() {
    \$visible = getSetting('shipping_address_visible', '1') === '1';
    if (!\$visible) return false;
    
    \$expires_at = getSetting('shipping_address_expires_at', '');
    if (!empty(\$expires_at)) {
        \$expiry_time = strtotime(\$expires_at);
        if (\$expiry_time !== false && time() > \$expiry_time) {
            return false;
        }
    }
    return true;
}
";
            
            // 6. Try to write config.php
            if (@file_put_contents($configFile, $configTemplate) !== false) {
                $success = true;
                // Secure permissions if host environment allows
                @chmod($configFile, 0600) || @chmod($configFile, 0640);
                // Delete schema.sql on successful installation for security
                if (file_exists($schemaFile)) {
                    @unlink($schemaFile);
                }
            } else {
                // If not writable, prepare manual code copy paste instruction
                $success = true;
                $generatedConfigCode = $configTemplate;
            }
            
        } catch (PDOException $e) {
            $error = 'Database Setup Failed: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = 'Installation Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist Application Web Setup Wizard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .install-card {
            max-width: 600px;
            margin: 0 auto;
        }
        .step-title {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
            margin-bottom: 1.25rem;
            font-family: var(--font-heading);
            font-size: 1.15rem;
            color: var(--primary);
        }
        pre {
            background: #1e1e24;
            padding: 1rem;
            border-radius: var(--radius-sm);
            overflow-x: auto;
            border: 1px solid var(--border-color);
            margin: 1rem 0;
            user-select: all;
        }
        code {
            font-family: monospace;
            color: #e4e4e7;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-container">
                <span class="logo-icon">🚀</span>
                <h1>Wishlist Setup Wizard</h1>
            </div>
            <p class="subtitle">Quickly deploy your mobile-first wishlist app on your cPanel or virtual hosting server.</p>
        </header>

        <main>
            <div class="info-card install-card">
                <?php if ($success): ?>
                    <div style="text-align: center; padding: 1.5rem 0;">
                        <span style="font-size: 4rem; display: block; margin-bottom: 1rem; animation: bounce 1.5s infinite;">🎉</span>
                        <h2 style="color: var(--success); font-family: var(--font-heading); margin-bottom: 0.5rem;">Setup Completed!</h2>
                        
                        <?php if (!empty($generatedConfigCode)): ?>
                            <div class="flash-message flash-danger" style="text-align: left; margin: 1.5rem 0;">
                                <strong>⚠️ Directory is not writable!</strong><br>
                                The installer could not write the config file automatically. Please create a file named <code>config.php</code> in your root directory and paste the following code:
                            </div>
                            <pre><code><?= htmlspecialchars($generatedConfigCode) ?></code></pre>
                        <?php else: ?>
                            <p class="text-sm text-secondary mb-4">
                                Database schema was initialized and <code>config.php</code> has been successfully written to the server directory.
                            </p>
                        <?php endif; ?>
                        
                        <p class="text-sm text-secondary mb-4">
                            You can now view your wishlist or log into the administrator control panel.
                        </p>
                        
                        <div class="flex gap-3 mt-4" style="flex-wrap: wrap;">
                            <a href="index.php" class="btn btn-primary" style="flex: 1;">👁️ View Wishlist</a>
                            <a href="login.php" class="btn btn-secondary" style="flex: 1;">🔒 Admin Login</a>
                        </div>
                    </div>
                <?php else: ?>
                    <h3 class="text-center mb-4">⚙️ Installer Settings</h3>
                    
                    <?php if ($error): ?>
                        <div class="flash-message flash-danger">
                            <span><?= h($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="install.php" method="POST">
                        
                        <!-- DB Section -->
                        <div class="step-title">1. MySQL Database Settings</div>
                        
                        <div class="form-group">
                            <label for="db_host">Database Host (usually localhost or 127.0.0.1)</label>
                            <input type="text" id="db_host" name="db_host" value="127.0.0.1" required>
                        </div>

                        <div class="form-group">
                            <label for="db_port">Database Port</label>
                            <input type="text" id="db_port" name="db_port" value="3306" required>
                        </div>

                        <div class="form-group">
                            <label for="db_name">Database Name (will create if doesn't exist)</label>
                            <input type="text" id="db_name" name="db_name" value="wishlist_db" required>
                        </div>

                        <div class="form-group">
                            <label for="db_user">Database Username</label>
                            <input type="text" id="db_user" name="db_user" placeholder="e.g. cpanel_user" required>
                        </div>

                        <div class="form-group">
                            <label for="db_pass">Database Password</label>
                            <input type="password" id="db_pass" name="db_pass" placeholder="Database user password">
                        </div>

                        <!-- Admin Setup Section -->
                        <div class="step-title mt-4">2. Administrator Setup</div>

                        <div class="form-group">
                            <label for="admin_user">Admin Username</label>
                            <input type="text" id="admin_user" name="admin_user" placeholder="e.g. admin" required>
                        </div>

                        <div class="form-group">
                            <label for="admin_pass">Admin Password</label>
                            <input type="password" id="admin_pass" name="admin_pass" placeholder="Minimum 6 characters" required>
                        </div>

                        <div class="form-group">
                            <label for="admin_pass_confirm">Confirm Admin Password</label>
                            <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" placeholder="Repeat password" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block mt-4">Install & Run Setup</button>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
