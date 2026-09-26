<?php
include('../include/connection.php');
$order_items = $pdo->query("SELECT id, quantity, seller_earning, total_price FROM order_items WHERE id = 49")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($order_items);
