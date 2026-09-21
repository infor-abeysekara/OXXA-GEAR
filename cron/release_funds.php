<?php
/**
 * Cron Job to release locked funds to sellers after 14 days of delivery.
 * Should be run daily via cPanel or server cron.
 */

// If running from command line, use absolute path. If via web, use relative.
$includePath = dirname(__DIR__) . '/include/connection.php';
$functionsPath = dirname(__DIR__) . '/include/functions.php';

if (!file_exists($includePath)) {
    die("Connection file not found at: " . $includePath . "\n");
}

require_once $includePath;
require_once $functionsPath;

try {
    $pdo->beginTransaction();

    // Find all 'Locked' order items where the order was delivered 14+ days ago
    $query = "
        SELECT 
            oi.id as order_item_id,
            oi.seller_earning,
            oi.order_id,
            p.seller_id,
            o.order_code
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.settlement_status = 'Locked'
        AND o.status IN ('delivered', 'completed')
        AND o.delivered_at IS NOT NULL
        AND o.delivered_at <= DATE_SUB(NOW(), INTERVAL 14 DAY)
    ";

    $stmt = $pdo->query($query);
    $itemsToRelease = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($itemsToRelease)) {
        $pdo->commit();
        echo "No funds to release today.\n";
        exit;
    }

    $processedSellers = []; // To keep track of total amount released per seller for notifications

    foreach ($itemsToRelease as $item) {
        $seller_id = $item['seller_id'];
        $amount = $item['seller_earning'];

        // 1. Update order item status to Settled
        $updateItem = $pdo->prepare("UPDATE order_items SET settlement_status = 'Settled' WHERE id = ?");
        $updateItem->execute([$item['order_item_id']]);

        // 2. Update seller_payouts status to pending
        $updatePayout = $pdo->prepare("UPDATE seller_payouts SET payout_status = 'pending' WHERE order_item_id = ?");
        $updatePayout->execute([$item['order_item_id']]);

        // 3. Move funds from locked to pending
        $updateWallet = $pdo->prepare("
            UPDATE seller_wallets 
            SET locked_balance = GREATEST(0, locked_balance - ?),
                pending_balance = pending_balance + ?
            WHERE seller_id = ?
        ");
        $updateWallet->execute([$amount, $amount, $seller_id]);

        // Aggregate amounts for notification
        if (!isset($processedSellers[$seller_id])) {
            $processedSellers[$seller_id] = 0;
        }
        $processedSellers[$seller_id] += $amount;
    }

    // 3. Send notifications to sellers
    foreach ($processedSellers as $seller_id => $totalAmount) {
        $msg = "Rs. " . number_format($totalAmount, 2) . " has been successfully released from your locked balance and is now available for withdrawal (14-day hold completed).";
        addNotification($conn, $seller_id, $msg, 'success', 'Payouts', 'site/seller-dashboard.php?tab=earnings');
    }

    $pdo->commit();
    echo "Successfully released funds for " . count($itemsToRelease) . " order items.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error releasing funds: " . $e->getMessage() . "\n";
}
