<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

header('Content-Type: application/json');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['userid'];
$orderId = sanitizeInput($_POST['order_id'] ?? '');
$status = sanitizeInput($_POST['status'] ?? '');

// Validate input
if (empty($orderId) || !in_array($status, ['confirmed', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // First, verify that this seller owns products in this order
    $verifyQuery = "SELECT ot.*, p.pname, p.user_id as seller_id, u.firstname, u.lastname, u.email
                    FROM ordertable ot 
                    JOIN production p ON ot.pid = p.pid 
                    JOIN users u ON ot.user_id = u.user_id
                    WHERE ot.orderid = ? AND p.user_id = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param("ss", $orderId, $user_id);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    
    if ($verifyResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
        exit;
    }
    
    $orderData = $verifyResult->fetch_assoc();
    
    // Update order status
    $updateQuery = "UPDATE ordertable SET status = ? WHERE orderid = ? AND pid IN (SELECT pid FROM production WHERE user_id = ?)";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("sss", $status, $orderId, $user_id);
    
    if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
        // Add notification for buyer
        $message = $status === 'confirmed' ? 
            "Your order #{$orderId} for {$orderData['pname']} has been confirmed by the seller." :
            "Your order #{$orderId} for {$orderData['pname']} has been rejected by the seller.";
        
        addNotification($conn, $orderData['user_id'], $message, 'order');
        
        echo json_encode([
            'success' => true, 
            'message' => 'Order status updated successfully',
            'new_status' => $status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update order status or no changes made']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>