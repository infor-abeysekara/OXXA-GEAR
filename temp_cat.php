<?php
require 'include/connection.php';
$stmt = $pdo->query("SELECT id, name FROM categories");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data, JSON_PRETTY_PRINT);
?>
