<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=oxxa_gear_database;charset=utf8mb4", 'root', '');
$stmt = $pdo->query("SHOW CREATE TABLE products");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>" . $row['Create Table'] . "</pre>";
?>
