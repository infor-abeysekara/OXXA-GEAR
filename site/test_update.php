<?php
include('../include/connection.php');

$pdo->beginTransaction();
try {
    $seller_id = 6;
    $earning = "100.50";
    
    $checkBal = $pdo->prepare("SELECT seller_id FROM seller_balances WHERE seller_id = ?");
    $checkBal->execute([$seller_id]);
    if (!$checkBal->fetch()) {
        $insBal = $pdo->prepare("INSERT INTO seller_balances (seller_id, available_balance, return_window_hold, pending_withdrawal, total_withdrawn, total_earnings) VALUES (?, 0, ?, 0, 0, ?)");
        $insBal->execute([$seller_id, $earning, $earning]);
    } else {
        $updBal = $pdo->prepare("UPDATE seller_balances SET return_window_hold = return_window_hold + ?, total_earnings = total_earnings + ? WHERE seller_id = ?");
        $updBal->execute([$earning, $earning, $seller_id]);
    }
    $pdo->commit();
    echo "Success!";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
