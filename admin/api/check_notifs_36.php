<?php
include_once(__DIR__ . "/../../include/connection.php");
$stmt = $pdo->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
