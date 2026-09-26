<?php
include_once(__DIR__ . "/../../include/connection.php");

try {
    // 1. Create attributes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `attributes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL,
          `input_type` varchar(50) DEFAULT 'text',
          `default_options` json DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Create category_attributes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `category_attributes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `category_id` int(11) NOT NULL,
          `attribute_id` int(11) NOT NULL,
          `is_variant_axis` tinyint(1) DEFAULT 0,
          `is_required` tinyint(1) DEFAULT 1,
          PRIMARY KEY (`id`),
          UNIQUE KEY `cat_attr` (`category_id`, `attribute_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Create product_fixed_attributes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_fixed_attributes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `product_id` int(11) NOT NULL,
          `attribute_id` int(11) NOT NULL,
          `attribute_value` text DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `prod_attr` (`product_id`, `attribute_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 4. Update product_variants table (We will create a new one instead of modifying master_variants)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `product_dynamic_variants` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `product_id` int(11) NOT NULL,
          `sku` varchar(100) NOT NULL,
          `price` decimal(10,2) NOT NULL DEFAULT 0.00,
          `stock` int(11) NOT NULL DEFAULT 0,
          `options_json` json NOT NULL,
          `barcode` varchar(100) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 5. Add image grouping columns to product_images
    try {
        $pdo->exec("ALTER TABLE `product_images` ADD COLUMN `group_attribute` varchar(50) NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE `product_images` ADD COLUMN `group_value` varchar(100) NULL DEFAULT NULL");
    } catch(Exception $e) {}

    // Insert Default Attributes
    $attrs = [
        ['Gender', 'select', json_encode(['Men', 'Women', 'Unisex', 'Kids'])],
        ['Fabric', 'select', json_encode(['Polyester', 'Cotton', 'Dry-Fit', 'Nylon'])],
        ['Fit', 'select', json_encode(['Regular', 'Slim', 'Oversized'])],
        ['Pattern', 'text', json_encode(['Plain', 'Printed'])],
        ['Color', 'text', null],
        ['Size', 'text', null],
        ['Sleeve Type', 'select', json_encode(['Short Sleeve', 'Long Sleeve', 'Sleeveless'])],
        ['Material', 'text', json_encode(['Mesh', 'Leather', 'Cotton', 'English Willow', 'Kashmir Willow'])],
        ['Closure', 'select', json_encode(['Lace-up', 'Slip-on'])],
        ['Sole Type', 'select', json_encode(['Running', 'Training', 'Casual'])],
        ['Type', 'text', json_encode(['Dumbbell', 'Band', 'Mat'])],
        ['Weight', 'text', json_encode(['2.5KG', '5KG', '10KG', '500g', '1kg', '2kg', '1160g', '1180g', '1200g'])],
        ['Resistance', 'select', json_encode(['Light', 'Medium', 'Heavy'])],
        ['Flavor', 'text', json_encode(['Chocolate', 'Vanilla', 'Strawberry'])],
        ['Form', 'select', json_encode(['Powder', 'Capsule'])],
        ['Servings', 'text', null],
        ['Warranty', 'text', json_encode(['6 Months', '1 Year'])],
        ['Nutrition Facts', 'text', null] 
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO attributes (name, input_type, default_options) VALUES (?, ?, ?)");
    foreach ($attrs as $attr) {
        $stmt->execute($attr);
    }

    // Seed mappings for existing categories
    $catStmt = $pdo->query("SELECT id, name FROM categories");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $attrStmt = $pdo->query("SELECT id, name FROM attributes");
    $attrMap = [];
    while($row = $attrStmt->fetch(PDO::FETCH_ASSOC)){
        $attrMap[$row['name']] = $row['id'];
    }

    $mappings = [];
    foreach($categories as $cat) {
        $cName = strtoupper($cat['name']);
        
        if (strpos($cName, 'SPORTS WEAR') !== false || strpos($cName, 'SPORTSWEAR') !== false) {
            $mappings[] = [$cat['id'], $attrMap['Gender'], 0];
            $mappings[] = [$cat['id'], $attrMap['Fabric'], 0];
            $mappings[] = [$cat['id'], $attrMap['Fit'], 0];
            $mappings[] = [$cat['id'], $attrMap['Pattern'], 0];
            $mappings[] = [$cat['id'], $attrMap['Color'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Size'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Sleeve Type'], 1]; 
        }
        else if (strpos($cName, 'FOOTWEAR') !== false) {
            $mappings[] = [$cat['id'], $attrMap['Material'], 0];
            $mappings[] = [$cat['id'], $attrMap['Closure'], 0];
            $mappings[] = [$cat['id'], $attrMap['Sole Type'], 0];
            $mappings[] = [$cat['id'], $attrMap['Color'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Size'], 1]; 
        }
        else if (strpos($cName, 'FITNESS') !== false || strpos($cName, 'GYM') !== false) {
            $mappings[] = [$cat['id'], $attrMap['Type'], 0];
            $mappings[] = [$cat['id'], $attrMap['Weight'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Resistance'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Color'], 1]; 
        }
        else if (strpos($cName, 'NUTRITION') !== false) {
            $mappings[] = [$cat['id'], $attrMap['Flavor'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Weight'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Form'], 0];
            $mappings[] = [$cat['id'], $attrMap['Servings'], 0];
            $mappings[] = [$cat['id'], $attrMap['Nutrition Facts'], 0];
        }
        else if (strpos($cName, 'ACCESSORIES') !== false) {
            $mappings[] = [$cat['id'], $attrMap['Material'], 0];
            $mappings[] = [$cat['id'], $attrMap['Color'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Size'], 1]; 
        }
        else if (strpos($cName, 'EQUIPMENT') !== false) {
            $mappings[] = [$cat['id'], $attrMap['Material'], 0];
            $mappings[] = [$cat['id'], $attrMap['Warranty'], 0];
            $mappings[] = [$cat['id'], $attrMap['Size'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Weight'], 1]; 
            $mappings[] = [$cat['id'], $attrMap['Color'], 1]; 
        }
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO category_attributes (category_id, attribute_id, is_variant_axis) VALUES (?, ?, ?)");
    foreach ($mappings as $m) {
        $stmt->execute($m);
    }
    
    // Add nutrition/hot deals specific columns to products if not exist
    try {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `is_hot_deal` tinyint(1) DEFAULT 0");
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `expiry_date` date NULL DEFAULT NULL");
    } catch(Exception $e) {}

    echo "Database migrated successfully.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
