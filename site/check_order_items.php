<?php
include('../include/connection.php');

$columns = $pdo->query("SHOW COLUMNS FROM order_items")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($columns);
