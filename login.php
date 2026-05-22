<?php
// login.php
if (!file_exists(__DIR__ . '/config.php')) {
    header("Location: install.php");
    exit;
}
require_once __DIR__ . '/config.php';

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
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Both username and password are required.';
    } else {
        if (!$adminExists) {
            // First time setup - register admin
            try {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`) VALUES (?, ?)");
                $stmt->execute([$username, $hashedPassword]);
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $username;
                
                header("Location: admin.php");
                exit;
            } catch (PDOException $e) {
                $error = 'Registration failed: ' . $e->getMessage();
            }
        } else {
            // Regular Login
            try {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user'] = $user['username'];
                    
                    header("Location: admin.php");
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (PDOException $e) {
                $error = 'Login failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !$adminExists ? 'Create Admin Account' : 'Admin Login' ?> - Wishlist</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                <h1>Wishlist Admin</h1>
            </div>
            <p class="subtitle">
                <?= !$adminExists ? 'No administrator account found. Let\'s set up your access.' : 'Access the dashboard to manage your wishlist items.' ?>
            </p>
        </header>

        <main class="login-container">
            <div class="info-card login-card">
                <h3 class="text-center mb-4">
                    <?= !$adminExists ? '🛡️ Create Admin Account' : '🔒 Secure Admin Login' ?>
                </h3>

                <?php if ($error): ?>
                    <div class="flash-message flash-danger">
                        <span><?= h($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="e.g. admin" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block mt-4">
                        <?= !$adminExists ? 'Create & Log In' : 'Sign In' ?>
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="index.php" class="text-sm">← Back to public wishlist</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
