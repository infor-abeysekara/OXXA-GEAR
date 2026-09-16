<?php
include('../include/connection.php');

// 1. Modify orders table
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(100) NULL AFTER status");
} catch(Exception $e) {}

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN courier_company VARCHAR(100) NULL AFTER tracking_number");
} catch(Exception $e) {}

// 2. Create reviews table
$pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(150),
    comment TEXT,
    fit_feedback ENUM('Runs Small', 'True to Size', 'Runs Large'),
    is_anonymous TINYINT(1) DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_reply TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order_product (order_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 3. Create review_images table
$pdo->exec("CREATE TABLE IF NOT EXISTS review_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

echo "Migration successful!";
?>
