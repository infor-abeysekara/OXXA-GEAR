<?php
require_once __DIR__ . '/../../include/connection.php';

try {
    // Add columns
    $pdo->exec("ALTER TABLE products ADD COLUMN is_free_shipping TINYINT(1) NOT NULL DEFAULT 0");
    $pdo->exec("ALTER TABLE products ADD COLUMN shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 300.00");
    
    // Add settings
    $pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('global_shipping_cost', '300.00')");
    $pdo->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('free_shipping_threshold', '5000.00')");
    
    echo "DB_UPDATE_SUCCESS";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "DB_UPDATE_SUCCESS (Already applied)";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
