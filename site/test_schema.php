<?php
include('../include/connection.php');

$stmt = $pdo->query("DESCRIBE product_variants");
echo "product_variants:\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt = $pdo->query("DESCRIBE product_images");
echo "\nproduct_images:\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
