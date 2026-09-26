<?php
include('../include/connection.php');
$product = $pdo->query("SELECT id, seller_id FROM products WHERE id IN (11, 19, 20, 21)")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($product);
