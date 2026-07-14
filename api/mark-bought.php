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
        
        if (isset($translations[$key]) && trim($translations[$key]) !== '') {
            return $translations[$key];
        }
        return $default;
    }
}

header('Content-Type: application/json');

// Get the POST payload
$input = json_decode(file_get_contents('php://input'), true);
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
        'buyer_name' => $buyerName,
        'buyer_proof' => $buyerProof,
        'buyer_message' => $buyerMessage,
        'message_public' => $messagePublic ? 'true' : 'false',
        'bought_at' => $boughtAt,
        'source' => 'buyer',
    ];
    // Send webhook asynchronously (fire and forget)
    if (function_exists('sendWebhook')) {
        sendWebhook($itemData, $buyerData);
    }
    
    echo json_encode(['success' => true, 'message' => __('success_marked_bought', 'Thank you! Item successfully marked as bought.')]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => __('err_updating_failed', 'An error occurred while updating the item.')]);
}
