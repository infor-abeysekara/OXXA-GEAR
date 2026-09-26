<?php
include_once(__DIR__ . "/../include/connection.php");

$pdo->beginTransaction();
try {
    // 1. Get all sellers
    $stmt = $pdo->query("SELECT id FROM users WHERE user_type = 'seller'");
    $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sellers as $s) {
        $sid = $s['id'];
        
        // Calculate return_window_hold
        $stmt2 = $pdo->prepare("
            SELECT SUM(sp.seller_earning) 
            FROM seller_payouts sp
            JOIN order_items oi ON sp.order_item_id = oi.id
            WHERE sp.seller_id = ? AND sp.payout_status = 'locked' AND oi.settlement_status = 'Locked'
        ");
        $stmt2->execute([$sid]);
        $return_window_hold = $stmt2->fetchColumn() ?: 0;
        
        // Calculate total_released
        $stmt3 = $pdo->prepare("
            SELECT SUM(sp.seller_earning) 
            FROM seller_payouts sp
            WHERE sp.seller_id = ? AND sp.payout_status IN ('pending', 'paid')
        ");
        $stmt3->execute([$sid]);
        $total_released = $stmt3->fetchColumn() ?: 0;
        
        // Calculate total_withdrawn
        $stmt4 = $pdo->prepare("
            SELECT SUM(amount) 
            FROM withdrawals 
            WHERE seller_id = ? AND status IN ('completed', 'approved')
        ");
        $stmt4->execute([$sid]);
        $total_withdrawn = $stmt4->fetchColumn() ?: 0;
        
        // Calculate pending_withdrawal
        $stmt5 = $pdo->prepare("
            SELECT SUM(amount) 
            FROM withdrawals 
            WHERE seller_id = ? AND status = 'pending'
        ");
        $stmt5->execute([$sid]);
        $pending_withdrawal = $stmt5->fetchColumn() ?: 0;
        
        // Calculate available_balance
        $available_balance = $total_released - $total_withdrawn - $pending_withdrawal;
        if ($available_balance < 0) $available_balance = 0;
        
        // Calculate total_earnings
        $stmt6 = $pdo->prepare("
            SELECT SUM(sp.seller_earning) 
            FROM seller_payouts sp
            JOIN order_items oi ON sp.order_item_id = oi.id
            WHERE sp.seller_id = ? AND oi.settlement_status IN ('Locked', 'Settled')
        ");
        $stmt6->execute([$sid]);
        $total_earnings = $stmt6->fetchColumn() ?: 0;
        
        // Upsert into seller_balances
        $check = $pdo->prepare("SELECT seller_id FROM seller_balances WHERE seller_id = ?");
        $check->execute([$sid]);
        if (!$check->fetch()) {
            $ins = $pdo->prepare("
                INSERT INTO seller_balances (seller_id, available_balance, return_window_hold, pending_withdrawal, total_withdrawn, total_earnings)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$sid, $available_balance, $return_window_hold, $pending_withdrawal, $total_withdrawn, $total_earnings]);
        } else {
            $upd = $pdo->prepare("
                UPDATE seller_balances 
                SET available_balance = ?, return_window_hold = ?, pending_withdrawal = ?, total_withdrawn = ?, total_earnings = ?
                WHERE seller_id = ?
            ");
            $upd->execute([$available_balance, $return_window_hold, $pending_withdrawal, $total_withdrawn, $total_earnings, $sid]);
        }
    }
    
    $pdo->commit();
    echo "Reconciliation complete.";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
