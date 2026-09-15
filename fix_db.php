<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=oxxa_gear_database;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER description;");
    echo "Added cost_price";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
