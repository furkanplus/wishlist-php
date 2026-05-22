<?php
// api/mark-bought.php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('__')) {
    function __($key, $default = '') {
        static $translations = null;
        
        $lang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'en';
        if ($lang === 'en') {
            return $default;
        }
        
        if ($translations === null) {
            $translations = [];
            $file = __DIR__ . '/../lang/' . $lang . '.php';
            if (file_exists($file)) {
                $translations = include $file;
            } else {
                // Backward compatibility / auto-migration from database
                global $pdo;
                if (isset($pdo)) {
                    try {
                        $stmt = $pdo->prepare('SELECT `translation_key`, `translation_value` FROM `translations` WHERE `lang` = ?');
                        $stmt->execute([$lang]);
                        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        if ($rows) {
                            $translations = $rows;
                            // Attempt to cache translations to static PHP file
                            $langDir = __DIR__ . '/../lang';
                            if (!is_dir($langDir)) {
                                @mkdir($langDir, 0755, true);
                            }
                            @file_put_contents($file, '<?php' . PHP_EOL . 'return ' . var_export($translations, true) . ';');
                        }
                    } catch (PDOException $e) {
                        // translations table doesn't exist
                    }
                }
            }
        }
        
        return $translations[$key] ?? $default;
    }
}

header('Content-Type: application/json');

// Get the POST payload
$input = json_decode(file_get_contents('php://input'), true);
$itemId = $input['item_id'] ?? null;
$buyerName = trim($input['buyer_name'] ?? '');
$buyerProof = trim($input['buyer_proof'] ?? '');

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
    $stmt = $pdo->prepare("SELECT `id`, `is_bought` FROM `wishlist_items` WHERE `id` = ?");
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
        echo json_encode(['success' => true, 'message' => __('success_marked_bought', 'Thank you! Item successfully marked as bought.')]);
    } else {
        echo json_encode(['success' => false, 'message' => __('err_updating_failed', 'An error occurred while updating the item.')]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => __('err_updating_failed', 'An error occurred while updating the item.') . ' ' . $e->getMessage()]);
}
