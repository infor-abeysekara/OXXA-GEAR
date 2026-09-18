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
    if (isset($_POST['colors']) && is_array($_POST['colors'])) {
        foreach ($_POST['colors'] as $cId => $colorData) {
            if (isset($colorData['sizes']) && is_array($colorData['sizes'])) {
                foreach ($colorData['sizes'] as $sizeData) {
                    if (isset($sizeData['active']) && $sizeData['active'] == '1') {
                        $total_qty += (int)($sizeData['qty'] ?? 0);
                    }
                }
            }
        }
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert Product
        $insertProd = $pdo->prepare("INSERT INTO products (product_code, seller_id, name, slug, brand_id, category_id, description, cost_price, base_price, total_qty, is_approved, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'active')");
        $insertProd->execute([$product_code, $seller_id, $name, $slug, $brand_id, $category_id, $description, $cost_price, $selling_price, $total_qty]);
        
        $product_id = $pdo->lastInsertId();

        // 2. Insert Colors, Images, and Sizes
        if (isset($_POST['colors']) && is_array($_POST['colors'])) {
            $insertColor = $pdo->prepare("INSERT INTO product_colors (product_id, color_name, color_hex, thumbnail_path) VALUES (?, ?, ?, ?)");
            $insertSize = $pdo->prepare("INSERT INTO color_sizes (color_id, size, qty, cost_price, selling_price, sku) VALUES (?, ?, ?, ?, ?, ?)");
            $insertProdImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
            
            $uploadDir = '../assets/uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($_POST['colors'] as $cId => $colorData) {
                $colorName = trim($colorData['name'] ?? '');
                $colorHex = trim($colorData['hex'] ?? '');
                if (empty($colorName)) continue;

                // Handle Thumbnail Upload
                $thumbnail_path = null;
                $thumbKey = "thumbnail_$cId";
                if (isset($_FILES[$thumbKey]) && $_FILES[$thumbKey]['error'] === UPLOAD_ERR_OK) {
                    if ($_FILES[$thumbKey]['size'] <= 1048576) {
                        $ext = strtolower(pathinfo($_FILES[$thumbKey]['name'], PATHINFO_EXTENSION));
                        $thumb_name = 'thumb_' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($_FILES[$thumbKey]['tmp_name'], $uploadDir . $thumb_name)) {
                            $thumbnail_path = $thumb_name;
                        }
                    }
                }

                $insertColor->execute([$product_id, $colorName, $colorHex, $thumbnail_path]);
                $color_id = $pdo->lastInsertId();

                // Handle Sizes for this color
                if (isset($colorData['sizes']) && is_array($colorData['sizes'])) {
                    foreach ($colorData['sizes'] as $sizeName => $sizeData) {
                        if (isset($sizeData['active']) && $sizeData['active'] == '1') {
                            $qty = (int)($sizeData['qty'] ?? 0);
                            $cost = !empty($sizeData['cost']) ? (float)$sizeData['cost'] : null;
                            $selling = !empty($sizeData['selling']) ? (float)$sizeData['selling'] : null;
                            $sku = trim($sizeData['sku'] ?? '');
                            $insertSize->execute([$color_id, $sizeName, $qty, $cost, $selling, $sku]);
                        }
                    }
                }

            }
        }

        // 3. Handle Global Product Images
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $sort_order = 0;
            for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
                if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                    if ($_FILES['product_images']['size'][$i] > 1048576) continue; // Skip files > 1MB
                    
                    $ext = strtolower(pathinfo($_FILES['product_images']['name'][$i], PATHINFO_EXTENSION));
                    $image_name = 'prod_' . $product_id . '_' . uniqid() . '.' . $ext;
                    
                    if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $uploadDir . $image_name)) {
                        $is_primary = ($sort_order === 0) ? 1 : 0;
                        $insertProdImg->execute([$product_id, $image_name, $is_primary, $sort_order]);
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
        error_log("Product Add Error: " . $e->getMessage());
        header('Location: ../site/seller-add-product.php?error=database');
        exit();
    }

} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product_btn'])) {
    
    $seller_id = $_SESSION['userid'];
    $product_id = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $category_id = (int)$_POST['category_id'];
    $description = trim($_POST['description']);
    $cost_price = (float)$_POST['cost_price'];
    $selling_price = (float)$_POST['selling_price'];

    // Verify ownership
    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
    $checkStmt->execute([$product_id, $seller_id]);
    if (!$checkStmt->fetch()) {
        header('Location: ../site/seller-dashboard.php?error=unauthorized');
        exit();
    }

    // Sum up variant quantities
    $total_qty = 0;
    if (isset($_POST['variant_qty']) && is_array($_POST['variant_qty']) && count($_POST['variant_qty']) > 0) {
        foreach ($_POST['variant_qty'] as $qty) {
            $total_qty += (int)$qty;
        }
    } else {
        $total_qty = isset($_POST['total_qty']) ? (int)$_POST['total_qty'] : 0;
    }

    try {
        $pdo->beginTransaction();

        // 1. Update Product
        $updateProd = $pdo->prepare("UPDATE products SET name = ?, brand_id = ?, category_id = ?, description = ?, cost_price = ?, base_price = ?, total_qty = ?, is_approved = 0 WHERE id = ?");
        $updateProd->execute([$name, $brand_id, $category_id, $description, $cost_price, $selling_price, $total_qty, $product_id]);

        // 2. Update Variants (Delete old, Insert new)
        $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$product_id]);
        
        if (isset($_POST['variant_qty']) && is_array($_POST['variant_qty'])) {
            $insertVar = $pdo->prepare("INSERT INTO product_variants (product_id, size, color, flavor, weight, fit_type, sku, price, cost_price, qty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            for ($i = 0; $i < count($_POST['variant_qty']); $i++) {
                $size = trim($_POST['variant_size'][$i] ?? '');
                $color = trim($_POST['variant_color'][$i] ?? '');
                $flavor = trim($_POST['variant_flavor'][$i] ?? '');
                $weight = trim($_POST['variant_weight'][$i] ?? '');
                $fit_type = trim($_POST['variant_fit'][$i] ?? '');
                $sku = trim($_POST['variant_sku'][$i] ?? '');
                $var_qty = (int)($_POST['variant_qty'][$i] ?? 0);
                $var_cost_price = (float)($_POST['variant_cost_price'][$i] ?? 0);
                $var_price = (float)($_POST['variant_price'][$i] ?? 0);
                
                if (!empty($size) || !empty($color) || !empty($flavor) || !empty($weight) || !empty($sku)) {
                    $insertVar->execute([$product_id, $size, $color, $flavor, $weight, $fit_type, $sku, $var_price, $var_cost_price, $var_qty]);
                }
            }
        }

        // 3. Handle Images Upload (Only if new ones are selected)
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $uploadDir = '../assets/uploads/products/';
            
            // Delete old images from DB (and optionally disk, but skipping disk for brevity)
            $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$product_id]);
            
            $insertImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
            $sort_order = 0;
            for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    if ($_FILES['images']['size'][$i] > 1048576) {
                        continue; // Skip files larger than 1MB
                    }
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

        // 4. Handle Hot Deal Request / Pricing
        $is_hot_deal_requested = isset($_POST['request_hot_deal']) && $_POST['request_hot_deal'] == '1';
        $hot_deal_orig = isset($_POST['hot_deal_original_price']) ? (float)$_POST['hot_deal_original_price'] : 0;
        $hot_deal_sale = isset($_POST['hot_deal_sale_price']) ? (float)$_POST['hot_deal_sale_price'] : 0;

        if ($hot_deal_orig > 0 && $hot_deal_sale > 0 && $hot_deal_sale < $hot_deal_orig) {
            $discount_pct = (int)round((($hot_deal_orig - $hot_deal_sale) / $hot_deal_orig) * 100);
            
            if ($is_hot_deal_requested) {
                // Rule 1: Min 15% discount
                if ($discount_pct < 15) {
                    $pdo->rollBack();
                    header('Location: ../site/seller-edit-product.php?id=' . $product_id . '&error=Hot+Deal+discount+must+be+at+least+15%25');
                    exit();
                }

                // Rule 2: Min stock 10
                if ($total_qty < 10) {
                    $pdo->rollBack();
                    header('Location: ../site/seller-edit-product.php?id=' . $product_id . '&error=Hot+Deals+require+at+least+10+items+in+stock');
                    exit();
                }

                // Rule 3: Max 2 active deals per seller
                $activeDealsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND is_hot_deal = 1 AND hot_deal_status = 'approved' AND id != ?");
                $activeDealsCountStmt->execute([$seller_id, $product_id]);
                if ((int)$activeDealsCountStmt->fetchColumn() >= 2) {
                    $pdo->rollBack();
                    header('Location: ../site/seller-edit-product.php?id=' . $product_id . '&error=Maximum+2+active+Hot+Deals+allowed+per+seller');
                    exit();
                }

                // Rule 4: 7-day cooldown after rejection
                $cooldownStmt = $pdo->prepare("SELECT reviewed_at FROM hot_deal_requests WHERE product_id = ? AND status = 'rejected' ORDER BY id DESC LIMIT 1");
                $cooldownStmt->execute([$product_id]);
                $lastRejection = $cooldownStmt->fetchColumn();
                if ($lastRejection && strtotime($lastRejection) > strtotime('-7 days')) {
                    $pdo->rollBack();
                    header('Location: ../site/seller-edit-product.php?id=' . $product_id . '&error=Cooldown+active:+Cannot+re-request+for+7+days+after+rejection');
                    exit();
                }

                // Rule 5: Expiry max 7 days
                $expiry_date = trim($_POST['hot_deal_expiry'] ?? '');
                if (empty($expiry_date) || strtotime($expiry_date) < strtotime('today') || strtotime($expiry_date) > strtotime('+7 days 23:59:59')) {
                    $expiry_date = date('Y-m-d', strtotime('+7 days'));
                }
                $expiry_datetime = $expiry_date . ' 23:59:59';

                // Rule 6: Reason
                $reason = trim($_POST['hot_deal_reason'] ?? 'Clearance Stock');

                // Update product table
                $updateHd = $pdo->prepare("UPDATE products SET 
                    original_price = ?, 
                    sale_price = ?, 
                    discount_percent = ?, 
                    hot_deal_status = 'pending', 
                    hot_deal_expiry = ?, 
                    hot_deal_request_reason = ? 
                    WHERE id = ?");
                $updateHd->execute([$hot_deal_orig, $hot_deal_sale, $discount_pct, $expiry_datetime, $reason, $product_id]);

                // Insert into hot_deal_requests
                $insertHdr = $pdo->prepare("INSERT INTO hot_deal_requests 
                    (product_id, seller_id, requested_discount, original_price, sale_price, reason, status, requested_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $insertHdr->execute([$product_id, $seller_id, $discount_pct, $hot_deal_orig, $hot_deal_sale, $reason]);

                // Notify Admins
                $adminStmt = $pdo->query("SELECT id FROM users WHERE user_type = 'admin'");
                $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($adminIds as $adminId) {
                    addNotification($conn, $adminId, "New Hot Deal Request: " . $name . " (-" . $discount_pct . "%)", 'warning', 'HotDeals', "admin/manage-products.php?tab=hot_deal_requests");
                }

                $pdo->commit();
                header('Location: ../site/seller-edit-product.php?id=' . $product_id . '&success=hot_deal_requested');
                exit();
            } else {
                // Just save the price reference without requesting approval
                $pdo->prepare("UPDATE products SET original_price = ?, sale_price = ?, discount_percent = ? WHERE id = ?")
                    ->execute([$hot_deal_orig, $hot_deal_sale, $discount_pct, $product_id]);
            }
        }

        $pdo->commit();
        header('Location: ../site/seller-dashboard.php?tab=products&success=product_updated');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: ../site/seller-edit-product.php?id=' . $product_id . '&error=' . urlencode($e->getMessage()));
        exit();
    }

} else {
    header('Location: ../site/seller-dashboard.php');
    exit();
}
?>
