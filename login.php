<?php
// login.php
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

// Set security headers
setSecurityHeaders();

$currentLang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'en';


// Redirect if already logged in
if (isAdmin()) {
    header("Location: admin.php");
    exit;
}

$error = '';
$success = '';

// Check if any admin exists
try {
    $adminExists = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn() > 0;
} catch (PDOException $e) {
    die("Database access failed: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit("login:$ip", 5, 900)) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
    } else {
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = __('err_login_required', 'Both username and password are required.');
        } elseif (strlen($password) < 12) {
            $error = 'Password must be at least 12 characters long.';
        } else {
            if (!$adminExists) {
                // First time setup - register admin
                try {
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`) VALUES (?, ?)");
                    $stmt->execute([$username, $hashedPassword]);
                    
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user'] = $username;
                    
                    header("Location: admin.php");
                    exit;
                } catch (PDOException $e) {
                    $error = __('err_registration_failed', 'Registration failed. Please try again.');
                }
            } else {
                // Regular Login
                try {
                    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password_hash'])) {
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_user'] = $user['username'];
                        
                        header("Location: admin.php");
                        exit;
                    } else {
                        $error = __('err_login_invalid', 'Invalid username or password.');
                    }
                } catch (PDOException $e) {
                    $error = __('err_login_failed', 'Login failed. Please try again.');
                }
            }
        }
    }
} // closes if POST
?>
<!DOCTYPE html>
<html lang="<?= h($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !$adminExists ? h(__('login_title_create', 'Create Admin Account')) : h(__('login_title_login', 'Admin Login')) ?> - <?= h(__('index_title', 'Shared Wishlist')) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php echo getCustomStyles(); ?>
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 70vh;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-container">
                <span class="logo-icon">🎁</span>
                <h1><?= h(__('index_title', 'My Wishlist')) ?></h1>
            </div>
            <p class="subtitle">
                <?= !$adminExists ? h(__('login_subtitle_create', 'No administrator account found. Let\'s set up your access.')) : h(__('login_subtitle_login', 'Access the dashboard to manage your wishlist items.')) ?>
            </p>
        </header>

        <div class="nav-bar" style="justify-content: flex-end;">
            <div class="lang-selector">
                <?php foreach (getAvailableLanguages() as $langCode): ?>
                    <a href="?lang=<?= h($langCode) ?>" class="lang-btn <?= $currentLang === $langCode ? 'active' : '' ?>"><?= h(strtoupper($langCode)) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <main class="login-container">
            <div class="info-card login-card">
                <h3 class="text-center mb-4">
                    <?= !$adminExists ? h(__('login_card_create', '🛡️ Create Admin Account')) : h(__('login_card_login', '🔒 Secure Admin Login')) ?>
                </h3>

                <?php if ($error): ?>
                    <div class="flash-message flash-danger">
                        <span><?= h($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label for="username"><?= h(__('username_label', 'Username')) ?></label>
                        <input type="text" id="username" name="username" placeholder="e.g. admin" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password"><?= h(__('password_label', 'Password')) ?></label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block mt-4">
                        <?= !$adminExists ? h(__('login_btn_create', 'Create & Log In')) : h(__('login_btn_login', 'Sign In')) ?>
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="index.php" class="text-sm"><?= h(__('back_to_public', '← Back to public wishlist')) ?></a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
