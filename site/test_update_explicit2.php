<?php
include('../include/connection.php');
$rows = $pdo->query("SELECT * FROM seller_balances")->fetchAll(PDO::FETCH_ASSOC);
echo "Before: " . json_encode($rows) . "<br>";

$updBal = $pdo->prepare("UPDATE seller_balances SET return_window_hold = 123.45 WHERE seller_id = 6");
$updBal->execute();
echo "Affected rows: " . $updBal->rowCount() . "<br>";

$rows2 = $pdo->query("SELECT * FROM seller_balances")->fetchAll(PDO::FETCH_ASSOC);
echo "After: " . json_encode($rows2);
