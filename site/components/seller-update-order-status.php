<?php
session_start();
include('../../include/connection.php');
include('../../include/functions.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['userid'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $seller_id = $_SESSION['userid'];

    // Allowed statuses for Seller
    $allowed_statuses = ['confirmed', 'ready_to_delivery'];

    if (!in_array($status, $allowed_statuses)) {
        $_SESSION['error_msg'] = "Invalid status update requested.";
        header("Location: ../seller-dashboard.php?tab=orders");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Check if the order has items belonging to this seller
        // Also ensure current status is not already ready_to_delivery or beyond
        $checkStmt = $pdo->prepare("
            SELECT o.status, COUNT(*) as item_count FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE o.id = ? AND p.seller_id = ?
            GROUP BY o.status
        ");
        $checkStmt->execute([$order_id, $seller_id]);
        $orderData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$orderData || $orderData['item_count'] == 0) {
            throw new Exception("You do not have permission to update this order.");
        }

        $current_status = $orderData['status'];
        if (in_array($current_status, ['ready_to_delivery', 'accepted', 'shipped', 'delivered', 'cancelled', 'returned'])) {
            throw new Exception("Order is already handed over or processed. You cannot update the status anymore.");
        }

        // Update the order status
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);

        // Fetch buyer's user_id to send a notification
        $buyerStmt = $pdo->prepare("SELECT user_id, order_code FROM orders WHERE id = ?");
        $buyerStmt->execute([$order_id]);
        $buyer = $buyerStmt->fetch(PDO::FETCH_ASSOC);

        if ($buyer) {
            $buyerId = $buyer['user_id'];
            $orderCode = $buyer['order_code'];
            
            $statusDisplay = $status === 'ready_to_delivery' ? 'READY TO DELIVERY' : strtoupper($status);
            $notifMsg = "Your order #$orderCode has been updated to " . $statusDisplay;
            
            addNotification($conn, $buyerId, $notifMsg, 'info', 'Orders', 'site/order-details.php?id=' . $order_id);
        }

        $pdo->commit();
        $_SESSION['success_msg'] = "Order status updated to " . ($status === 'ready_to_delivery' ? 'Ready to Delivery' : strtoupper($status)) . " successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Failed to update order status: " . $e->getMessage();
    }
}

header("Location: ../seller-dashboard.php?tab=orders");
exit();
?>
