<?php
// admin.php
if (!file_exists(__DIR__ . '/config.php')) {
    header("Location: install.php");
    exit;
}
require_once __DIR__ . '/config.php';

// Enforce admin login
requireAdmin();

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
    
    if ($_POST['action'] === 'save_settings') {
        setSetting('shipping_address', $_POST['shipping_address'] ?? '');
        setSetting('shipping_address_visible', isset($_POST['shipping_address_visible']) ? '1' : '0');
        setSetting('shipping_address_expires_at', $_POST['shipping_address_expires_at'] ?? '');
        setSetting('general_notes', $_POST['general_notes'] ?? '');
        
        $_SESSION['flash_success'] = "Settings saved successfully.";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                <h1>Admin Control Panel</h1>
            </div>
            <p class="subtitle">Manage items, sort manually, view purchase verifications, and edit configurations.</p>
        </header>

        <!-- Navigation -->
        <div class="nav-bar admin-nav">
            <span class="text-sm text-muted">Logged in as: <strong><?= h($_SESSION['admin_user']) ?></strong></span>
            <div class="flex gap-2">
                <a href="index.php" class="btn btn-secondary btn-sm">👁️ View Public List</a>
                <a href="logout.php" class="btn btn-danger btn-sm">🚪 Sign Out</a>
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

        <div class="admin-grid">
            <!-- LEFT COLUMN: Forms & Settings -->
            <div class="flex flex-col gap-3">
                
                <!-- Add New Item Section -->
                <div class="add-item-box">
                    <h3 class="mb-4">➕ Add Wishlist Item</h3>
                    
                    <div class="form-group">
                        <label for="scrape-url">Paste Product Link</label>
                        <div class="url-input-container">
                            <input type="url" id="scrape-url" placeholder="https://example.com/product-page" autocomplete="off">
                            <button type="button" id="btn-scrape" class="btn btn-primary">Fetch Details</button>
                        </div>
                        <div id="scrape-loading" class="scraping-indicator">
                            <div class="spinner"></div>
                            <span>Analyzing page and grabbing image...</span>
                        </div>
                    </div>

                    <!-- Add form (reveals details once scraped or manually entered) -->
                    <form action="admin.php" method="POST" id="form-add-item">
                        <input type="hidden" name="action" value="add_item">
                        
                        <div class="form-group">
                            <label for="add-title">Product Title</label>
                            <input type="text" id="add-title" name="title" placeholder="Enter title" required>
                        </div>

                        <div class="form-group">
                            <label for="add-url">Product Link URL</label>
                            <input type="url" id="add-url" name="url" placeholder="https://..." required>
                        </div>

                        <div class="form-group">
                            <label for="add-image">Image URL</label>
                            <input type="url" id="add-image" name="image_url" placeholder="https://... (or leave empty)">
                            <div class="preview-pane mt-2" id="add-image-preview-container">
                                <span class="text-xs text-muted block mb-1">Image Preview:</span>
                                <img src="" alt="" class="preview-image" id="add-image-preview">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="add-notes">Notes (Size, color, preferences)</label>
                            <textarea id="add-notes" name="notes" rows="2" placeholder="e.g. Size M, color black"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Add to Wishlist</button>
                    </form>
                </div>

                <!-- Settings Box -->
                <div class="add-item-box">
                    <h3 class="mb-4">⚙️ General Settings</h3>
                    <form action="admin.php" method="POST">
                        <input type="hidden" name="action" value="save_settings">

                        <div class="form-group">
                            <label for="general-notes">General Announcement/Notes</label>
                            <textarea id="general-notes" name="general_notes" rows="3" placeholder="Notes for everyone visiting..."><?= h($generalNotes) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="shipping-address">Shipping Address Area</label>
                            <textarea id="shipping-address" name="shipping_address" rows="3" placeholder="Enter your full shipping address details..."><?= h($shippingAddress) ?></textarea>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="shipping-visible" name="shipping_address_visible" value="1" <?= $shippingAddressVisible ? 'checked' : '' ?>>
                            <label for="shipping-visible">Make shipping address visible to visitors</label>
                        </div>

                        <div class="form-group">
                            <label for="shipping-deadline">Visibility Deadline (Optional)</label>
                            <input type="datetime-local" id="shipping-deadline" name="shipping_address_expires_at" value="<?= h($shippingAddressExpiresAt) ?>">
                            <span class="text-xs text-muted">The shipping address will automatically hide after this time. Leave empty for permanent visibility.</span>
                        </div>

                        <button type="submit" class="btn btn-secondary btn-block">Save Config Settings</button>
                    </form>
                </div>

                <!-- Change Password Box -->
                <div class="add-item-box">
                    <h3 class="mb-4">🔒 Change Password</h3>
                    <form action="admin.php" method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current-password">Current Password</label>
                            <input type="password" id="current-password" name="current_password" placeholder="••••••••" required>
                        </div>

                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <input type="password" id="new-password" name="new_password" placeholder="Minimum 6 characters" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm-password">Confirm New Password</label>
                            <input type="password" id="confirm-password" name="confirm_password" placeholder="Repeat new password" required>
                        </div>

                        <button type="submit" class="btn btn-secondary btn-block">Update Password</button>
                    </form>
                </div>
            </div>

            <!-- RIGHT COLUMN: Interactive Sortable Wishlist Grid -->
            <div class="wishlist-container">
                <h3 class="mb-4">📋 Current Wishlist Items</h3>
                <p class="text-xs text-muted mb-4">💡 Drag and drop cards to reorder. Mobile users can use the arrow buttons (↑/↓) to sort items.</p>
                
                <div class="wishlist-grid" id="sortable-list">
                    <?php if (empty($items)): ?>
                        <div class="info-card text-center" style="grid-column: 1/-1;">
                            <p class="text-muted">Your wishlist is currently empty. Use the form on the left to add items!</p>
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
                                    <span class="bought-badge">BOUGHT</span>
                                <?php endif; ?>

                                <div class="item-image-wrapper">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?= h($item['image_url']) ?>" alt="<?= h($item['title']) ?>" class="item-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="item-placeholder" style="display:none;">
                                            <span>🎁</span>
                                            <span>No Image Loaded</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="item-placeholder">
                                            <span>🎁</span>
                                            <span>No Image Provided</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="item-content">
                                    <h4 class="item-title" title="<?= h($item['title']) ?>"><?= h($item['title']) ?></h4>
                                    
                                    <?php if ($item['notes']): ?>
                                        <p class="item-notes" title="<?= h($item['notes']) ?>"><?= h($item['notes']) ?></p>
                                    <?php else: ?>
                                        <p class="item-notes text-muted">No specific notes.</p>
                                    <?php endif; ?>

                                    <!-- Admin Proof Area -->
                                    <?php if ($item['is_bought']): ?>
                                        <div style="background: rgba(16, 185, 129, 0.08); padding: 8px; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 12px; word-break: break-all;">
                                            <span class="text-xs text-muted block">Purchased by:</span>
                                            <strong class="text-sm block" style="color: var(--success);"><?= h($item['buyer_name']) ?></strong>
                                            <span class="text-xs text-muted block mt-1">Verification Proof:</span>
                                            <code class="text-xs" style="color: var(--text-primary);"><?= h($item['buyer_proof']) ?></code>
                                        </div>
                                    <?php endif; ?>

                                    <div class="item-actions">
                                        <a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">🌐 Open Link</a>
                                        <button class="btn btn-secondary btn-sm btn-edit" 
                                                data-id="<?= $item['id'] ?>"
                                                data-title="<?= h($item['title']) ?>"
                                                data-url="<?= h($item['url']) ?>"
                                                data-image="<?= h($item['image_url']) ?>"
                                                data-notes="<?= h($item['notes']) ?>">
                                            ✏️ Edit
                                        </button>
                                        <div class="flex gap-2">
                                            <a href="admin.php?toggle_bought=<?= $item['id'] ?>" class="btn btn-secondary btn-sm" style="flex-grow: 1;">
                                                <?= $item['is_bought'] ? '↩️ Unmark' : '✅ Mark Bought' ?>
                                            </a>
                                            <a href="admin.php?delete=<?= $item['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove this item from your wishlist?');" title="Delete">🗑️</a>
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

    <!-- Edit Item Modal -->
    <div class="modal-backdrop" id="edit-modal">
        <div class="modal-window">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Edit Wishlist Item</h3>
                <button class="modal-close" id="modal-close">&times;</button>
            </div>
            <form action="admin.php" method="POST">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="id" id="edit-id">

                <div class="form-group">
                    <label for="edit-title">Product Title</label>
                    <input type="text" id="edit-title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="edit-url">Product Link URL</label>
                    <input type="url" id="edit-url" name="url" required>
                </div>

                <div class="form-group">
                    <label for="edit-image">Image URL</label>
                    <input type="url" id="edit-image" name="image_url">
                    <div class="preview-pane mt-2" id="edit-image-preview-container" style="display: block;">
                        <span class="text-xs text-muted block mb-1">Image Preview:</span>
                        <img src="" alt="" class="preview-image" id="edit-image-preview">
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit-notes">Notes (Size, color, preferences)</label>
                    <textarea id="edit-notes" name="notes" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
            </form>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
