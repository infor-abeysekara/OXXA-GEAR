<?php
session_start();
require_once '../include/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'Please sign in to use your wishlist.']);
    exit;
}

$user_id = $_SESSION['userid'];
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if ($product_id > 0) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['success' => true, 'message' => 'Added to Wishlist!']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    }
} elseif ($action === 'remove') {
    // Can remove by wishlist_id or product_id
    if (isset($_POST['wishlist_id'])) {
        $wishlist_id = (int)$_POST['wishlist_id'];
        $stmt = $pdo->prepare("DELETE FROM wishlists WHERE id = ? AND user_id = ?");
        $stmt->execute([$wishlist_id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Removed from Wishlist!']);
    } elseif (isset($_POST['product_id'])) {
        $product_id = (int)$_POST['product_id'];
        $stmt = $pdo->prepare("DELETE FROM wishlists WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$product_id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Removed from Wishlist!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
