<?php
session_start();
include_once("../include/connection.php");

header('Content-Type: application/json');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$seller_id = $_SESSION['userid'];
$action = $_POST['action'] ?? '';

if ($action == 'reply') {
    $review_id = intval($_POST['review_id']);
    $reply_text = trim($_POST['reply_text']);
    
    if (empty($reply_text)) {
        echo json_encode(['success' => false, 'message' => 'Reply cannot be empty']);
        exit();
    }
    
    // Verify ownership
    $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE id = ? AND seller_id = ?");
    $checkStmt->execute([$review_id, $seller_id]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Review not found or not authorized']);
        exit();
    }
    
    // Insert reply
    $stmt = $pdo->prepare("INSERT INTO review_replies (review_id, seller_id, reply_text) VALUES (?, ?, ?)");
    if ($stmt->execute([$review_id, $seller_id, $reply_text])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }

} elseif ($action == 'report') {
    $review_id = intval($_POST['review_id']);
    $reason = trim($_POST['reason']);
    
    // Verify ownership
    $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE id = ? AND seller_id = ?");
    $checkStmt->execute([$review_id, $seller_id]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Review not found or not authorized']);
        exit();
    }
    
    // Mark review as reported
    $pdo->prepare("UPDATE reviews SET status = 'reported' WHERE id = ?")->execute([$review_id]);
    
    // Insert report
    $stmt = $pdo->prepare("INSERT INTO review_reports (review_id, reported_by, reported_by_type, reason) VALUES (?, ?, 'seller', ?)");
    if ($stmt->execute([$review_id, $seller_id, $reason])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
