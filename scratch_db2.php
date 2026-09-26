<?php
require_once __DIR__ . '/include/connection.php';

try {
    $stmt = $pdo->query("SELECT * FROM product_dynamic_variants LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
