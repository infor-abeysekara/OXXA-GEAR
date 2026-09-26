<?php
include_once(__DIR__ . "/../../include/connection.php");
$stmt = $pdo->query("SELECT * FROM orders WHERE id = 35");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
