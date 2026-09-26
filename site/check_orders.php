<?php
include('../include/connection.php');

$columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($columns);
