<?php
// index.php
if (!file_exists(__DIR__ . '/config.php')) {
    header("Location: install.php");
    exit;
}
require_once __DIR__ . '/config.php';

// Fetch settings
$generalNotes = getSetting('general_notes', '');
$shippingAddress = getSetting('shipping_address', '');
$shippingVisible = isShippingAddressVisible();
$shippingExpiresAt = getSetting('shipping_address_expires_at', '');

// Fetch items: Unbought items at the top (sorted by sort_order), bought items at the bottom
try {
    $stmt = $pdo->query("SELECT * FROM `wishlist_items` ORDER BY `is_bought` ASC, `sort_order` ASC");
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Wishlist</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Public Header -->
        <header>
            <div class="logo-container">
                <span class="logo-icon">🎁</span>
                <h1>My Wishlist</h1>
            </div>
            <p class="subtitle">Welcome! Here are some items I've been wishing for. If you purchase something, please mark it as bought to avoid duplicates.</p>
        </header>

        <!-- Navigation Bar -->
        <div class="nav-bar">
            <div></div> <!-- Spacer -->
            <a href="login.php" class="btn btn-secondary btn-sm">🔒 Admin Dashboard</a>
        </div>

        <!-- Info / Announcement Board -->
        <?php if (!empty($generalNotes) || ($shippingVisible && !empty($shippingAddress))): ?>
            <div class="info-section">
                <!-- General Notes Card -->
                <?php if (!empty($generalNotes)): ?>
                    <div class="info-card">
                        <h3>📝 Notes from Owner</h3>
                        <p><?= h($generalNotes) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Shipping Address Card -->
                <?php if ($shippingVisible && !empty($shippingAddress)): ?>
                    <div class="info-card">
                        <h3>📍 Shipping Address</h3>
                        <address><?= h($shippingAddress) ?></address>
                        
                        <?php if (!empty($shippingExpiresAt)): ?>
                            <div id="address-timer" data-expires="<?= strtotime($shippingExpiresAt) ?>" class="timer-badge">
                                ⏱️ Available for: <span id="timer-countdown">--:--:--</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Wishlist Items Grid -->
        <main class="wishlist-container">
            <h3 class="mb-4">🎁 Items List</h3>
            <div class="wishlist-grid">
                <?php if (empty($items)): ?>
                    <div class="info-card text-center" style="grid-column: 1/-1;">
                        <p class="text-muted">No items have been added to the wishlist yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="item-card <?= $item['is_bought'] ? 'bought' : '' ?>">
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
                                        <span>No Image</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="item-content">
                                <h4 class="item-title" title="<?= h($item['title']) ?>"><?= h($item['title']) ?></h4>
                                
                                <?php if ($item['notes']): ?>
                                    <p class="item-notes" title="<?= h($item['notes']) ?>"><?= h($item['notes']) ?></p>
                                <?php else: ?>
                                    <p class="item-notes text-muted">No specific details.</p>
                                <?php endif; ?>

                                <div class="item-actions">
                                    <a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">🌐 View Product</a>
                                    
                                    <?php if (!$item['is_bought']): ?>
                                        <button class="btn btn-primary btn-sm btn-mark-bought" data-id="<?= $item['id'] ?>" data-title="<?= h($item['title']) ?>">
                                            🎁 I've Bought This
                                        </button>
                                    <?php else: ?>
                                        <div class="bought-info-text">
                                            ✔ Purchased by <?= h($item['buyer_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Mark Bought Verification Modal -->
    <div class="modal-backdrop" id="buy-modal">
        <div class="modal-window">
            <div class="modal-header">
                <h3 class="modal-title">🎁 Mark as Bought</h3>
                <button class="modal-close" id="modal-close-buy">&times;</button>
            </div>
            
            <div id="modal-form-content">
                <p class="text-sm text-secondary mb-4">
                    You are marking <strong id="modal-item-title" style="color: var(--text-primary);">[Item Title]</strong> as purchased. 
                    To protect against accidental duplicates, you must provide verification.
                </p>
                
                <form id="form-mark-bought">
                    <input type="hidden" id="buy-item-id">
                    
                    <div class="form-group">
                        <label for="buyer-name">Your Name / Nickname (Optional)</label>
                        <input type="text" id="buyer-name" placeholder="e.g. Secret Santa, Aunt Mary">
                    </div>

                    <div class="form-group">
                        <label for="buyer-proof">Tracking Link OR Order ID (Required)</label>
                        <input type="text" id="buyer-proof" placeholder="e.g. UPS link or Amazon Order ID" required>
                        <span class="text-xs text-muted">This proof will only be visible to the wishlist owner to verify the purchase.</span>
                    </div>

                    <div id="modal-error" class="flash-message flash-danger" style="display: none; margin-top: 1rem;"></div>

                    <button type="submit" class="btn btn-primary btn-block mt-4">Confirm Purchase</button>
                </form>
            </div>

            <!-- Success State -->
            <div id="modal-success-content" style="display: none; text-align: center; padding: 2rem 1rem;">
                <span style="font-size: 3.5rem; display: block; margin-bottom: 1rem; animation: bounce 1s ease infinite;">🎉</span>
                <h3 style="color: var(--success); font-family: var(--font-heading); margin-bottom: 0.5rem;">Purchase Verified!</h3>
                <p class="text-sm text-secondary">The item has been marked as bought and moved to the bottom of the list.</p>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
