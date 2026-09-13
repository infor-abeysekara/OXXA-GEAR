<?php
session_start();
include_once("../include/connection.php");
include_once("../include/functions.php");

header('Content-Type: application/json');

// Check if user is logged in and is a seller
if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$order_id = sanitizeInput($input['order_id'] ?? '');
$seller_id = $_SESSION['userid'];

if (empty($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Check if order exists and belongs to this seller
    $checkQuery = "SELECT o.*, u.firstname, u.lastname, u.email 
                   FROM ordertable o
                   JOIN users u ON o.user_id = u.user_id
                   WHERE o.orderid = ? AND o.seller_id = ? AND o.status = 'pending'";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ss", $order_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Order not found or already processed');
    }
    
    $order = $result->fetch_assoc();
    
    // Update order status to confirmed
    $updateQuery = "UPDATE ordertable SET status = 'confirmed', confirmed_at = NOW() WHERE orderid = ? AND seller_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ss", $order_id, $seller_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to confirm order');
    }
    
    // Create notification message for buyer
    $payment_method = $order['payment_method'];
    if ($payment_method === 'COD') {
        $message = "We have accepted your order #{$order_id}. You will receive it within 7-14 days. Then you can pay and collect it.";
    } else {
        $message = "We have accepted your order #{$order_id}. You will receive it within 7-14 days.";
    }
    
    // Add notification for buyer
    addNotification($conn, $order['user_id'], $message, 'order');
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order confirmed successfully',
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>