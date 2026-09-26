<?php
require_once(__DIR__ . '/../include/connection.php');

echo "Starting Wallet Update Cron...\n";

try {
    $pdo->beginTransaction();

    // 1. Get orders that are DELIVERED and return window has expired
    $stmt = $pdo->prepare("SELECT id, order_code FROM orders WHERE status = 'DELIVERED' AND return_window_ends < CURDATE() AND return_requested = 0");
    $stmt->execute();
    $ordersToComplete = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $completedCount = 0;

    foreach ($ordersToComplete as $order) {
        $orderId = $order['id'];
        
        // Update order status
        $updateOrder = $pdo->prepare("UPDATE orders SET status = 'COMPLETED', completed_at = NOW() WHERE id = ?");
        $updateOrder->execute([$orderId]);

        // Get all items for this order and their seller earnings
        $itemStmt = $pdo->prepare("
            SELECT oi.id as item_id, oi.seller_earning, p.seller_id 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ? AND oi.settlement_status = 'Locked'
        ");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            // Mark item as settled
            $updateItem = $pdo->prepare("UPDATE order_items SET settlement_status = 'Settled' WHERE id = ?");
            $updateItem->execute([$item['item_id']]);

            // Update seller balance
            $earning = (float) $item['seller_earning'];
            $sellerId = $item['seller_id'];

            // Ensure seller balance exists
            $checkBal = $pdo->prepare("SELECT seller_id FROM seller_balances WHERE seller_id = ?");
            $checkBal->execute([$sellerId]);
            if (!$checkBal->fetch()) {
                $insBal = $pdo->prepare("INSERT INTO seller_balances (seller_id, available_balance, return_window_hold, pending_withdrawal, total_withdrawn, total_earnings) VALUES (?, 0, 0, 0, 0, 0)");
                $insBal->execute([$sellerId]);
            }

            // Move from locked to available
            // If the amount wasn't in locked, it might go negative, but theoretically it should be in locked when order was placed/delivered.
            // For safety, we just add to available and subtract from return_window_hold.
            $updateBal = $pdo->prepare("UPDATE seller_balances SET available_balance = available_balance + ?, return_window_hold = GREATEST(0, return_window_hold - ?) WHERE seller_id = ?");
            $updateBal->execute([$earning, $earning, $sellerId]);
        }

        $completedCount++;
    }

    $pdo->commit();
    echo "Successfully processed $completedCount orders to COMPLETED and updated wallets.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error processing wallet update: " . $e->getMessage() . "\n";
}
