<?php
require __DIR__ . '/include/connection.php';

$uploadDir = __DIR__ . '/assets/uploads/products/';
$files = scandir($uploadDir);

$count = 0;
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    
    // filenames are like: prod_1_6aa99d0b9d99b.avif
    if (preg_match('/^prod_(\d+)_/i', $file, $matches)) {
        $product_id = (int)$matches[1];
        
        // Check if already in db
        $stmt = $pdo->prepare("SELECT id FROM product_images WHERE image_path = ?");
        $stmt->execute([$file]);
        if (!$stmt->fetch()) {
            // Check if product exists
            $prodCheck = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $prodCheck->execute([$product_id]);
            if ($prodCheck->fetch()) {
                // Check if it's the first image to make it primary
                $primaryCheck = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? AND is_primary = 1");
                $primaryCheck->execute([$product_id]);
                $is_primary = $primaryCheck->fetch() ? 0 : 1;

                $insert = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, 0)");
                $insert->execute([$product_id, $file, $is_primary]);
                $count++;
            }
        }
    }
}

echo "Restored $count images into database.";
