<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'include/connection.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Drop old tables (Warning: this deletes existing variant and image data, as approved by user)
    $pdo->exec("DROP TABLE IF EXISTS `color_images`");
    $pdo->exec("DROP TABLE IF EXISTS `color_sizes`");
    $pdo->exec("DROP TABLE IF EXISTS `product_colors`");
    
    // We will keep product_images and product_variants for backward compatibility if needed, 
    // but the new code won't use them. Actually, let's drop them to keep schema clean as requested.
    $pdo->exec("DROP TABLE IF EXISTS `cart`"); // Foreign keys depend on variants sometimes. Wait, `cart` in SQL dump relies on `product_id`.
    $pdo->exec("DROP TABLE IF EXISTS `product_images`");
    $pdo->exec("DROP TABLE IF EXISTS `product_variants`");

    // 2. Create product_colors
    $pdo->exec("CREATE TABLE `product_colors` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `product_id` INT UNSIGNED NOT NULL,
      `color_name` VARCHAR(100) NOT NULL,
      `color_hex` VARCHAR(20) DEFAULT NULL,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Create color_images
    $pdo->exec("CREATE TABLE `color_images` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `color_id` INT UNSIGNED NOT NULL,
      `image_path` VARCHAR(255) NOT NULL,
      `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
      `sort_order` INT NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`color_id`) REFERENCES `product_colors`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Create color_sizes
    $pdo->exec("CREATE TABLE `color_sizes` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `color_id` INT UNSIGNED NOT NULL,
      `size` VARCHAR(50) NOT NULL,
      `qty` INT NOT NULL DEFAULT 0,
      `price_override` DECIMAL(10,2) DEFAULT NULL,
      `sku` VARCHAR(50) DEFAULT NULL,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`color_id`) REFERENCES `product_colors`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<h1>Migration Successful!</h1>";
} catch (Exception $e) {
    echo "<h1>Migration Failed:</h1> <p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
