<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['userid']) || $_SESSION['type'] !== 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login as a seller.']);
    exit();
}

require_once(__DIR__ . '/../../include/connection.php');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_POST['action'] ?? '');
$product_id = isset($input['product_id']) ? (int)$input['product_id'] : (isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0);
$seller_id = (int)$_SESSION['userid'];

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    exit();
}

// Verify ownership
$checkStmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND seller_id = ?");
$checkStmt->execute([$product_id, $seller_id]);
$product = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found or access denied.']);
    exit();
}

try {
    if ($action === 'delete') {
        $pdo->beginTransaction();

        // 1. Delete color_sizes
        $delSizes = $pdo->prepare("DELETE cs FROM color_sizes cs JOIN product_colors pc ON cs.color_id = pc.id WHERE pc.product_id = ?");
        $delSizes->execute([$product_id]);

        // 2. Delete color_images
        $delColImgs = $pdo->prepare("DELETE ci FROM color_images ci JOIN product_colors pc ON ci.color_id = pc.id WHERE pc.product_id = ?");
        $delColImgs->execute([$product_id]);

        // 3. Delete product_colors
        $delColors = $pdo->prepare("DELETE FROM product_colors WHERE product_id = ?");
        $delColors->execute([$product_id]);

        // 4. Delete product_images
        $delProdImgs = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
        $delProdImgs->execute([$product_id]);

        // 5. Delete product
        $delProd = $pdo->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
        $delProd->execute([$product_id, $seller_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Product "' . $product['name'] . '" was deleted successfully.']);
        exit();

    } elseif ($action === 'toggle_status') {
        $statusStmt = $pdo->prepare("SELECT status FROM products WHERE id = ?");
        $statusStmt->execute([$product_id]);
        $currentStatus = $statusStmt->fetchColumn();

        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
        $updateStmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ? AND seller_id = ?");
        $updateStmt->execute([$newStatus, $product_id, $seller_id]);

        echo json_encode([
            'success' => true, 
            'message' => 'Product status changed to ' . ucfirst($newStatus) . '.',
            'new_status' => $newStatus
        ]);
        exit();

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        exit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Seller Product Action Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
    exit();
}
