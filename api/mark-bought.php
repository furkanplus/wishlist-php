<?php
// api/mark-bought.php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Get the POST payload
$input = json_decode(file_get_contents('php://input'), true);
$itemId = $input['item_id'] ?? null;
$buyerName = trim($input['buyer_name'] ?? '');
$buyerProof = trim($input['buyer_proof'] ?? '');

if (empty($itemId)) {
    echo json_encode(['success' => false, 'message' => 'Item ID is required.']);
    exit;
}

if (empty($buyerProof)) {
    echo json_encode(['success' => false, 'message' => 'You must provide a Tracking Link or Order ID as proof.']);
    exit;
}

try {
    // Check if the item exists and is not already bought
    $stmt = $pdo->prepare("SELECT `id`, `is_bought` FROM `wishlist_items` WHERE `id` = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Wishlist item not found.']);
        exit;
    }
    
    if ((int)$item['is_bought'] === 1) {
        echo json_encode(['success' => false, 'message' => 'This item has already been marked as bought.']);
        exit;
    }
    
    // Set default name if empty
    if (empty($buyerName)) {
        $buyerName = 'Anonymous Friend';
    }
    
    // Update item as bought
    $updateStmt = $pdo->prepare("
        UPDATE `wishlist_items` 
        SET `is_bought` = 1, 
            `buyer_name` = ?, 
            `buyer_proof` = ?, 
            `bought_at` = NOW() 
        WHERE `id` = ?
    ");
    $updateResult = $updateStmt->execute([$buyerName, $buyerProof, $itemId]);
    
    if ($updateResult) {
        echo json_encode(['success' => true, 'message' => 'Thank you! Item successfully marked as bought.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating the item.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
