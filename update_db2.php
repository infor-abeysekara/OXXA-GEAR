<?php
require 'include/connection.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_images` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `product_id` INT UNSIGNED NOT NULL,
      `image_path` VARCHAR(255) NOT NULL,
      `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
      `sort_order` INT NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Created product_images table successfully.\n";
} catch (Exception $e) {
    echo "Error on product_images: " . $e->getMessage() . "\n";
}
?>
