<?php
include('../include/connection.php');
$seller_balances = $pdo->query("SELECT * FROM seller_balances")->fetchAll(PDO::FETCH_ASSOC);
$order_items = $pdo->query("SELECT id, order_id, product_id, seller_earning, settlement_status FROM order_items")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['balances' => $seller_balances, 'items' => $order_items]);
