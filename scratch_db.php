<?php
require_once __DIR__ . '/include/connection.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables) . "\n\n";
    
    if (in_array('product_colors', $tables)) {
        $stmt = $pdo->query("SELECT * FROM product_colors LIMIT 5");
        echo "Product Colors:\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo "Table product_colors does NOT exist.\n";
    }
    
    if (in_array('color_images', $tables)) {
        $stmt = $pdo->query("SELECT * FROM color_images LIMIT 5");
        echo "Color Images:\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo "Table color_images does NOT exist.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
