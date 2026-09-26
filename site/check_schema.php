<?php
include('../include/connection.php');
$schema = $pdo->query("SHOW COLUMNS FROM order_items")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($schema);
