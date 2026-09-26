<?php
// cron_release_escrow.php
// Runs daily at 00:00 via Cron
require_once('include/connection.php');

try {
    $pdo->beginTransaction();

    // Find all DELIVERED orders where return window has passed and no return was requested
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE status = 'DELIVERED' 
        AND return_window_ends IS NOT NULL 
        AND return_window_ends < NOW() 
        AND return_requested = 0
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $completedCount = 0;

    foreach ($orders as $order) {
        $order_id = $order['id'];
        
        // Find order items to get seller_earning and seller_id
        $itemsStmt = $pdo->prepare("
            SELECT oi.seller_earning, oi.quantity, p.seller_id 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$order_id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $seller_earnings = [];
        foreach ($items as $item) {
            $seller_id = $item['seller_id'];
            $earning = $item['seller_earning'] * $item['quantity'];
            
            if (!isset($seller_earnings[$seller_id])) {
                $seller_earnings[$seller_id] = 0;
            }
            $seller_earnings[$seller_id] += $earning;
        }
        
        // Update seller balances
        foreach ($seller_earnings as $seller_id => $net_amount) {
            // Check if seller_balance exists
            $checkBalanceStmt = $pdo->prepare("SELECT seller_id FROM seller_balances WHERE seller_id = ?");
            $checkBalanceStmt->execute([$seller_id]);
            if (!$checkBalanceStmt->fetchColumn()) {
                $pdo->prepare("INSERT INTO seller_balances (seller_id) VALUES (?)")->execute([$seller_id]);
            }
            
            // Move from return_window_hold to available_balance
            $updateBalanceStmt = $pdo->prepare("
                UPDATE seller_balances 
                SET return_window_hold = return_window_hold - ?,
                    available_balance = available_balance + ?
                WHERE seller_id = ?
            ");
            $updateBalanceStmt->execute([$net_amount, $net_amount, $seller_id]);
            
            // (Mock) Notify seller
            // mail("seller@example.com", "Funds Available", "Rs.{$net_amount} is now available to withdraw for Order {$order['order_code']}");
        }

        // Mark order as completed
        $updateOrderStmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'COMPLETED', completed_at = NOW() 
            WHERE id = ?
        ");
        $updateOrderStmt->execute([$order_id]);
        
        $completedCount++;
    }

    $pdo->commit();
    echo "Cron Executed Successfully. Released escrow for $completedCount orders.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error executing cron: " . $e->getMessage() . "\n";
}
