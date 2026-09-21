<?php
include('../include/connection.php');

$merchant_id         = $_POST['merchant_id'] ?? '';
$order_id            = $_POST['order_id'] ?? '';
$payhere_amount      = $_POST['payhere_amount'] ?? '';
$payhere_currency    = $_POST['payhere_currency'] ?? '';
$status_code         = $_POST['status_code'] ?? '';
$md5sig              = $_POST['md5sig'] ?? '';

$merchant_secret = "MjcyNjcyODQ4OTI1MzQ3NjI1NzgzMjc4NzIwNTI2NDI2ODc3MjQwOQ=="; // Replace with your Merchant Secret

$local_md5sig = strtoupper(
    md5(
        $merchant_id . 
        $order_id . 
        $payhere_amount . 
        $payhere_currency . 
        $status_code . 
        strtoupper(md5($merchant_secret))
    )
);

if (($local_md5sig === $md5sig) && ($status_code == 2) ) {
    // Payment is verified and successful
    
    // Update order payment status and set order status to pending so sellers can process it
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = 'pending' WHERE order_code = ?");
    $stmt->execute([$order_id]);

    // Update related seller payouts to 'pending' instead of 'locked'
    $spStmt = $pdo->prepare("
        UPDATE seller_payouts sp 
        JOIN order_items oi ON sp.order_item_id = oi.id 
        JOIN orders o ON oi.order_id = o.id 
        SET sp.payout_status = 'pending' 
        WHERE o.order_code = ?
    ");
    $spStmt->execute([$order_id]);

    // Get order info to deduct stock and clear cart
    $orderQuery = $pdo->prepare("SELECT id, user_id FROM orders WHERE order_code = ?");
    $orderQuery->execute([$order_id]);
    if ($orderData = $orderQuery->fetch(PDO::FETCH_ASSOC)) {
        // Clear cart
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$orderData['user_id']]);

        // Deduct stock
        $itemsQuery = $pdo->prepare("SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?");
        $itemsQuery->execute([$orderData['id']]);
        $items = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            if ($item['variant_id']) {
                $pdo->prepare("UPDATE color_sizes SET qty = qty - ? WHERE id = ?")->execute([$item['quantity'], $item['variant_id']]);
            }
            $pdo->prepare("UPDATE products SET total_qty = total_qty - ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
        }
    }
}

// PayHere expects a 200 OK response
http_response_code(200);
?>
