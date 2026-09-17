<?php
require 'include/connection.php';

try {
    $pdo->exec("ALTER TABLE `product_colors` ADD COLUMN `thumbnail_path` VARCHAR(255) DEFAULT NULL AFTER `color_hex`");
    echo "Added thumbnail_path to product_colors.\n";
} catch (Exception $e) {
    echo "Error on product_colors: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE `color_sizes` ADD COLUMN `cost_price` DECIMAL(10,2) DEFAULT NULL AFTER `qty`");
    $pdo->exec("ALTER TABLE `color_sizes` ADD COLUMN `selling_price` DECIMAL(10,2) DEFAULT NULL AFTER `cost_price`");
    $pdo->exec("ALTER TABLE `color_sizes` DROP COLUMN `price_override`");
    echo "Updated color_sizes with cost_price and selling_price.\n";
} catch (Exception $e) {
    echo "Error on color_sizes: " . $e->getMessage() . "\n";
}
?>
