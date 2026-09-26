<?php
include_once(__DIR__ . "/../../include/connection.php");
$stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
