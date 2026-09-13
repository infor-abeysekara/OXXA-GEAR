<?php
include_once(__DIR__ . '/connection.php');

try {
    // Add cost_price to products
    $pdo->exec("ALTER TABLE `products` ADD COLUMN `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Hidden from buyers, for profit calc' AFTER `description`");
    echo "Added cost_price column.\n";
} catch (PDOException $e) {
    echo "cost_price error: " . $e->getMessage() . "\n";
}

try {
    // Create seller_payouts table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `seller_payouts` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `order_item_id` INT UNSIGNED NOT NULL,
      `seller_id` INT UNSIGNED NOT NULL,
      `selling_price` DECIMAL(10,2) NOT NULL,
      `cost_price` DECIMAL(10,2) NOT NULL,
      `profit` DECIMAL(10,2) NOT NULL,
      `admin_commission` DECIMAL(10,2) NOT NULL,
      `seller_earning` DECIMAL(10,2) NOT NULL,
      `payout_status` ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `paid_at` TIMESTAMP NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_payout_order_item` (`order_item_id`),
      KEY `idx_payout_seller` (`seller_id`),
      CONSTRAINT `fk_payout_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_payout_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created seller_payouts table.\n";
} catch (PDOException $e) {
    echo "seller_payouts error: " . $e->getMessage() . "\n";
}
?>
