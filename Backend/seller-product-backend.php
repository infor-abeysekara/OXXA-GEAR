<?php
session_start();
include('../include/connection.php');
include('../include/functions.php');

if (!isset($_SESSION['userid']) || $_SESSION['type'] != 'seller') {
    header('Location: ../site/login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT is_approved FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['userid']]);
$is_approved = $stmt->fetchColumn();

if (!$is_approved) {
    header('Location: ../site/business-registration.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    
    $seller_id = $_SESSION['userid'];
    $name = trim($_POST['name']);
    $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $category_id = (int)$_POST['category_id'];
    $description = trim($_POST['description']);
    $cost_price = (float)$_POST['cost_price'];
    $selling_price = (float)$_POST['selling_price'];

    // Generate product code (e.g. PRD-1052)
    $stmt = $pdo->query("SELECT MAX(id) FROM products");
    $maxId = $stmt->fetchColumn();
    $nextId = $maxId ? $maxId + 1 : 1;
    $product_code = 'PRD-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    // Generate slug
    $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)) . '-' . uniqid();

    // Sum up variant quantities for total_qty from new structure
    $total_qty = 0;
    if (isset($_POST['variant_qty']) && is_array($_POST['variant_qty'])) {
        foreach ($_POST['variant_qty'] as $qty) {
            $total_qty += (int)$qty;
        }
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert Product
        $insertProd = $pdo->prepare("INSERT INTO products (product_code, seller_id, name, slug, brand_id, category_id, description, cost_price, base_price, total_qty, is_approved, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'active')");
        $insertProd->execute([$product_code, $seller_id, $name, $slug, $brand_id, $category_id, $description, $cost_price, $selling_price, $total_qty]);
        
        $product_id = $pdo->lastInsertId();

        // 2. Insert Variants
        if (isset($_POST['variant_qty']) && is_array($_POST['variant_qty'])) {
            $insertVar = $pdo->prepare("INSERT INTO product_variants (product_id, size, color, flavor, weight, fit_type, sku, price, qty) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");
            for ($i = 0; $i < count($_POST['variant_qty']); $i++) {
                $size = trim($_POST['variant_size'][$i] ?? '');
                $color = trim($_POST['variant_color'][$i] ?? '');
                $flavor = trim($_POST['variant_flavor'][$i] ?? '');
                $weight = trim($_POST['variant_weight'][$i] ?? '');
                $fit_type = trim($_POST['variant_fit'][$i] ?? '');
                $sku = trim($_POST['variant_sku'][$i] ?? '');
                $qty = (int)($_POST['variant_qty'][$i] ?? 0);
                
                if (!empty($size) || !empty($color) || !empty($flavor) || !empty($weight) || !empty($sku)) {
                    $insertVar->execute([$product_id, $size, $color, $flavor, $weight, $fit_type, $sku, $qty]);
                }
            }
        }

        // 3. Handle Images Upload
        $uploadDir = '../assets/uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $insertImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
            
            $sort_order = 0;
            for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    $image_name = 'prod_' . $product_id . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $image_name)) {
                        $is_primary = ($i === 0) ? 1 : 0;
                        $insertImg->execute([$product_id, $image_name, $is_primary, $sort_order]);
                        $sort_order++;
                    }
                }
            }
        }

        $pdo->commit();
        header('Location: ../site/seller-dashboard.php?tab=products&success=product_added');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: ../site/seller-add-product.php?error=database');
        exit();
    }

} else {
    header('Location: ../site/seller-dashboard.php');
    exit();
}
?>
