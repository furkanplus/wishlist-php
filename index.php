<?php
// index.php
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


// Fetch settings
$generalNotes = getSetting('general_notes', '');
$shippingAddress = getSetting('shipping_address', '');
$shippingVisible = isShippingAddressVisible();
$shippingExpiresAt = getSetting('shipping_address_expires_at', '');
$currency = getSetting('currency', 'USD');
$currencySymbol = getCurrencySymbol($currency);

// Sort handling
$sort = $_GET['sort'] ?? 'default';
$orderClause = "`is_bought` ASC, `sort_order` ASC";
if ($sort === 'price_asc') {
    $orderClause = "`is_bought` ASC, IFNULL(`estimated_price`, 999999) ASC";
} elseif ($sort === 'price_desc') {
    $orderClause = "`is_bought` ASC, IFNULL(`estimated_price`, 0) DESC";
}

// Fetch items
try {
    $stmt = $pdo->query("
        SELECT 
            `id`, `title`, `url`, `image_url`, `notes`, `estimated_price`, 
            `is_bought`, `buyer_name`, `buyer_proof`, `bought_at`, `is_archived`, `sort_order`, `created_at`,
            CASE 
                WHEN `is_bought` = 1 AND `buyer_message` IS NOT NULL AND `message_public` = 0 THEN NULL
                ELSE `buyer_message`
            END AS `buyer_message`,
            `message_public`
        FROM `wishlist_items` 
        WHERE `is_archived` = 0 
        ORDER BY {$orderClause}
    ");
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Unable to load wishlist. Please try again later.");
}
?>
<!DOCTYPE html>
<html lang="<?= h($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(__('index_title', 'Shared Wishlist')) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <?php echo getCustomStyles(); ?>
    <script>
        window.translations = {
            verification_required: <?= json_encode(__('js_verification_required', 'Verification proof (Tracking Link or Order ID) is required.')) ?>,
            verifying: <?= json_encode(__('js_verifying', 'Verifying...')) ?>,
            confirm_purchase: <?= json_encode(__('confirm_purchase', 'Confirm Purchase')) ?>,
            network_error: <?= json_encode(__('js_network_error', 'Unable to contact the server. Please check your network connection.')) ?>,
            message_visibility_private: <?= json_encode(__('message_visibility_private', 'This message will only be visible to the wishlist owner (admin).')) ?>,
            message_visibility_public: <?= json_encode(__('message_visibility_public', 'This message will be visible to everyone on the public wishlist.')) ?>,
            err_rate_limited: <?= json_encode(__('err_rate_limited', 'Too many requests. Please try again later.')) ?>
        };
    </script>
</head>
<body>
    <div class="container">
        <!-- Public Header -->
        <header>
            <div class="logo-container">
                <span class="logo-icon">🎁</span>
                <h1><?= h(__('index_title', 'My Wishlist')) ?></h1>
            </div>
            <p class="subtitle"><?= h(__('index_subtitle', "Welcome! Here are some items I've been wishing for. If you purchase something, please mark it as bought to avoid duplicates.")) ?></p>
        </header>

        <!-- Navigation Bar -->
        <div class="nav-bar">
            <div class="lang-selector">
                <?php foreach (getAvailableLanguages() as $langCode): ?>
                    <a href="?lang=<?= h($langCode) ?>" class="lang-btn <?= $currentLang === $langCode ? 'active' : '' ?>"><?= h(strtoupper($langCode)) ?></a>
                <?php endforeach; ?>
            </div>
            <a href="login.php" class="btn btn-secondary btn-sm"><?= h(__('admin_dashboard_link', '🔒 Admin Dashboard')) ?></a>
        </div>

        <!-- Info / Announcement Board -->
        <?php if (!empty($generalNotes) || ($shippingVisible && !empty($shippingAddress))): ?>
            <div class="info-section">
                <!-- General Notes Card -->
                <?php if (!empty($generalNotes)): ?>
                    <div class="info-card">
                        <h3><?= h(__('notes_from_owner', '📝 Notes from Owner')) ?></h3>
                        <p><?= h($generalNotes) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Shipping Address Card -->
                <?php if ($shippingVisible && !empty($shippingAddress)): ?>
                    <div class="info-card">
                        <h3><?= h(__('shipping_address', '📍 Shipping Address')) ?></h3>
                        <address><?= h($shippingAddress) ?></address>
                        
                        <?php if (!empty($shippingExpiresAt)): ?>
                            <div id="address-timer" data-expires="<?= strtotime($shippingExpiresAt) ?>" class="timer-badge">
                                <?= sprintf(h(__('available_for', '⏱️ Available for: %s')), '<span id="timer-countdown">--:--:--</span>') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Wishlist Items Grid -->
        <main class="wishlist-container">
            <div class="flex justify-between align-center mb-4" style="flex-wrap: wrap; gap: 0.5rem;">
                <h3><?= h(__('items_list', '🎁 Items List')) ?></h3>
                <div class="sort-controls flex gap-2 align-center">
                    <span class="text-xs text-muted"><?= h(__('sort_label', 'Sort:')) ?></span>
                    <a href="?sort=default" class="sort-btn <?= $sort === 'default' ? 'active' : '' ?>"><?= h(__('sort_default', 'Default')) ?></a>
                    <a href="?sort=price_asc" class="sort-btn <?= $sort === 'price_asc' ? 'active' : '' ?>"><?= h(__('sort_price_asc', 'Price: Low to High')) ?></a>
                    <a href="?sort=price_desc" class="sort-btn <?= $sort === 'price_desc' ? 'active' : '' ?>"><?= h(__('sort_price_desc', 'Price: High to Low')) ?></a>
                </div>
            </div>
            <div class="wishlist-grid">
                <?php if (empty($items)): ?>
                    <div class="info-card text-center" style="grid-column: 1/-1;">
                        <p class="text-muted"><?= h(__('empty_wishlist', 'No items have been added to the wishlist yet.')) ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="item-card <?= $item['is_bought'] ? 'bought' : '' ?>">
                            <?php if ($item['is_bought']): ?>
                                <span class="bought-badge"><?= h(__('bought_badge', 'BOUGHT')) ?></span>
                            <?php endif; ?>

                            <a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer" class="item-image-wrapper">
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
                            </a>

                            <div class="item-content">
                                <h4 class="item-title" title="<?= h($item['title']) ?>">
                                    <a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer">
                                        <?= h($item['title']) ?>
                                    </a>
                                </h4>
                                
                                <?php if ($item['notes']): ?>
                                    <p class="item-notes" title="<?= h($item['notes']) ?>"><?= h($item['notes']) ?></p>
                                <?php else: ?>
                                    <p class="item-notes text-muted">No specific details.</p>
                                <?php endif; ?>

                                <?php if ($item['estimated_price'] !== null): ?>
                                    <div class="item-price">~ <?= number_format((float)$item['estimated_price'], 2) ?><?= h($currencySymbol) ?></div>
                                <?php endif; ?>

                                <div class="item-actions">
                                    <a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm"><?= h(__('view_product', '🌐 View Product')) ?></a>
                                    
                                    <?php if (!$item['is_bought']): ?>
                                        <button class="btn btn-primary btn-sm btn-mark-bought" data-id="<?= $item['id'] ?>" data-title="<?= h($item['title']) ?>">
                                            <?= h(__('mark_bought', "🎁 I've Bought This")) ?>
                                        </button>
                                    <?php else: ?>
                                        <div class="bought-info-text">
                                            <?= sprintf(h(__('purchased_by', '✔ Purchased by %s')), h($item['buyer_name'] ? $item['buyer_name'] : __('anonymous_friend', 'Anonymous Friend'))) ?>
                                        </div>
                                        
                                        <?php if (!empty($item['buyer_message'])): ?>
                                            <?php $isPublic = !empty($item['message_public']); ?>
                                            <div class="buyer-message-<?= $isPublic ? 'public' : 'private' ?>" style="margin-top: 0.75rem; padding: 0.75rem; border-radius: 8px; border: 1px solid <?= $isPublic ? 'rgba(99, 102, 241, 0.2)' : 'rgba(249, 115, 22, 0.2)' ?>; background: <?= $isPublic ? 'rgba(99, 102, 241, 0.1)' : 'rgba(249, 115, 22, 0.1)' ?>;">
                                                <span class="text-xs text-muted block mb-1"><?= $isPublic ? '💬 ' . h(__('public_message_label', 'Public message from buyer:')) : '🔒 ' . h(__('private_message_label', 'Private message (owner only):')) ?></span>
                                                <p class="text-sm" style="white-space: pre-wrap; margin: 0;"><?= h($item['buyer_message']) ?></p>
                                            </div>
                                        <?php endif; ?>
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
                <h3 class="modal-title"><?= h(__('modal_title', '🎁 Mark as Bought')) ?></h3>
                <button class="modal-close" id="modal-close-buy">&times;</button>
            </div>
            
            <div id="modal-form-content">
                <p class="text-sm text-secondary mb-4">
                    <?= sprintf(h(__('modal_instructions', 'You are marking %s as purchased. To protect against accidental duplicates, you must provide verification.')), 
                        '<strong id="modal-item-title" style="color: var(--text-primary);">[Item Title]</strong>') ?>
                </p>
                
                <form id="form-mark-bought">
                    <input type="hidden" id="buy-item-id">
                    
                    <div class="form-group">
                        <label for="buyer-name"><?= h(__('buyer_name_label', 'Your Name / Nickname (Optional)')) ?></label>
                        <input type="text" id="buyer-name" placeholder="<?= h(__('buyer_name_placeholder', 'e.g. Secret Santa, Aunt Mary')) ?>">
                    </div>

                    <div class="form-group">
                        <label for="buyer-proof"><?= h(__('buyer_proof_label', 'Tracking Link OR Order ID (Required)')) ?></label>
                        <input type="text" id="buyer-proof" placeholder="<?= h(__('buyer_proof_placeholder', 'e.g. UPS link or Amazon Order ID')) ?>" required>
                        <span class="text-xs text-muted"><?= h(__('buyer_proof_desc', 'This proof will only be visible to the wishlist owner to verify the purchase.')) ?></span>
                    </div>

                    <div class="form-group">
                        <label for="buyer-message"><?= h(__('buyer_message_label', 'Message (Optional)')) ?></label>
                        <textarea id="buyer-message" rows="3" placeholder="<?= h(__('buyer_message_placeholder', 'e.g. Hope you like it! Happy holidays!')) ?>"></textarea>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="message-public-toggle">
                        <label for="message-public-toggle"><?= h(__('message_public_checkbox', 'Make message visible on public wishlist')) ?></label>
                    </div>
                    <span class="text-xs text-muted" id="message-visibility-hint"><?= h(__('message_visibility_private', 'This message will only be visible to the wishlist owner (admin).')) ?></span>

                    <div id="modal-error" class="flash-message flash-danger" style="display: none; margin-top: 1rem;"></div>

                    <button type="submit" class="btn btn-primary btn-block mt-4"><?= h(__('confirm_purchase', 'Confirm Purchase')) ?></button>
                </form>
            </div>

            <!-- Success State -->
            <div id="modal-success-content" style="display: none; text-align: center; padding: 2rem 1rem;">
                <span style="font-size: 3.5rem; display: block; margin-bottom: 1rem; animation: bounce 1s ease infinite;">🎉</span>
                <h3 style="color: var(--success); font-family: var(--font-heading); margin-bottom: 0.5rem;"><?= h(__('modal_success_title', 'Purchase Verified!')) ?></h3>
                <p class="text-sm text-secondary"><?= h(__('modal_success_desc', 'The item has been marked as bought and moved to the bottom of the list.')) ?></p>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <footer class="page-footer" style="margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); text-align: center;">
        <p class="text-xs text-muted" style="margin: 0;">
            Built with <a href="https://github.com/furkanplus/wishlist-php" target="_blank" rel="noopener noreferrer" style="color: var(--primary); text-decoration: none; font-weight: 500;">wishlist-php</a> &mdash; <a href="https://github.com/furkanplus/wishlist-php" target="_blank" rel="noopener noreferrer" style="color: var(--text-secondary); text-decoration: none;">View on GitHub</a>
        </p>
    </footer>
</body>
</html>
