<?php
/**
 * OXXA GEAR - Hot Deals Auto-Expiry Cron Script
 * 
 * Recommended execution: Daily at 00:00:00 (Midnight)
 * Example cron: 0 0 * * * php c:\wamp64\www\OXXA GEAR\cron\expire_hot_deals.php
 */

require_once __DIR__ . '/../include/connection.php';
require_once __DIR__ . '/../include/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Running Hot Deals Expiration & Stock Sync...\n";

// 1. Expire deals where hot_deal_expiry has passed
$expire_sql = "
    UPDATE products 
    SET is_hot_deal = 0, hot_deal_status = 'expired' 
    WHERE is_hot_deal = 1 
      AND hot_deal_expiry IS NOT NULL 
      AND hot_deal_expiry < NOW()
";
$expire_result = mysqli_query($conn, $expire_sql);
$expired_count = mysqli_affected_rows($conn);
echo "Expired by date: {$expired_count} products.\n";

// 2. Deactivate hot deals where total inventory is 0
$stock_sql = "
    UPDATE products p
    SET p.is_hot_deal = 0, p.hot_deal_status = 'expired'
    WHERE p.is_hot_deal = 1 
      AND (
          SELECT COALESCE(SUM(cs.qty), 0) 
          FROM color_sizes cs 
          JOIN product_colors pc ON cs.color_id = pc.id 
          WHERE pc.product_id = p.id
      ) <= 0
";
$stock_result = mysqli_query($conn, $stock_sql);
$out_of_stock_count = mysqli_affected_rows($conn);
echo "Deactivated due to zero stock: {$out_of_stock_count} products.\n";

// 3. Optional: Notify sellers whose deals expired
$expired_deals_query = "
    SELECT p.id, p.name, p.seller_id, u.id as user_id
    FROM products p
    JOIN users u ON p.seller_id = u.id
    WHERE p.hot_deal_status = 'expired' 
      AND p.updated_at >= NOW() - INTERVAL 1 HOUR
";
$exp_deals_res = mysqli_query($conn, $expired_deals_query);
if ($exp_deals_res) {
    while ($p_row = mysqli_fetch_assoc($exp_deals_res)) {
        addNotification(
            $conn, 
            $p_row['user_id'], 
            "Hot Deal Expired - Your promotional deal for {$p_row['name']} has concluded.", 
            'info', 
            'HotDeals', 
            'site/seller-edit-product.php?id=' . $p_row['id']
        );
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Completed successfully. Total expired: " . ($expired_count + $out_of_stock_count) . "\n";
