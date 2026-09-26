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
    $is_free_shipping = isset($_POST['is_free_shipping']) ? 1 : 0;
    $shipping_cost = $is_free_shipping ? 0 : (isset($_POST['shipping_cost']) ? (float)$_POST['shipping_cost'] : 300.00);

    // Generate product code (e.g. PRD-1052)
    $stmt = $pdo->query("SELECT MAX(id) FROM products");
    $maxId = $stmt->fetchColumn();
    $nextId = $maxId ? $maxId + 1 : 1;
    $product_code = 'PRD-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    // Generate slug
    $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)) . '-' . uniqid();

    // Sum up variant quantities from colors array
    $total_qty = 0;
    if (isset($_POST['colors']) && is_array($_POST['colors'])) {
        foreach ($_POST['colors'] as $cId => $colorData) {
            if (isset($colorData['sizes']) && is_array($colorData['sizes'])) {
                foreach ($colorData['sizes'] as $sVal => $sData) {
                    if (isset($sData['active']) && $sData['active'] == 1) {
                        $total_qty += (int)$sData['qty'];
                    }
                }
            }
        }
    }
    if ($total_qty <= 0) {
        $total_qty = 0;
    }

    try {
        $pdo->beginTransaction();

        $uploadDir = '../assets/uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $insertProdImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");

        // 1. Insert Product
        $insertProd = $pdo->prepare("INSERT INTO products (product_code, seller_id, name, slug, brand_id, category_id, description, cost_price, base_price, total_qty, is_approved, status, is_free_shipping, shipping_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'active', ?, ?)");
        $insertProd->execute([$product_code, $seller_id, $name, $slug, $brand_id, $category_id, $description, $cost_price, $selling_price, $total_qty, $is_free_shipping, $shipping_cost]);
        
        $product_id = $pdo->lastInsertId();

        // 2. Insert Fixed Attributes
        if (isset($_POST['fixed_attr']) && is_array($_POST['fixed_attr'])) {
            $insertFixed = $pdo->prepare("INSERT INTO product_fixed_attributes (product_id, attribute_id, attribute_value) VALUES (?, ?, ?)");
            foreach ($_POST['fixed_attr'] as $attrId => $attrVal) {
                if (trim($attrVal) !== '') {
                    $insertFixed->execute([$product_id, (int)$attrId, trim($attrVal)]);
                }
            }
        }

        // 3. Handle Product Colors & Sizes
        if (isset($_POST['colors']) && is_array($_POST['colors'])) {
            $insertColor = $pdo->prepare("INSERT INTO product_colors (product_id, color_name, thumbnail_path) VALUES (?, ?, ?)");
            $insertSize = $pdo->prepare("INSERT INTO color_sizes (color_id, size, qty, cost_price, selling_price, sku) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($_POST['colors'] as $cId => $cData) {
                $cName = trim($cData['name']);
                if (empty($cName)) continue;
                
                $thumbPath = null;
                if (isset($_FILES["thumbnail_{$cId}"]) && $_FILES["thumbnail_{$cId}"]['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES["thumbnail_{$cId}"]['name'], PATHINFO_EXTENSION));
                    $thumbName = 'thumb_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES["thumbnail_{$cId}"]['tmp_name'], $uploadDir . $thumbName)) {
                        $thumbPath = $thumbName;
                    }
                }
                
                $insertColor->execute([$product_id, $cName, $thumbPath]);
                $newColorId = $pdo->lastInsertId();
                
                if (isset($cData['sizes']) && is_array($cData['sizes'])) {
                    foreach ($cData['sizes'] as $sVal => $sData) {
                        if (isset($sData['active']) && $sData['active'] == 1) {
                            $sQty = (int)$sData['qty'];
                            $sCost = (isset($cData['diff_price']) && $cData['diff_price'] == 1 && !empty($sData['cost'])) ? (float)$sData['cost'] : $cost_price;
                            $sSell = (isset($cData['diff_price']) && $cData['diff_price'] == 1 && !empty($sData['selling'])) ? (float)$sData['selling'] : $selling_price;
                            $sSku = trim($sData['sku']);
                            
                            $insertSize->execute([$newColorId, urldecode($sVal), $sQty, $sCost, $sSell, $sSku]);
                        }
                    }
                }
            }
        } else {
            // Default Standard Variant
            $insertColor = $pdo->prepare("INSERT INTO product_colors (product_id, color_name, thumbnail_path) VALUES (?, 'Standard', NULL)");
            $insertColor->execute([$product_id]);
            $newColorId = $pdo->lastInsertId();
            
            $insertSize = $pdo->prepare("INSERT INTO color_sizes (color_id, size, qty, cost_price, selling_price, sku) VALUES (?, 'Standard', ?, ?, ?, ?)");
            $insertSize->execute([$newColorId, $total_qty, $cost_price, $selling_price, $product_code]);
        }

        // 3. Handle Global Product Images
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $sort_order = 0;
            for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
                if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                    if ($_FILES['product_images']['size'][$i] > 10485760) continue; // Skip files > 10MB
                    
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

        // Auto sync base_price and total_qty from color_sizes to products
        $syncProd = $pdo->prepare("UPDATE products p SET 
            p.base_price = COALESCE((SELECT MIN(cs.selling_price) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.selling_price > 0), p.base_price),
            p.total_qty = COALESCE((SELECT SUM(cs.qty) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id), p.total_qty)
            WHERE p.id = ?");
        $syncProd->execute([$product_id]);

        $pdo->commit();
        header('Location: ../site/seller-dashboard.php?tab=products&success=product_added');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Product Add Error: " . $e->getMessage());
        header('Location: ../site/seller-add-product.php?error=database');
        exit();
    }

} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['update_product_btn']) || isset($_POST['update_product']))) {
    
    $seller_id = $_SESSION['userid'];
    $product_id = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $category_id = (int)$_POST['category_id'];
    $description = trim($_POST['description']);
    $cost_price = (float)$_POST['cost_price'];
    $selling_price = (float)$_POST['selling_price'];
    $is_free_shipping = isset($_POST['is_free_shipping']) ? 1 : 0;
    $shipping_cost = $is_free_shipping ? 0 : (isset($_POST['shipping_cost']) ? (float)$_POST['shipping_cost'] : 300.00);

    // Verify ownership
    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
    $checkStmt->execute([$product_id, $seller_id]);
    if (!$checkStmt->fetch()) {
        header('Location: ../site/seller-dashboard.php?error=unauthorized');
        exit();
    }

    // Sum up variant quantities
    $total_qty = 0;
    if (isset($_POST['colors']) && is_array($_POST['colors'])) {
        foreach ($_POST['colors'] as $cId => $colorData) {
            if (isset($colorData['sizes']) && is_array($colorData['sizes'])) {
                foreach ($colorData['sizes'] as $sVal => $sData) {
                    if (isset($sData['active']) && $sData['active'] == 1) {
                        $total_qty += (int)$sData['qty'];
                    }
                }
            }
        }
    }
    if ($total_qty <= 0) {
        $total_qty = 0;
    }

    try {
        $pdo->beginTransaction();

        // 1. Update Product
        $updateProd = $pdo->prepare("UPDATE products SET name = ?, brand_id = ?, category_id = ?, description = ?, cost_price = ?, base_price = ?, total_qty = ?, is_approved = 0, is_free_shipping = ?, shipping_cost = ? WHERE id = ?");
        $updateProd->execute([$name, $brand_id, $category_id, $description, $cost_price, $selling_price, $total_qty, $is_free_shipping, $shipping_cost, $product_id]);
        // 2. Update Fixed Attributes
        $pdo->prepare("DELETE FROM product_fixed_attributes WHERE product_id = ?")->execute([$product_id]);
        if (isset($_POST['fixed_attr']) && is_array($_POST['fixed_attr'])) {
            $insertFixed = $pdo->prepare("INSERT INTO product_fixed_attributes (product_id, attribute_id, attribute_value) VALUES (?, ?, ?)");
            foreach ($_POST['fixed_attr'] as $attrId => $attrVal) {
                if (trim($attrVal) !== '') {
                    $insertFixed->execute([$product_id, (int)$attrId, trim($attrVal)]);
                }
            }
        }

        // 3. Update Product Colors & Sizes
        $uploadDir = '../assets/uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $pdo->prepare("DELETE FROM product_colors WHERE product_id = ?")->execute([$product_id]);
        
        if (isset($_POST['colors']) && is_array($_POST['colors'])) {
            $insertColor = $pdo->prepare("INSERT INTO product_colors (product_id, color_name, thumbnail_path) VALUES (?, ?, ?)");
            $insertSize = $pdo->prepare("INSERT INTO color_sizes (color_id, size, qty, cost_price, selling_price, sku) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($_POST['colors'] as $cId => $cData) {
                $cName = trim($cData['name']);
                if (empty($cName)) continue;
                
                $thumbPath = null;
                if (isset($_FILES["thumbnail_{$cId}"]) && $_FILES["thumbnail_{$cId}"]['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES["thumbnail_{$cId}"]['name'], PATHINFO_EXTENSION));
                    $thumbName = 'thumb_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES["thumbnail_{$cId}"]['tmp_name'], $uploadDir . $thumbName)) {
                        $thumbPath = $thumbName;
                    }
                } else {
                    if (isset($cData['existing_thumbnail']) && !empty($cData['existing_thumbnail'])) {
                        $thumbPath = $cData['existing_thumbnail'];
                    }
                }
                
                $insertColor->execute([$product_id, $cName, $thumbPath]);
                $newColorId = $pdo->lastInsertId();
                
                if (isset($cData['sizes']) && is_array($cData['sizes'])) {
                    foreach ($cData['sizes'] as $sVal => $sData) {
                        if (isset($sData['active']) && $sData['active'] == 1) {
                            $sQty = (int)$sData['qty'];
                            $sCost = (isset($cData['diff_price']) && $cData['diff_price'] == 1 && !empty($sData['cost'])) ? (float)$sData['cost'] : $cost_price;
                            $sSell = (isset($cData['diff_price']) && $cData['diff_price'] == 1 && !empty($sData['selling'])) ? (float)$sData['selling'] : $selling_price;
                            $sSku = trim($sData['sku']);
                            
                            $insertSize->execute([$newColorId, urldecode($sVal), $sQty, $sCost, $sSell, $sSku]);
                        }
                    }
                }
            }
        } else {
            // Default Standard Variant
            $insertColor = $pdo->prepare("INSERT INTO product_colors (product_id, color_name, thumbnail_path) VALUES (?, 'Standard', NULL)");
            $insertColor->execute([$product_id]);
            $newColorId = $pdo->lastInsertId();
            
            $insertSize = $pdo->prepare("INSERT INTO color_sizes (color_id, size, qty, cost_price, selling_price, sku) VALUES (?, 'Standard', ?, ?, ?, ?)");
            $insertSize->execute([$newColorId, $total_qty, $cost_price, $selling_price, '']);
        }
        // 3. Handle Images (Deletions, Additions, and Primary Selection)
        $uploadDir = '../assets/uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 3a. Handle Deleted Images
        if (isset($_POST['deleted_images']) && is_array($_POST['deleted_images'])) {
            $delImgStmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND (image_path = ? OR id = ?)");
            foreach ($_POST['deleted_images'] as $delImg) {
                $delImg = trim($delImg);
                if (!empty($delImg)) {
                    $delImgStmt->execute([$product_id, $delImg, $delImg]);
                    if (file_exists($uploadDir . $delImg)) {
                        @unlink($uploadDir . $delImg);
                    }
                }
            }
        }

        // 3b. Handle Newly Uploaded Images (Only valid images up to 10MB)
        $newUploadedFileMap = [];
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            $insertImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, 0, ?)");
            
            $maxSortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?");
            $maxSortStmt->execute([$product_id]);
            $currentSort = (int)$maxSortStmt->fetchColumn() + 1;

            for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
                if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                    if ($_FILES['product_images']['size'][$i] > 10485760) {
                        continue; // Skip files larger than 10MB
                    }
                    $ext = strtolower(pathinfo($_FILES['product_images']['name'][$i], PATHINFO_EXTENSION));
                    $image_name = 'prod_' . $product_id . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $uploadDir . $image_name)) {
                        $insertImg->execute([$product_id, $image_name, $currentSort]);
                        $newUploadedFileMap[$i] = $image_name;
                        $currentSort++;
                    }
                }
            }
        }

        // 3c. Sync Primary Image Selection
        $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$product_id]);

        $primaryUpdated = false;
        $primarySelection = trim($_POST['primary_image'] ?? '');

        if (!empty($primarySelection)) {
            // Check if selected primary is one of the newly uploaded images (e.g. "new_0", "new_1")
            if (strpos($primarySelection, 'new_') === 0) {
                $newIdx = (int)substr($primarySelection, 4);
                if (isset($newUploadedFileMap[$newIdx])) {
                    $setPrimaryStmt = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE product_id = ? AND image_path = ?");
                    $setPrimaryStmt->execute([$product_id, $newUploadedFileMap[$newIdx]]);
                    $primaryUpdated = true;
                }
            } else {
                // Existing image path or ID
                $setPrimaryStmt = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE product_id = ? AND (image_path = ? OR id = ?)");
                $setPrimaryStmt->execute([$product_id, $primarySelection, $primarySelection]);
                if ($setPrimaryStmt->rowCount() > 0) {
                    $primaryUpdated = true;
                }
            }
        }

        // Fallback: If no image is marked primary, make the first image primary
        if (!$primaryUpdated) {
            $firstImgStmt = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
            $firstImgStmt->execute([$product_id]);
            $firstImgId = $firstImgStmt->fetchColumn();
            if ($firstImgId) {
                $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ?")->execute([$firstImgId]);
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

        // Auto sync base_price and total_qty from color_sizes to products
        $syncProd = $pdo->prepare("UPDATE products p SET 
            p.base_price = COALESCE((SELECT MIN(cs.selling_price) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id AND cs.selling_price > 0), p.base_price),
            p.total_qty = COALESCE((SELECT SUM(cs.qty) FROM color_sizes cs JOIN product_colors pc ON pc.id = cs.color_id WHERE pc.product_id = p.id), p.total_qty)
            WHERE p.id = ?");
        $syncProd->execute([$product_id]);

        // Notify Admins about the product update requiring approval
        $adminStmt = $pdo->query("SELECT id FROM users WHERE user_type = 'admin'");
        $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($adminIds as $adminId) {
            addNotification($conn, $adminId, "Product Updated - Pending Approval: " . $name, 'info', 'Products', "admin/manage-products.php?tab=pending");
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
