<?php
include_once(__DIR__ . "/../include/connection.php");

$pdo->beginTransaction();
$log = [];
try {
    $stmt = $pdo->query("
        SELECT p.seller_id, SUM(o.seller_earning) as locked_total
        FROM order_items o
        JOIN products p ON o.product_id = p.id
        WHERE o.settlement_status = 'Locked'
        GROUP BY p.seller_id
    ");
    $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $log['sellers'] = $sellers;

    foreach ($sellers as $s) {
        $sid = $s['seller_id'];
        $locked_total = $s['locked_total'] ?: 0;

        $check = $pdo->prepare("SELECT seller_id FROM seller_balances WHERE seller_id = ?");
        $check->execute([$sid]);
        if (!$check->fetch()) {
            $log['actions'][] = "Inserting for $sid";
            $ins = $pdo->prepare("INSERT INTO seller_balances (seller_id, available_balance, return_window_hold, pending_withdrawal, total_withdrawn, total_earnings) VALUES (?, 0, ?, 0, 0, ?)");
            $ins->execute([$sid, $locked_total, $locked_total]);
        } else {
            $log['actions'][] = "Updating for $sid with $locked_total";
            $upd = $pdo->prepare("
                UPDATE seller_balances 
                SET return_window_hold = ?,
                    total_earnings = GREATEST(total_earnings, ? + available_balance + pending_withdrawal + total_withdrawn)
                WHERE seller_id = ?
            ");
            $upd->execute([$locked_total, $locked_total, $sid]);
        }
    }
    
    $pdo->commit();
    $log['status'] = "Sync successful.";
} catch (Exception $e) {
    $pdo->rollBack();
    $log['status'] = "Error: " . $e->getMessage();
}

file_put_contents('sync_log.json', json_encode($log));
echo "Done";
