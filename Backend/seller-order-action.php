<?php
session_start();
include('../include/connection.php');
header('Content-Type: application/json');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? '';
    $new_status = $_POST['new_status'] ?? '';
    $cancellation_reason = $_POST['cancellation_reason'] ?? null;
    $seller_id = $_SESSION['userid'];

    if (empty($order_id) || empty($new_status)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    try {
        // Verify seller owns this order
        $verifyStmt = $pdo->prepare("
            SELECT o.*, ord.payment_method 
            FROM order_items o 
            JOIN orders ord ON o.order_id = ord.id 
            JOIN products p ON o.product_id = p.id 
            WHERE ord.order_code = ? AND p.seller_id = ?
            LIMIT 1
        ");
        $verifyStmt->execute([$order_id, $seller_id]);
        $orderItem = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        if (!$orderItem) {
            echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
            exit;
        }

        if ($new_status === 'cancelled') {
            if (empty(trim($cancellation_reason))) {
                echo json_encode(['success' => false, 'message' => 'Cancellation reason is required']);
                exit;
            }
            
            // If payment was CARD, set payment status to REFUND_PENDING
            $payment_status_update = (strtoupper($orderItem['payment_method']) === 'CARD' || strtoupper($orderItem['payment_method']) === 'PAYHERE') 
                ? "payment_status = 'REFUND_PENDING'," 
                : "";
            
            $updateStmt = $pdo->prepare("UPDATE orders SET status = 'CANCELLED', $payment_status_update cancellation_reason = ? WHERE order_code = ?");
            $updateStmt->execute([$cancellation_reason, $order_id]);
            
            echo json_encode(['success' => true, 'message' => 'Order rejected successfully.']);
            
        } elseif ($new_status === 'accepted') {
            $updateStmt = $pdo->prepare("UPDATE orders SET status = 'ACCEPTED', payment_status = 'paid' WHERE order_code = ?");
            $updateStmt->execute([$order_id]);
            
            echo json_encode(['success' => true, 'message' => 'Order accepted successfully.']);
            
        } elseif ($new_status === 'handover_to_center') {
            $updateStmt = $pdo->prepare("UPDATE orders SET status = 'HANDOVER_TO_CENTER' WHERE order_code = ?");
            $updateStmt->execute([$order_id]);
            
            echo json_encode(['success' => true, 'message' => 'Order marked as Handed over to Collecting Center.']);
            
        } elseif ($new_status === 'shipped') {
            $tracking = $_POST['tracking_number'] ?? null;
            $courier = $_POST['courier_company'] ?? null;
            $updateStmt = $pdo->prepare("UPDATE orders SET status = 'SHIPPED', tracking_number = ?, courier_company = ? WHERE order_code = ?");
            $updateStmt->execute([$tracking, $courier, $order_id]);
            
            echo json_encode(['success' => true, 'message' => 'Order marked as Shipped.']);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid status option']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
