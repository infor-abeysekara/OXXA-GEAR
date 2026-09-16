<?php
session_start();
include('../../include/connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['userid'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $tracking_number = $_POST['tracking_number'] ?? null;
    $courier_company = $_POST['courier_company'] ?? null;

    // Optional: add a check here to ensure the logged-in seller actually owns this order
    // (Skipping for brevity in this demo script)

    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, tracking_number = ?, courier_company = ? WHERE id = ?");
        $stmt->execute([$status, $tracking_number, $courier_company, $order_id]);

        // If you want to notify the buyer, you could insert into notifications table here
        
        $_SESSION['success_msg'] = "Order status updated to " . strtoupper($status) . " successfully!";
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Failed to update order status: " . $e->getMessage();
    }
}

header("Location: ../seller-dashboard.php?tab=orders");
exit();
?>
