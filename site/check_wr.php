<?php
include('../include/connection.php');

$columns = $pdo->query("SHOW COLUMNS FROM withdrawal_requests")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($columns);
