<?php
session_start();
include_once("../include/connection.php");

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$user_id = $_SESSION['userid'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$fit_feedback = isset($_POST['fit_feedback']) ? trim($_POST['fit_feedback']) : '';
$is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

if ($product_id <= 0 || $rating < 1 || $rating > 5 || empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Check if already reviewed
$revCheckStmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
$revCheckStmt->execute([$user_id, $product_id]);
if ($revCheckStmt->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this product.']);
    exit();
}

// Get the seller_id for the product
$sellerStmt = $pdo->prepare("SELECT seller_id FROM products WHERE id = ?");
$sellerStmt->execute([$product_id]);
$seller_id = $sellerStmt->fetchColumn();

// If order_id is provided, verify it belongs to user
if ($order_id > 0) {
    $orderCheckStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'delivered'");
    $orderCheckStmt->execute([$order_id, $user_id]);
    if (!$orderCheckStmt->fetchColumn()) {
        $order_id = 0; // Invalid order or not delivered yet
    }
} else {
    // Attempt to find a valid delivered order if order_id wasn't passed properly
    $findOrderStmt = $pdo->prepare("SELECT o.id FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered' LIMIT 1");
    $findOrderStmt->execute([$user_id, $product_id]);
    $order_id = $findOrderStmt->fetchColumn() ?: 0;
}

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'You can only review products you have purchased and received.']);
    exit();
}

// Insert Review
$insertStmt = $pdo->prepare("
    INSERT INTO reviews (user_id, product_id, order_id, seller_id, rating, title, comment, fit_feedback, is_anonymous, is_verified_purchase, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'active')
");

if ($insertStmt->execute([$user_id, $product_id, $order_id, $seller_id, $rating, $title, $comment, $fit_feedback, $is_anonymous])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save review.']);
}
?>
