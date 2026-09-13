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

if (empty($size_id)) {
    echo json_encode(['success' => false, 'message' => 'Size ID is required']);
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

// Delete the size
$deleteQuery = "DELETE FROM productsize WHERE id = ?";
$deleteStmt = $conn->prepare($deleteQuery);
$deleteStmt->bind_param("s", $size_id);

if ($deleteStmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Size deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete size']);
}
?>