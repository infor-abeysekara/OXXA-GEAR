<?php
require __DIR__ . '/include/connection.php';

$query = "SELECT p.*, 
                 COALESCE((SELECT MIN(cs.selling_price) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.qty > 0 AND cs.selling_price > 0), p.base_price) as lowest_price,
                 (SELECT SUM(cs.qty) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id) as var_qty,
                 (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as image,
                 (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1 OFFSET 1) as hover_image,
                 (SELECT name FROM brands b WHERE b.id = p.brand_id) as brand_name,
                 (SELECT business_name FROM seller_profiles sp WHERE sp.user_id = p.seller_id) as seller_name,
                 (SELECT logo_path FROM seller_profiles sp WHERE sp.user_id = p.seller_id) as seller_logo
          FROM products p 
          GROUP BY p.id
          ORDER BY p.created_at DESC
          LIMIT 10 OFFSET 0";

$stmt = $pdo->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($products);
echo "</pre>";
