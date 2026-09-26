<?php
require_once __DIR__ . '/include/connection.php';

try {
    $stmt = $pdo->query("SHOW CREATE TABLE product_colors");
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
    
    $stmt2 = $pdo->query("SHOW CREATE TABLE color_sizes");
    print_r($stmt2->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
