<?php
include_once(__DIR__ . "/../../include/connection.php");
$stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
