<?php
include('../include/header.php');

$stmt = $pdo->query('SELECT p.id as p_id, p.name, pi.* FROM products p LEFT JOIN product_images pi ON p.id = pi.product_id ORDER BY p.id DESC LIMIT 10');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($rows);
echo "</pre>";
