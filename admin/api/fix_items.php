<?php
include_once(__DIR__ . "/../../include/connection.php");
$stmt = $pdo->query("
    SELECT o.id as item_id, ord.id as order_id, ord.order_code, p.seller_id 
    FROM order_items o
    JOIN products p ON o.product_id = p.id
    JOIN orders ord ON o.order_id = ord.id
    WHERE ord.status IN ('DELIVERED', 'COMPLETED') AND o.settlement_status = 'Pending'
");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($items as $item) {
    $earning = $item['seller_earning'] ?? 0;
    if ($earning == 0) {
        // We need to fetch earning if it wasn't in the query
        $iStmt = $pdo->prepare("SELECT seller_earning FROM order_items WHERE id = ?");
        $iStmt->execute([$item['item_id']]);
        $earning = $iStmt->fetchColumn() ?: 0;
    }
    
    $updateStmt = $pdo->prepare("UPDATE order_items SET settlement_status = 'Locked' WHERE id = ?");
    $updateStmt->execute([$item['item_id']]);
    
    $sid = $item['seller_id'];
    $check = $pdo->prepare("SELECT seller_id FROM seller_balances WHERE seller_id = ?");
    $check->execute([$sid]);
    if (!$check->fetch()) {
        $ins = $pdo->prepare("INSERT INTO seller_balances (seller_id, available_balance, return_window_hold, pending_withdrawal, total_withdrawn, total_earnings) VALUES (?, 0, ?, 0, 0, ?)");
        $ins->execute([$sid, $earning, $earning]);
    } else {
        $upd = $pdo->prepare("UPDATE seller_balances SET return_window_hold = return_window_hold + ?, total_earnings = total_earnings + ? WHERE seller_id = ?");
        $upd->execute([$earning, $earning, $sid]);
    }

    echo "Fixed item {$item['item_id']} for order {$item['order_code']}\n";
}
echo "Done.";
