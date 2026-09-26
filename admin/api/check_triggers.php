<?php
include_once(__DIR__ . "/../../include/connection.php");
$stmt = $pdo->query('SHOW TRIGGERS');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
