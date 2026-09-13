<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

header('Content-Type: application/json');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$size_id = sanitizeInput($_POST['size_id'] ?? '');
$size = sanitizeInput($_POST['size'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$qty = intval($_POST['qty'] ?? 0);

if (empty($size_id) || empty($size) || $price <= 0 || $qty < 0) {
    echo json_encode(['success' => false, 'message' => 'All fields are required and must be valid']);
    exit;
}

// Verify the size belongs to a product owned by this seller
$verifyQuery = "SELECT ps.*, p.user_id 
                FROM productsize ps 
                JOIN production p ON ps.pid = p.pid 
                WHERE ps.id = ? AND p.user_id = ?";
$verifyStmt = $conn->prepare($verifyQuery);
$verifyStmt->bind_param("ss", $size_id, $_SESSION['userid']);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Size not found or unauthorized']);
    exit;
}

// Update the size
$updateQuery = "UPDATE productsize SET size = ?, price = ?, qty = ? WHERE id = ?";
$updateStmt = $conn->prepare($updateQuery);
$updateStmt->bind_param("sdis", $size, $price, $qty, $size_id);

if ($updateStmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Size updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update size']);
}
?>