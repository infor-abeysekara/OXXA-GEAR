<?php
include('../include/connection.php');
$stmt = $pdo->query("DESCRIBE seller_wallets");
var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("DESCRIBE orders");
var_dump($stmt2->fetchAll(PDO::FETCH_ASSOC));
$stmt3 = $pdo->query("DESCRIBE order_items");
var_dump($stmt3->fetchAll(PDO::FETCH_ASSOC));
?>
