<?php
include('../include/connection.php');
$schema = $pdo->query("SHOW COLUMNS FROM withdrawals")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($schema);
