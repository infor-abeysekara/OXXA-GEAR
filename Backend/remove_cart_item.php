<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['userid'];
$item_id = (int)($_POST['item_id'] ?? 0);

// Validate inputs
if ($item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}

try {
    // Remove item from cart
    $query = "DELETE FROM cart WHERE Id = ? AND Userid = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $item_id, $user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $cartCount = getCartCount($conn, $user_id);
            echo json_encode([
                'success' => true,
                'cart_count' => $cartCount,
                'message' => 'Item removed successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>