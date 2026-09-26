<?php
include('include/connection.php');
$stmt = $pdo->query('SHOW CREATE TABLE seller_balances');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
