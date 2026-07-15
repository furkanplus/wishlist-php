<?php
// api/mark-bought.php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Get the POST payload
$input = json_decode(file_get_contents('php://input'), true);

// Rate limiting to prevent abuse
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$itemIdParam = $input['item_id'] ?? null;
$rateLimitKey = 'mark-bought:' . $ip . ':' . ($itemIdParam ?? 'unknown');
if (function_exists('checkRateLimit') && !checkRateLimit($rateLimitKey, 10, 60)) {
    echo json_encode(['success' => false, 'message' => __('err_rate_limited', 'Too many requests. Please try again later.')]);
    exit;
}
$itemId = $input['item_id'] ?? null;
$buyerName = trim($input['buyer_name'] ?? '');
$buyerProof = trim($input['buyer_proof'] ?? '');
$buyerMessage = trim($input['buyer_message'] ?? '');
$messagePublic = isset($input['message_public']) ? (bool)$input['message_public'] : false;

if (empty($itemId)) {
    echo json_encode(['success' => false, 'message' => __('err_item_id_required', 'Item ID is required.')]);
    exit;
}

if (empty($buyerProof)) {
    echo json_encode(['success' => false, 'message' => __('err_proof_required', 'You must provide a Tracking Link or Order ID as proof.')]);
    exit;
}

try {
    // Check if the item exists and is not already bought
    $stmt = $pdo->prepare("SELECT `id`, `is_bought`, `title`, `url`, `image_url`, `estimated_price`, `notes` FROM `wishlist_items` WHERE `id` = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => __('err_item_not_found', 'Wishlist item not found.')]);
        exit;
    }
    
    if ((int)$item['is_bought'] === 1) {
        echo json_encode(['success' => false, 'message' => __('err_already_bought', 'This item has already been marked as bought.')]);
        exit;
    }
    
    // Set default name if empty
    if (empty($buyerName)) {
        $buyerName = __('anonymous_friend', 'Anonymous Friend');
    }

    // Update item as bought atomically
    $updateStmt = $pdo->prepare("
        UPDATE `wishlist_items` 
        SET `is_bought` = 1, 
            `buyer_name` = ?, 
            `buyer_proof` = ?, 
            `buyer_message` = ?,
            `message_public` = ?,
            `bought_at` = NOW() 
        WHERE `id` = ? AND `is_bought` = 0
    ");
    $updateStmt->execute([$buyerName, $buyerProof, $buyerMessage, $messagePublic ? 1 : 0, $itemId]);
    
    if ($updateStmt->rowCount() === 0) {
        // Concurrent request already marked it as bought
        echo json_encode(['success' => false, 'message' => __('err_already_bought', 'This item has already been marked as bought.')]);
        exit;
    }
    
    // Send webhook notification
    $boughtAt = date('Y-m-d H:i:s');
    $itemData = [
        'item_id' => $item['id'],
        'item_title' => $item['title'],
        'item_url' => $item['url'],
        'item_image' => $item['image_url'] ?? '',
        'estimated_price' => $item['estimated_price'] ?? '',
        'notes' => $item['notes'] ?? '',
    ];
    $buyerData = [
        'event_id' => bin2hex(random_bytes(16)),
        'buyer_name' => $buyerName,
        'buyer_proof' => $buyerProof,
        'buyer_message' => $buyerMessage,
        'message_public' => $messagePublic ? 'true' : 'false',
        'bought_at' => $boughtAt,
        'source' => 'buyer',
    ];
    if (function_exists('sendWebhook')) {
        $whResult = sendWebhook($itemData, $buyerData);
        if (!$whResult['success']) {
            error_log("Wishlist webhook failed for item #{$itemId}: {$whResult['message']}");
        }
    }
    
    echo json_encode(['success' => true, 'message' => __('success_marked_bought', 'Thank you! Item successfully marked as bought.')]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => __('err_updating_failed', 'An error occurred while updating the item.')]);
}
