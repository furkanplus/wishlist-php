<?php
// api/update-order.php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Only allow admin access
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin login required.']);
    exit;
}

// Get the POST payload
$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format. Expected non-empty array of item IDs.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE `wishlist_items` SET `sort_order` = ? WHERE `id` = ?");
    
    foreach ($ids as $index => $id) {
        $stmt->execute([$index, (int)$id]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Order updated successfully.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Failed to save new order: ' . $e->getMessage()]);
}
